<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ClaudeWorkerClient;
use App\Support\PipelineSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PipelineSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.worker.url' => null, 'services.worker.secret' => null,
            'services.elevenlabs.key' => null, 'services.elevenlabs.voice_id' => null,
        ]);
        $this->admin = User::create(['name' => 'Admin', 'email' => 'a@example.com', 'password' => 'password123']);
        $this->admin->forceFill(['is_admin' => true, 'email_verified_at' => now()])->save();
        Sanctum::actingAs($this->admin);
    }

    public function test_only_an_admin_can_see_or_change_the_settings(): void
    {
        $user = User::create(['name' => 'U', 'email' => 'u@example.com', 'password' => 'password123']);
        Sanctum::actingAs($user);

        $this->getJson('/api/admin/pipeline')->assertForbidden();
        $this->putJson('/api/admin/pipeline', ['values' => ['worker_url' => 'https://w.test']])->assertForbidden();
        $this->postJson('/api/admin/pipeline/test/worker')->assertForbidden();
    }

    public function test_secrets_are_stored_encrypted_and_never_returned(): void
    {
        $res = $this->putJson('/api/admin/pipeline', ['values' => [
            'worker_url' => 'https://worker.test', 'worker_secret' => 'super-secret-1234', 'elevenlabs_key' => 'xi-key-abcd',
        ]])->assertOk();

        $res->assertJsonPath('fields.worker_url.value', 'https://worker.test')
            ->assertJsonPath('fields.worker_secret.hint', '****1234')
            ->assertJsonPath('fields.worker_secret.has_value', true)
            ->assertJsonPath('fields.worker_secret.value', null);
        $this->assertStringNotContainsString('super-secret-1234', $res->getContent());
        $this->assertStringNotContainsString('xi-key-abcd', $res->getContent());

        $this->assertNotSame('super-secret-1234', DB::table('pipeline_settings')->where('key', 'worker_secret')->value('value'));
        $this->assertSame('super-secret-1234', PipelineSettings::saved()['worker_secret']);
    }

    public function test_saved_values_override_the_env_defaults_and_reach_the_worker_client(): void
    {
        config(['services.worker.url' => 'https://from-env.test', 'series.a.publish_at' => '21:00']);
        Http::fake(['*' => Http::response(['job_id' => 'j'])]);

        $this->putJson('/api/admin/pipeline', ['values' => ['worker_url' => 'https://saved.test', 'worker_secret' => 's', 'series_publish_at' => '20:30']])->assertOk();

        $this->assertSame('20:30', config('series.a.publish_at'));
        $episode = \App\Models\Episode::create(['user_id' => $this->admin->id, 'series' => 'series-a', 'run_date' => now()->toDateString(), 'status' => 'pending']);
        (new \App\Services\ClaudeWorkerClient)->research($episode);
        Http::assertSent(fn ($r) => str_starts_with($r->url(), 'https://saved.test/jobs/research'));
    }

    public function test_a_blank_secret_keeps_the_saved_one_and_clear_goes_back_to_the_default(): void
    {
        $this->putJson('/api/admin/pipeline', ['values' => ['elevenlabs_key' => 'keep-me-9999', 'elevenlabs_voice_id' => 'voice1']])->assertOk();
        $this->putJson('/api/admin/pipeline', ['values' => ['elevenlabs_key' => '']])->assertOk()->assertJsonPath('fields.elevenlabs_key.hint', '****9999');

        $this->putJson('/api/admin/pipeline', ['values' => ['elevenlabs_voice_id' => ''], 'clear' => ['elevenlabs_key']])->assertOk()
            ->assertJsonPath('fields.elevenlabs_key.source', 'default')
            ->assertJsonPath('fields.elevenlabs_voice_id.source', 'default');
        $this->assertSame([], PipelineSettings::saved());
    }

    public function test_invalid_values_are_refused(): void
    {
        $this->putJson('/api/admin/pipeline', ['values' => ['series_publish_at' => '25:99']])->assertStatus(422);
        $this->putJson('/api/admin/pipeline', ['values' => ['series_timezone' => 'Mars/Base']])->assertStatus(422);
        $this->putJson('/api/admin/pipeline', ['values' => ['worker_url' => 'not a url']])->assertStatus(422);
        $this->putJson('/api/admin/pipeline', ['values' => ['elevenlabs_stability' => 3]])->assertStatus(422);
    }

    public function test_worker_test_reports_each_outcome(): void
    {
        $this->postJson('/api/admin/pipeline/test/worker')->assertJsonPath('success', false);

        config(['services.worker.url' => 'https://worker.test', 'services.worker.secret' => 'secret']);
        Http::fake(['worker.test/health' => Http::sequence()->push('ok', 200)->push('no', 401)->push('', 404)]);

        $this->postJson('/api/admin/pipeline/test/worker')->assertJsonPath('success', true);
        $this->postJson('/api/admin/pipeline/test/worker')->assertJsonPath('success', false)->assertJsonFragment(['message' => 'The worker rejected the secret. It must match the secret saved on the worker.']);
        $this->postJson('/api/admin/pipeline/test/worker')->assertJsonPath('success', false);

        Http::assertSent(fn ($r) => $r->url() === 'https://worker.test/health' && $r->header('X-Signature')[0] === ClaudeWorkerClient::sign(''));
    }

    public function test_elevenlabs_test_shows_plan_characters_voice_and_urdu_support(): void
    {
        config(['services.elevenlabs.key' => 'k', 'services.elevenlabs.voice_id' => 'v1', 'services.elevenlabs.model_id' => 'eleven_v3']);
        Http::fake([
            'api.elevenlabs.io/v1/user/subscription' => Http::response(['tier' => 'creator', 'character_count' => 1000, 'character_limit' => 100000]),
            'api.elevenlabs.io/v1/voices/v1' => Http::response(['name' => 'Bilal', 'category' => 'professional']),
            'api.elevenlabs.io/v1/models' => Http::response([['model_id' => 'eleven_v3', 'languages' => [['language_id' => 'en'], ['language_id' => 'ur']]]]),
        ]);

        $this->postJson('/api/admin/pipeline/test/elevenlabs')->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('details.voice', 'Bilal')
            ->assertJsonPath('details.characters_limit', 100000)
            ->assertJsonPath('details.urdu', true);
    }

    public function test_elevenlabs_test_explains_a_bad_key(): void
    {
        config(['services.elevenlabs.key' => 'bad', 'services.elevenlabs.voice_id' => 'v1']);
        Http::fake(['api.elevenlabs.io/*' => Http::response(['detail' => ['status' => 'invalid_api_key']], 401)]);
        $this->postJson('/api/admin/pipeline/test/elevenlabs')->assertJsonPath('success', false)->assertJsonFragment(['message' => 'ElevenLabs rejected the API key.']);
    }

    public function test_elevenlabs_test_explains_an_unknown_voice(): void
    {
        config(['services.elevenlabs.key' => 'k', 'services.elevenlabs.voice_id' => 'v1']);
        Http::fake([
            'api.elevenlabs.io/v1/user/subscription' => Http::response(['tier' => 'free', 'character_count' => 0, 'character_limit' => 10000]),
            'api.elevenlabs.io/v1/voices/*' => Http::response([], 404),
        ]);
        $this->postJson('/api/admin/pipeline/test/elevenlabs')->assertJsonPath('success', false)
            ->assertJsonFragment(['message' => 'The key works, but the voice ID was not found in this account.']);
    }

    public function test_checklist_reflects_the_heartbeats_and_missing_settings(): void
    {
        config(['queue.default' => 'database']);
        $checks = fn () => collect($this->getJson('/api/admin/pipeline')->json('checks'))->keyBy('key');

        $this->assertFalse($checks()['scheduler']['ok']);
        $this->assertFalse($checks()['queue']['ok']);
        $this->assertFalse($checks()['worker']['ok']);

        Cache::put('pipeline:scheduler-ran', now()->timestamp);
        Cache::put('pipeline:queue-ran', now()->timestamp);
        config(['services.worker.url' => 'https://w.test', 'services.worker.secret' => 's']);

        $this->assertTrue($checks()['scheduler']['ok']);
        $this->assertTrue($checks()['queue']['ok']);
        $this->assertTrue($checks()['worker']['ok']);
        $this->assertTrue($checks()['owner']['ok']);
    }

    public function test_queue_heartbeat_job_records_that_a_worker_ran(): void
    {
        (new \App\Jobs\QueueHeartbeatJob)->handle();

        $this->assertNotNull(Cache::get('pipeline:queue-ran'));
    }
    public function test_every_series_a_setting_can_be_saved_and_is_used(): void
    {
        $other = User::create(['name' => 'Owner', 'email' => 'owner@example.com', 'password' => 'password123']);

        $res = $this->putJson('/api/admin/pipeline', ['values' => [
            'series_research_at' => '16:30', 'series_publish_at' => '20:15', 'series_timezone' => 'Asia/Dubai', 'series_shorts' => 2,
            'series_skip_before_minutes' => 30, 'series_user_id' => $other->id,
            'series_platforms_long' => ['youtube', 'linkedin'], 'series_platforms_short' => ['tiktok', 'twitter'],
            'series_limit_researching' => 45, 'series_limit_voicing' => 20, 'series_limit_editing' => 90,
        ]])->assertOk();

        $res->assertJsonPath('fields.series_platforms_long.value', ['youtube', 'linkedin'])
            ->assertJsonPath('fields.series_platforms_short.source', 'saved')
            ->assertJsonPath('fields.series_user_id.value', $other->id)
            ->assertJsonPath('fields.series_limit_editing.value', 90);

        $this->assertSame('16:30', config('series.a.research_at'));
        $this->assertSame('20:15', config('series.a.publish_at'));
        $this->assertSame('Asia/Dubai', config('series.a.timezone'));
        $this->assertSame(2, config('series.a.shorts'));
        $this->assertSame(30, config('series.a.skip_before_minutes'));
        $this->assertSame($other->id, config('series.a.user_id'));
        $this->assertSame(['youtube', 'linkedin'], config('series.a.platforms.long'));
        $this->assertSame(['tiktok', 'twitter'], config('series.a.platforms.short'));
        $this->assertSame(['researching' => 45, 'voicing' => 20, 'editing' => 90], config('series.a.limits'));

        // and a fresh request (the settings come back from the database) sees the same
        config(['series.a.platforms.long' => ['facebook'], 'series.a.limits.editing' => 1]);
        PipelineSettings::apply();
        $this->assertSame(['youtube', 'linkedin'], config('series.a.platforms.long'));
        $this->assertSame(90, config('series.a.limits.editing'));
    }

    public function test_the_saved_owner_gets_the_daily_episode_and_the_saved_limit_stops_a_stuck_step(): void
    {
        \Illuminate\Support\Facades\Queue::fake();
        $owner = User::create(['name' => 'Owner', 'email' => 'owner@example.com', 'password' => 'password123']);
        $this->putJson('/api/admin/pipeline', ['values' => ['series_user_id' => $owner->id, 'series_limit_researching' => 5]])->assertOk();

        $this->artisan('episodes:start-daily')->assertSuccessful();
        $this->assertSame($owner->id, \App\Models\Episode::firstOrFail()->user_id);

        $stuck = \App\Models\Episode::create(['user_id' => $owner->id, 'series' => 'series-a', 'run_date' => now()->subDay()->toDateString(), 'status' => 'researching', 'step_started_at' => now()->subMinutes(10), 'scheduled_for' => now()->addDay()]);
        (new \App\Jobs\EpisodeWatchdogJob)->handle();

        $this->assertSame('failed', $stuck->fresh()->status);
    }

    public function test_series_a_platform_lists_and_numbers_are_validated(): void
    {
        $this->putJson('/api/admin/pipeline', ['values' => ['series_platforms_long' => []]])->assertStatus(422);
        $this->putJson('/api/admin/pipeline', ['values' => ['series_platforms_short' => ['myspace']]])->assertStatus(422);
        $this->putJson('/api/admin/pipeline', ['values' => ['series_user_id' => 99999]])->assertStatus(422);
        $this->putJson('/api/admin/pipeline', ['values' => ['series_limit_editing' => 0]])->assertStatus(422);
        $this->putJson('/api/admin/pipeline', ['values' => ['series_skip_before_minutes' => 500]])->assertStatus(422);
    }

    public function test_a_blank_series_setting_goes_back_to_the_built_in_default(): void
    {
        $this->putJson('/api/admin/pipeline', ['values' => ['series_limit_voicing' => 55, 'series_user_id' => $this->admin->id]])->assertOk();

        $this->putJson('/api/admin/pipeline', ['values' => ['series_limit_voicing' => '', 'series_user_id' => '']])->assertOk()
            ->assertJsonPath('fields.series_limit_voicing.source', 'default')
            ->assertJsonPath('fields.series_limit_voicing.value', 10)
            ->assertJsonPath('fields.series_user_id.value', null);
    }

    public function test_the_page_gets_the_owner_list_and_the_video_platforms(): void
    {
        $this->getJson('/api/admin/pipeline')->assertOk()
            ->assertJsonPath('users.0.email', 'a@example.com')
            ->assertJsonPath('video_platforms', ['youtube', 'facebook', 'instagram', 'tiktok', 'twitter', 'linkedin']);
    }
}
