<?php

namespace Tests\Feature;

use App\Jobs\EpisodeWatchdogJob;
use App\Jobs\GenerateVoiceJob;
use App\Jobs\ImportOutputsJob;
use App\Jobs\ScheduleEpisodePostsJob;
use App\Jobs\StartEditJob;
use App\Jobs\StartResearchJob;
use App\Models\Episode;
use App\Models\Platform;
use App\Models\Post;
use App\Models\User;
use App\Services\ClaudeWorkerClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EpisodePipelineTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.worker.url' => 'https://worker.test',
            'services.worker.secret' => 'secret',
            'services.elevenlabs.key' => 'key',
            'services.elevenlabs.voice_id' => 'voice',
        ]);
        Storage::fake('local');
        Storage::fake('public');

        $this->user = User::create(['name' => 'Bilal', 'email' => 'b@example.com', 'password' => 'password123']);
        $this->user->forceFill(['is_admin' => true, 'email_verified_at' => now()])->save();
    }

    private function episode(string $status, array $attributes = []): Episode
    {
        return Episode::create($attributes + [
            'user_id' => $this->user->id,
            'series' => 'series-a',
            'run_date' => now()->toDateString(),
            'status' => $status,
            'scheduled_for' => now()->addHours(5),
            'script_ur' => "پہلا پیراگراف۔\n\nدوسرا پیراگراف۔",
        ]);
    }

    private function webhook(array $body, ?string $signature = null)
    {
        $json = json_encode($body);

        return $this->call('POST', '/api/webhooks/worker', [], [], [], [
            'HTTP_X_SIGNATURE' => $signature ?? ClaudeWorkerClient::sign($json),
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], $json);
    }

    // ---- webhook ----

    public function test_webhook_rejects_a_bad_signature(): void
    {
        $e = $this->episode('researching');

        $this->webhook(['episode_id' => $e->id, 'type' => 'research', 'script' => 'x'], 'wrong')->assertStatus(401);
        $this->assertSame('researching', $e->fresh()->status);
    }

    public function test_webhook_is_refused_when_no_secret_is_configured(): void
    {
        config(['services.worker.secret' => '']);
        $e = $this->episode('researching');

        $this->webhook(['episode_id' => $e->id, 'type' => 'research', 'script' => 'x'], '')->assertStatus(503);
    }

    public function test_research_result_moves_to_approval_and_a_duplicate_is_ignored(): void
    {
        $e = $this->episode('researching', ['script_ur' => null]);
        $body = ['episode_id' => $e->id, 'type' => 'research', 'status' => 'ok', 'topic' => 'AI', 'script' => 'first', 'sources' => [['title' => 't', 'url' => 'https://a.test', 'claim' => 'c']]];

        $this->webhook($body)->assertNoContent();
        $this->webhook(['script' => 'second'] + $body)->assertNoContent();

        $e->refresh();
        $this->assertSame('awaiting_approval', $e->status);
        $this->assertSame('first', $e->script_ur);
        $this->assertSame('AI', $e->topic);
        $this->assertCount(1, $e->logs()->where('step', 'script')->get());
    }

    public function test_unknown_result_type_is_rejected(): void
    {
        $e = $this->episode('researching');

        $this->webhook(['episode_id' => $e->id, 'type' => 'nonsense', 'status' => 'ok'])->assertStatus(422);
    }

    public function test_failed_result_fails_the_episode_and_remembers_the_step(): void
    {
        $e = $this->episode('editing');

        $this->webhook(['episode_id' => $e->id, 'type' => 'edit', 'status' => 'failed', 'error' => 'ffmpeg died'])->assertNoContent();

        $e->refresh();
        $this->assertSame('failed', $e->status);
        $this->assertSame('editing', $e->failed_step);
        $this->assertStringContainsString('ffmpeg died', $e->error);
    }

    public function test_failed_result_is_ignored_when_the_episode_is_not_waiting_on_the_worker(): void
    {
        $e = $this->episode('awaiting_approval');

        $this->webhook(['episode_id' => $e->id, 'type' => 'research', 'status' => 'failed', 'error' => 'late'])->assertNoContent();

        $this->assertSame('awaiting_approval', $e->fresh()->status);
    }

    public function test_edit_result_dispatches_the_import_only_while_editing(): void
    {
        Queue::fake();
        $editing = $this->episode('editing');
        $files = ['long' => 'https://worker.test/files/1/long.mp4', 'shorts' => []];

        $this->webhook(['episode_id' => $editing->id, 'type' => 'edit', 'status' => 'ok', 'files' => $files, 'meta' => []])->assertNoContent();
        Queue::assertPushed(ImportOutputsJob::class, 1);

        $other = $this->episode('ready', ['run_date' => now()->addDay()->toDateString()]);
        $this->webhook(['episode_id' => $other->id, 'type' => 'edit', 'status' => 'ok', 'files' => $files])->assertNoContent();
        Queue::assertPushed(ImportOutputsJob::class, 1);
    }

    // ---- approval ----

    public function test_approve_starts_the_voice_once_and_keeps_edits(): void
    {
        Queue::fake();
        Sanctum::actingAs($this->user);
        $e = $this->episode('awaiting_approval');

        $this->postJson("/api/episodes/{$e->id}/approve", ['script_ur' => 'edited'])->assertOk()->assertJsonPath('status', 'approved');
        $this->postJson("/api/episodes/{$e->id}/approve")->assertStatus(422);

        $this->assertSame('edited', $e->fresh()->script_ur);
        Queue::assertPushed(GenerateVoiceJob::class, 1);
    }

    public function test_reject_can_re_run_research_or_stop_the_day(): void
    {
        Queue::fake();
        Sanctum::actingAs($this->user);
        $again = $this->episode('awaiting_approval');
        $stop = $this->episode('awaiting_approval', ['run_date' => now()->addDay()->toDateString()]);

        $this->postJson("/api/episodes/{$again->id}/reject", ['note' => 'too long', 'retry' => true])->assertOk()->assertJsonPath('status', 'pending');
        $this->postJson("/api/episodes/{$stop->id}/reject", ['note' => 'no', 'retry' => false])->assertOk()->assertJsonPath('status', 'rejected');

        $this->assertNull($again->fresh()->script_ur);
        Queue::assertPushed(StartResearchJob::class, 1);
    }

    public function test_revise_keeps_the_script_and_sends_it_with_the_instruction(): void
    {
        Http::fake(['worker.test/*' => Http::response(['job_id' => 'j1'])]);
        Sanctum::actingAs($this->user);
        $e = $this->episode('awaiting_approval', ['script_ur' => 'original script']);

        $this->postJson("/api/episodes/{$e->id}/revise", ['instruction' => 'shorter intro'])->assertOk();

        $e->refresh();
        $this->assertSame('researching', $e->status);
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/jobs/research')
            && $r['feedback'] === 'shorter intro' && $r['current_script'] === 'original script'
            && hash_equals(ClaudeWorkerClient::sign($r->body()), $r->header('X-Signature')[0]));
    }

    public function test_other_users_cannot_see_an_episode(): void
    {
        $e = $this->episode('awaiting_approval');
        $other = User::create(['name' => 'Other', 'email' => 'o@example.com', 'password' => 'password123']);
        Sanctum::actingAs($other);

        $this->getJson("/api/episodes/{$e->id}")->assertNotFound();
        $this->postJson("/api/episodes/{$e->id}/approve")->assertNotFound();
    }

    public function test_pasted_script_creates_an_episode_waiting_for_approval(): void
    {
        Queue::fake();
        Sanctum::actingAs($this->user);

        $this->postJson('/api/episodes', ['script_ur' => 'pasted', 'topic' => 'T'])->assertCreated()->assertJsonPath('status', 'awaiting_approval');
        $this->postJson('/api/episodes', ['script_ur' => 'again'])->assertStatus(422); // one per day
        Queue::assertNothingPushed();
    }

    // ---- voice ----

    public function test_voice_job_saves_audio_captions_and_moves_to_avatar(): void
    {
        Http::fake([
            'api.elevenlabs.io/v1/user/subscription' => Http::response(['character_limit' => 100000, 'character_count' => 10]),
            'api.elevenlabs.io/v1/text-to-speech/*' => Http::response([
                'audio_base64' => base64_encode('MP3'),
                'alignment' => [
                    'characters' => ['a', 'b', ' ', 'c'],
                    'character_start_times_seconds' => [0, 0.1, 0.2, 0.3],
                    'character_end_times_seconds' => [0.1, 0.2, 0.3, 0.4],
                ],
            ]),
        ]);
        $e = $this->episode('approved');

        (new GenerateVoiceJob($e->id))->handle(app(\App\Services\ElevenLabsService::class));

        $e->refresh();
        $this->assertSame('awaiting_avatar', $e->status);
        Storage::disk('local')->assertExists("episodes/{$e->id}/voice.mp3");
        $this->assertStringContainsString('ab c', Storage::disk('local')->get("episodes/{$e->id}/captions.srt"));
    }

    public function test_voice_job_stops_when_characters_run_out(): void
    {
        Http::fake(['api.elevenlabs.io/v1/user/subscription' => Http::response(['character_limit' => 100, 'character_count' => 100])]);
        $e = $this->episode('approved');
        $job = new GenerateVoiceJob($e->id);

        try {
            $job->handle(app(\App\Services\ElevenLabsService::class));
            $this->fail('expected an exception');
        } catch (\RuntimeException $ex) {
            $job->failed($ex);
        }

        $e->refresh();
        $this->assertSame('failed', $e->status);
        $this->assertSame('voicing', $e->failed_step);
    }

    public function test_srt_uses_the_alignment_timings(): void
    {
        $srt = app(\App\Services\ElevenLabsService::class)->toSrt([
            'chars' => str_split('hi there'),
            'starts' => [0, 0.1, 0.2, 0.3, 0.4, 0.5, 0.6, 0.7],
            'ends' => [0.1, 0.2, 0.3, 0.4, 0.5, 0.6, 0.7, 0.8],
        ]);

        $this->assertSame("1\n00:00:00,000 --> 00:00:00,800\nhi there\n\n", $srt);
    }

    // ---- avatar + edit ----

    public function test_avatar_upload_starts_the_edit(): void
    {
        Queue::fake();
        Sanctum::actingAs($this->user);
        $e = $this->episode('awaiting_avatar');

        $file = UploadedFile::fake()->create('avatar.mp4', 100, 'video/mp4');
        $this->postJson("/api/episodes/{$e->id}/avatar", ['file' => $file])->assertOk()->assertJsonPath('has_avatar', true);

        Queue::assertPushed(StartEditJob::class, 1);
        Storage::disk('local')->assertExists("episodes/{$e->id}/avatar.mp4");
    }

    public function test_edit_job_sends_signed_file_links_to_the_worker(): void
    {
        Http::fake(['worker.test/*' => Http::response(['job_id' => 'j2'])]);
        $e = $this->episode('awaiting_avatar', ['avatar_path' => 'a.mp4', 'voice_path' => 'v.mp3', 'captions_path' => 'c.srt']);

        (new StartEditJob($e->id))->handle(app(ClaudeWorkerClient::class));

        $this->assertSame('editing', $e->fresh()->status);
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/jobs/edit') && str_contains($r['files']['voice'], 'signature='));
    }

    public function test_signed_file_link_works_and_unsigned_does_not(): void
    {
        $e = $this->episode('awaiting_avatar', ['voice_path' => "episodes/1/voice.mp3"]);
        Storage::disk('local')->put('episodes/1/voice.mp3', 'MP3');

        $this->get("/api/episode-files/{$e->id}/voice")->assertForbidden();
        $this->get(ClaudeWorkerClient::fileUrl($e, 'voice'))->assertOk();
    }

    // ---- import + schedule ----

    public function test_import_downloads_the_outputs_and_moves_to_ready(): void
    {
        Http::fake(['worker.test/*' => fn () => Http::response('VIDEO')]);
        $e = $this->episode('editing');

        (new ImportOutputsJob($e->id, [
            'long' => 'https://worker.test/files/1/long.mp4',
            'shorts' => ['https://worker.test/files/1/s1.mp4'],
            'thumbnail' => 'https://worker.test/files/1/t.png',
        ], ['titles' => ['long' => 'L', 'shorts' => ['S1']]]))->handle();

        $e->refresh();
        $this->assertSame('ready', $e->status);
        $this->assertSame(3, \App\Models\Media::count());
        $this->assertCount(1, $e->outputs['shorts']);
        $this->assertNull(Post::first(), 'posts wait for the final approval');
    }

    public function test_import_refuses_files_from_another_host_and_cleans_up(): void
    {
        Http::fake(['worker.test/*' => fn () => Http::response('VIDEO')]);
        $e = $this->episode('editing');
        $job = new ImportOutputsJob($e->id, ['long' => 'https://worker.test/a.mp4', 'shorts' => ['https://evil.test/b.mp4']], []);

        try {
            $job->handle();
            $this->fail('expected an exception');
        } catch (\RuntimeException $ex) {
            $job->failed($ex);
        }

        $this->assertSame(0, \App\Models\Media::count());
        $this->assertSame('failed', $e->fresh()->status);
        $this->assertSame('importing', $e->fresh()->failed_step);
    }

    private function readyEpisode(): Episode
    {
        $long = \App\Models\Media::create(['user_id' => $this->user->id, 'filename' => 'l.mp4', 'original_filename' => 'l.mp4', 'mime_type' => 'video/mp4', 'size' => 10, 'path' => 'uploads/l.mp4', 'type' => 'video']);
        $short = \App\Models\Media::create(['user_id' => $this->user->id, 'filename' => 's.mp4', 'original_filename' => 's.mp4', 'mime_type' => 'video/mp4', 'size' => 10, 'path' => 'uploads/s.mp4', 'type' => 'video']);

        foreach (['youtube', 'facebook', 'instagram'] as $type) {
            Platform::create(['user_id' => $this->user->id, 'platform_type' => $type, 'credentials' => ['t' => 1], 'is_active' => true]);
        }

        return $this->episode('ready', ['outputs' => [
            'long' => $long->id, 'shorts' => [$short->id], 'thumbnail' => null,
            'titles' => ['long' => 'Long title', 'shorts' => ['Short title']],
            'descriptions' => ['long' => 'Long desc', 'shorts' => ['Short desc']],
        ]]);
    }

    public function test_final_approval_creates_scheduled_posts_and_cancel_posting_undoes_it(): void
    {
        Sanctum::actingAs($this->user);
        $e = $this->readyEpisode();

        $this->postJson("/api/episodes/{$e->id}/approve-outputs")->assertOk(); // queue is sync in tests

        $e->refresh();
        $this->assertSame('scheduled', $e->status);
        $this->assertCount(2, $e->post_ids);
        $long = Post::find($e->post_ids[0]);
        $this->assertSame('scheduled', $long->status);
        $this->assertStringStartsWith('Long title', $long->content);
        $this->assertCount(2, $long->platforms); // youtube + facebook
        $this->assertCount(1, $long->schedules);

        $this->postJson("/api/episodes/{$e->id}/cancel-posting")->assertOk()->assertJsonPath('status', 'ready');
        $this->assertSame(0, Post::count());
        $this->assertSame(2, \App\Models\Media::whereNull('post_id')->count(), 'videos stay in the library');
    }

    public function test_scheduling_twice_does_not_create_duplicate_posts(): void
    {
        $e = $this->readyEpisode();

        (new ScheduleEpisodePostsJob($e->id))->handle();
        (new ScheduleEpisodePostsJob($e->id))->handle();

        $this->assertSame(2, Post::count());
    }

    public function test_scheduling_fails_when_no_platform_is_connected(): void
    {
        $e = $this->episode('ready', ['outputs' => ['long' => 1, 'shorts' => [], 'titles' => [], 'descriptions' => []]]);
        $job = new ScheduleEpisodePostsJob($e->id);

        try {
            $job->handle();
            $this->fail('expected an exception');
        } catch (\RuntimeException $ex) {
            $job->failed($ex);
        }

        $this->assertSame('failed', $e->fresh()->status);
        $this->assertSame(0, Post::count());
    }

    public function test_retry_goes_back_one_step(): void
    {
        Queue::fake();
        Sanctum::actingAs($this->user);
        $e = $this->episode('failed', ['failed_step' => 'voicing', 'error' => 'boom']);

        $this->postJson("/api/episodes/{$e->id}/retry")->assertOk()->assertJsonPath('status', 'approved');

        Queue::assertPushed(GenerateVoiceJob::class, 1);
        $this->assertNull($e->fresh()->error);
    }

    public function test_publish_time_can_be_changed_but_not_into_the_past(): void
    {
        Sanctum::actingAs($this->user);
        $e = $this->episode('awaiting_approval');
        $tomorrow = now()->addDay()->setTime(21, 0)->toIso8601String();

        $this->patchJson("/api/episodes/{$e->id}", ['scheduled_for' => $tomorrow])->assertOk();
        $this->patchJson("/api/episodes/{$e->id}", ['scheduled_for' => now()->subHour()->toIso8601String()])->assertStatus(422);
    }

    // ---- daily start + watchdog ----

    public function test_daily_command_creates_one_episode_per_day(): void
    {
        Queue::fake();

        $this->artisan('episodes:start-daily')->assertSuccessful();
        $this->artisan('episodes:start-daily')->assertSuccessful();

        $this->assertSame(1, Episode::count());
        Queue::assertPushed(StartResearchJob::class, 1);
        $this->assertSame(Episode::today(), Episode::first()->run_date->toDateString());
    }

    public function test_watchdog_fails_stuck_steps_skips_late_days_and_closes_published(): void
    {
        Bus::fake([\App\Jobs\EpisodeSummaryJob::class]);

        $stuck = $this->episode('researching', ['step_started_at' => now()->subMinutes(45)]);
        $late = $this->episode('awaiting_approval', ['run_date' => now()->addDay()->toDateString(), 'scheduled_for' => now()->addMinutes(10)]);
        $fine = $this->episode('awaiting_approval', ['run_date' => now()->addDays(2)->toDateString(), 'scheduled_for' => now()->addHours(3)]);

        $post = Post::create(['user_id' => $this->user->id, 'content' => 'x', 'platforms' => [], 'status' => 'published']);
        $done = $this->episode('scheduled', ['run_date' => now()->subDay()->toDateString(), 'scheduled_for' => now()->subHour(), 'post_ids' => [$post->id]]);

        (new EpisodeWatchdogJob)->handle();

        $this->assertSame('failed', $stuck->fresh()->status);
        $this->assertSame('researching', $stuck->fresh()->failed_step);
        $this->assertSame('skipped', $late->fresh()->status);
        $this->assertSame('awaiting_approval', $fine->fresh()->status);
        $this->assertSame('published', $done->fresh()->status);
        Bus::assertDispatched(\App\Jobs\EpisodeSummaryJob::class);
    }
}
