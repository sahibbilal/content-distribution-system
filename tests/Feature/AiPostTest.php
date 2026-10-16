<?php

namespace Tests\Feature;

use App\Jobs\ImportImageJob;
use App\Jobs\ScheduleEpisodePostsJob;
use App\Jobs\StartResearchJob;
use App\Models\Episode;
use App\Models\Media;
use App\Models\Platform;
use App\Models\Post;
use App\Models\User;
use App\Services\ClaudeWorkerClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** On-demand text and text + image posts made by the assistant (the non-video kinds of an episode) */
class AiPostTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Platform $linkedin;
    private Platform $instagram;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.worker.url' => 'https://worker.test', 'services.worker.secret' => 'secret']);
        Storage::fake('public');

        $this->user = User::create(['name' => 'Bilal', 'email' => 'b@example.com', 'password' => 'password123']);
        $this->user->forceFill(['is_admin' => true, 'email_verified_at' => now()])->save();
        Sanctum::actingAs($this->user);

        $this->linkedin = Platform::create(['user_id' => $this->user->id, 'platform_type' => 'linkedin', 'credentials' => ['t' => 1], 'is_active' => true]);
        $this->instagram = Platform::create(['user_id' => $this->user->id, 'platform_type' => 'instagram', 'credentials' => ['t' => 1], 'is_active' => true]);
    }

    private function webhook(array $body)
    {
        $json = json_encode($body);

        return $this->call('POST', '/api/webhooks/worker', [], [], [], [
            'HTTP_X_SIGNATURE' => ClaudeWorkerClient::sign($json),
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], $json);
    }

    public function test_several_on_demand_posts_can_exist_on_the_same_day(): void
    {
        Queue::fake();

        $this->postJson('/api/episodes', ['kind' => 'text', 'prompt' => 'AI news', 'platform_ids' => [$this->linkedin->id]])->assertCreated();
        $this->postJson('/api/episodes', ['kind' => 'text', 'prompt' => 'Laravel tips', 'platform_ids' => [$this->linkedin->id]])->assertCreated();

        $this->assertSame(2, Episode::where('kind', 'text')->count());
        Queue::assertPushed(StartResearchJob::class, 2);
    }

    public function test_text_post_needs_a_prompt_and_a_connected_platform_the_user_owns(): void
    {
        Queue::fake();
        $other = User::create(['name' => 'O', 'email' => 'o@example.com', 'password' => 'password123']);
        $foreign = Platform::create(['user_id' => $other->id, 'platform_type' => 'linkedin', 'credentials' => ['t' => 1], 'is_active' => true]);

        $this->postJson('/api/episodes', ['kind' => 'text', 'platform_ids' => [$this->linkedin->id]])->assertStatus(422);
        $this->postJson('/api/episodes', ['kind' => 'text', 'prompt' => 'x'])->assertStatus(422);
        $this->postJson('/api/episodes', ['kind' => 'text', 'prompt' => 'x', 'platform_ids' => [$foreign->id]])->assertStatus(422);
        Queue::assertNothingPushed();
    }

    public function test_text_only_post_is_refused_for_a_platform_that_needs_media(): void
    {
        Queue::fake();

        $this->postJson('/api/episodes', ['kind' => 'text', 'prompt' => 'x', 'platform_ids' => [$this->instagram->id]])
            ->assertStatus(422)->assertJsonFragment(['message' => 'Instagram needs an image or a video to publish.']);
        // the same platform is fine once an image comes with the post
        $this->postJson('/api/episodes', ['kind' => 'image', 'prompt' => 'x', 'platform_ids' => [$this->instagram->id]])->assertCreated();
    }

    public function test_worker_gets_the_kind_prompt_and_platform_types(): void
    {
        Http::fake(['worker.test/*' => Http::response(['job_id' => 'j1'])]);

        $this->postJson('/api/episodes', ['kind' => 'text', 'prompt' => 'Write about Laravel 12', 'platform_ids' => [$this->linkedin->id]])->assertCreated();

        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/jobs/research')
            && $r['kind'] === 'text' && $r['prompt'] === 'Write about Laravel 12' && $r['platforms'] === ['linkedin']);
    }

    public function test_text_post_is_approved_and_scheduled_in_one_tap_without_voice_or_video(): void
    {
        $e = Episode::create([
            'user_id' => $this->user->id, 'series' => 'ai-post', 'kind' => 'text', 'run_date' => now()->toDateString(),
            'status' => 'researching', 'platform_ids' => [$this->linkedin->id], 'scheduled_for' => now()->addHours(2),
        ]);

        $this->webhook(['episode_id' => $e->id, 'type' => 'research', 'status' => 'ok', 'topic' => 'T', 'script' => 'Hello LinkedIn', 'sources' => []])->assertNoContent();
        $this->assertSame('awaiting_approval', $e->fresh()->status);

        $this->postJson("/api/episodes/{$e->id}/approve", ['script_ur' => 'Hello LinkedIn, edited'])->assertOk()->assertJsonPath('status', 'scheduled');

        $post = Post::firstOrFail();
        $this->assertSame('Hello LinkedIn, edited', $post->content);
        $this->assertSame([$this->linkedin->id], $post->platforms);
        $this->assertSame('scheduled', $post->status);
        $this->assertCount(1, $post->schedules);
        $this->assertSame(0, $post->media()->count());
    }

    public function test_image_job_downloads_the_image_before_approval_and_attaches_it_to_the_post(): void
    {
        Http::fake(['worker.test/*' => fn () => Http::response('PNG')]);
        $e = Episode::create([
            'user_id' => $this->user->id, 'series' => 'ai-post', 'kind' => 'image', 'run_date' => now()->toDateString(),
            'status' => 'researching', 'platform_ids' => [$this->linkedin->id, $this->instagram->id], 'scheduled_for' => now()->addHours(2),
        ]);

        $this->webhook(['episode_id' => $e->id, 'type' => 'research', 'status' => 'ok', 'topic' => 'T', 'script' => 'Caption', 'image' => 'https://worker.test/files/1/card.png'])->assertNoContent();

        $e->refresh();
        $this->assertSame('awaiting_approval', $e->status);
        $this->assertSame(1, Media::count());

        $this->postJson("/api/episodes/{$e->id}/approve")->assertOk()->assertJsonPath('status', 'scheduled');

        $post = Post::firstOrFail();
        $this->assertSame(1, $post->media()->count());
        $this->assertCount(2, $post->platforms);
    }

    public function test_image_job_without_an_image_fails_and_a_duplicate_webhook_downloads_once(): void
    {
        Queue::fake();
        $e = Episode::create(['user_id' => $this->user->id, 'series' => 'ai-post', 'kind' => 'image', 'run_date' => now()->toDateString(), 'status' => 'researching', 'platform_ids' => [$this->linkedin->id]]);

        $this->webhook(['episode_id' => $e->id, 'type' => 'research', 'status' => 'ok', 'script' => 'Caption'])->assertNoContent();

        $this->assertSame('failed', $e->fresh()->status);
        Queue::assertNothingPushed();
    }

    public function test_revised_image_replaces_the_previous_one(): void
    {
        Http::fake(['worker.test/*' => fn () => Http::response('PNG2')]);
        $old = Media::create(['user_id' => $this->user->id, 'filename' => 'old.png', 'original_filename' => 'old.png', 'mime_type' => 'image/png', 'size' => 3, 'path' => 'uploads/old.png', 'type' => 'image']);
        $e = Episode::create([
            'user_id' => $this->user->id, 'series' => 'ai-post', 'kind' => 'image', 'run_date' => now()->toDateString(),
            'status' => 'researching', 'platform_ids' => [$this->linkedin->id], 'outputs' => ['image' => $old->id],
        ]);

        (new ImportImageJob($e->id, 'https://worker.test/files/1/new.png', ['script' => 'v2']))->handle();

        $this->assertNull(Media::find($old->id));
        $this->assertSame(1, Media::count());
        $this->assertSame('v2', $e->fresh()->script_ur);
    }

    public function test_text_job_needs_a_future_publish_time_to_approve(): void
    {
        Queue::fake();
        $e = Episode::create([
            'user_id' => $this->user->id, 'series' => 'ai-post', 'kind' => 'text', 'run_date' => now()->toDateString(),
            'status' => 'awaiting_approval', 'script_ur' => 'x', 'platform_ids' => [$this->linkedin->id], 'scheduled_for' => now()->subMinute(),
        ]);

        $this->postJson("/api/episodes/{$e->id}/approve")->assertStatus(422);
        $this->assertSame('awaiting_approval', $e->fresh()->status);
        Queue::assertNotPushed(ScheduleEpisodePostsJob::class);
    }

    public function test_failed_text_import_retries_from_the_start_not_the_avatar_step(): void
    {
        Queue::fake();
        $e = Episode::create(['user_id' => $this->user->id, 'series' => 'ai-post', 'kind' => 'image', 'run_date' => now()->toDateString(), 'status' => 'failed', 'failed_step' => 'importing', 'platform_ids' => [$this->linkedin->id]]);

        $this->postJson("/api/episodes/{$e->id}/retry")->assertOk()->assertJsonPath('status', 'pending');
        Queue::assertPushed(StartResearchJob::class, 1);
    }
}
