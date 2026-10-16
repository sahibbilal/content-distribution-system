<?php

namespace Tests\Feature;

use App\Jobs\StartResearchJob;
use App\Models\AiIntegration;
use App\Models\Episode;
use App\Models\Media;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** "Ask AI" on the Create Post page: drafts, the short X version, hashtags, image checks and image making */
class AiDraftTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Platform $x;
    private Platform $linkedin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->user = User::create(['name' => 'Bilal', 'email' => 'b@example.com', 'password' => 'password123']);
        $this->user->forceFill(['email_verified_at' => now()])->save();
        Sanctum::actingAs($this->user);

        $this->x = Platform::create(['user_id' => $this->user->id, 'platform_type' => 'twitter', 'credentials' => ['access_token' => 'x'], 'is_active' => true]);
        $this->linkedin = Platform::create(['user_id' => $this->user->id, 'platform_type' => 'linkedin', 'credentials' => ['t' => 1], 'is_active' => true]);
        AiIntegration::create(['provider' => 'anthropic', 'api_key' => 'sk-test', 'enabled' => true]);
    }

    private function answer(array $json)
    {
        return Http::response(['content' => [['type' => 'text', 'text' => "Here you go:\n" . json_encode($json)]]]);
    }

    public function test_it_needs_an_enabled_anthropic_key(): void
    {
        AiIntegration::where('provider', 'anthropic')->update(['enabled' => false]);

        $this->postJson('/api/ai/draft', ['prompt' => 'x'])->assertStatus(422)->assertJsonFragment(['message' => 'Connect and enable Anthropic in Admin > AI integrations first, or choose the Claude worker.']);
    }

    public function test_it_returns_a_long_and_a_separate_short_text_with_hashtags(): void
    {
        Http::fake(['api.anthropic.com/*' => $this->answer([
            'long' => 'A long post about Laravel 12.', 'short' => 'Laravel 12 is out.',
            'hashtags' => ['Laravel', '#PHP', 'Laravel'], 'hashtags_short' => ['Laravel'], 'image_prompt' => null, 'image_check' => null,
        ])]);

        $res = $this->postJson('/api/ai/draft', ['prompt' => 'Write about Laravel 12', 'platforms' => [$this->x->id, $this->linkedin->id], 'hashtags' => true])->assertOk();

        $res->assertJsonPath('versions.long', 'A long post about Laravel 12.')
            ->assertJsonPath('versions.short', 'Laravel 12 is out.')
            ->assertJsonPath('hashtags', ['Laravel', 'PHP'])
            ->assertJsonPath('hashtags_short', ['Laravel']);
        Http::assertSent(fn ($r) => str_contains($r['system'], 'at most 280 characters') && $r->header('x-api-key')[0] === 'sk-test');
    }

    public function test_an_x_text_that_is_too_long_with_its_hashtags_is_asked_again_once(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::sequence()
            ->push(['content' => [['type' => 'text', 'text' => json_encode(['long' => 'L', 'short' => str_repeat('a', 290), 'hashtags' => [], 'hashtags_short' => []])]]])
            ->push(['content' => [['type' => 'text', 'text' => json_encode(['long' => 'L', 'short' => str_repeat('a', 200), 'hashtags' => [], 'hashtags_short' => []])]]])]);

        $res = $this->postJson('/api/ai/draft', ['prompt' => 'x', 'platforms' => [$this->x->id]])->assertOk();

        $this->assertSame(200, mb_strlen($res->json('versions.short')));
        Http::assertSentCount(2);
        Http::assertSent(fn ($r) => str_contains(json_encode($r['messages']), 'characters too long'));
    }

    public function test_without_x_there_is_no_short_text_and_without_hashtags_none_are_returned(): void
    {
        Http::fake(['api.anthropic.com/*' => $this->answer(['long' => 'Only long', 'short' => null, 'hashtags' => [], 'hashtags_short' => []])]);

        $this->postJson('/api/ai/draft', ['prompt' => 'x', 'platforms' => [$this->linkedin->id]])->assertOk()
            ->assertJsonPath('versions.short', null)->assertJsonPath('hashtags', []);
    }

    public function test_attached_images_are_sent_to_the_ai_and_its_image_check_comes_back(): void
    {
        Storage::disk('public')->put('uploads/a.jpg', 'JPEGDATA');
        $media = Media::create(['user_id' => $this->user->id, 'filename' => 'a.jpg', 'original_filename' => 'a.jpg', 'mime_type' => 'image/jpeg', 'size' => 8, 'path' => 'uploads/a.jpg', 'type' => 'image']);
        Http::fake(['api.anthropic.com/*' => $this->answer(['long' => 'Caption', 'short' => null, 'hashtags' => [], 'hashtags_short' => [], 'image_check' => ['ok' => false, 'note' => 'The picture is blurry.']])]);

        $this->postJson('/api/ai/draft', ['prompt' => 'Caption this', 'platforms' => [$this->linkedin->id], 'media' => [$media->id]])->assertOk()
            ->assertJsonPath('image_check.ok', false)->assertJsonPath('image_check.note', 'The picture is blurry.');

        Http::assertSent(fn ($r) => collect($r['messages'][0]['content'])->contains(fn ($b) => ($b['type'] ?? '') === 'image' && $b['source']['data'] === base64_encode('JPEGDATA')));
    }

    public function test_it_can_make_an_image_and_a_failed_image_does_not_lose_the_text(): void
    {
        AiIntegration::create(['provider' => 'openai', 'api_key' => 'sk-oa', 'model' => 'gpt-image-1', 'enabled' => true]);
        Http::fake([
            'api.anthropic.com/*' => $this->answer(['long' => 'Text', 'short' => null, 'hashtags' => [], 'hashtags_short' => [], 'image_prompt' => 'A red rocket']),
            'api.openai.com/*' => Http::response(['data' => [['b64_json' => base64_encode('PNGDATA')]]]),
        ]);

        $res = $this->postJson('/api/ai/draft', ['prompt' => 'x', 'platforms' => [$this->linkedin->id], 'make_image' => true])->assertOk();

        $this->assertNotNull($res->json('image.id'));
        $this->assertSame('PNGDATA', Storage::disk('public')->get(Media::find($res->json('image.id'))->path));
        Http::assertSent(fn ($r) => str_contains($r->url(), 'openai.com') && $r['prompt'] === 'A red rocket' && $r['model'] === 'gpt-image-1');

        AiIntegration::where('provider', 'openai')->update(['enabled' => false]);
        $this->postJson('/api/ai/draft', ['prompt' => 'x', 'platforms' => [$this->linkedin->id], 'make_image' => true])->assertOk()
            ->assertJsonPath('versions.long', 'Text')->assertJsonPath('image', null)->assertJsonPath('image_error', fn ($m) => str_contains($m, 'OpenAI'));
    }

    public function test_the_worker_engine_starts_an_episode_and_its_result_is_read_back_once_ready(): void
    {
        Queue::fake();
        config(['services.worker.url' => 'https://worker.test']);

        $start = $this->postJson('/api/ai/draft', ['prompt' => 'Laravel news', 'engine' => 'worker', 'platforms' => [$this->x->id, $this->linkedin->id], 'hashtags' => true])->assertStatus(202);
        $e = Episode::findOrFail($start->json('episode_id'));
        Queue::assertPushed(StartResearchJob::class, 1);
        $this->assertSame(280, $e->outputs['options']['x_limit']);
        $this->getJson("/api/ai/draft/{$e->id}")->assertJsonPath('status', 'working');

        // what the worker's webhook stores
        $e->forceFill(['status' => 'awaiting_approval', 'script_ur' => 'Long', 'outputs' => $e->outputs + ['versions' => ['long' => 'Long', 'short' => 'Short'], 'hashtags' => ['a'], 'hashtags_short' => ['b']]])->save();

        $this->getJson("/api/ai/draft/{$e->id}")->assertJsonPath('status', 'ready')->assertJsonPath('versions.short', 'Short')->assertJsonPath('hashtags_short', ['b']);
        $this->assertSame('cancelled', $e->fresh()->status, 'closed once read, so it does not wait for an approval');
        $this->getJson("/api/ai/draft/{$e->id}")->assertJsonPath('status', 'ready');
    }

    public function test_the_worker_engine_refuses_when_no_worker_is_set_up(): void
    {
        config(['services.worker.url' => null]);

        $this->postJson('/api/ai/draft', ['prompt' => 'x', 'engine' => 'worker'])->assertStatus(422);
    }

    public function test_another_users_draft_cannot_be_read(): void
    {
        $other = User::create(['name' => 'O', 'email' => 'o@example.com', 'password' => 'password123']);
        $e = Episode::create(['user_id' => $other->id, 'series' => 'ai-post', 'kind' => 'text', 'run_date' => now()->toDateString(), 'status' => 'awaiting_approval']);

        $this->getJson("/api/ai/draft/{$e->id}")->assertNotFound();
    }
}
