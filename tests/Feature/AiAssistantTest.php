<?php

namespace Tests\Feature;

use App\Models\AiGeneration;
use App\Models\AiIntegration;
use App\Models\AiProject;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** The editable rulebook, projects and memory behind "Ask AI" */
class AiAssistantTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create(['name' => 'Bilal', 'email' => 'b@example.com', 'password' => 'password123']);
        $this->user->forceFill(['email_verified_at' => now()])->save();
        Sanctum::actingAs($this->user);
    }

    private function sections()
    {
        return collect($this->getJson('/api/ai/assistant')->assertOk()->json('sections'))->keyBy('key');
    }

    public function test_the_rulebook_starts_with_every_section_and_two_starter_projects(): void
    {
        $res = $this->getJson('/api/ai/assistant')->assertOk();

        $this->assertSame(['general', 'format', 'remember', 'images', 'script', 'video', 'pdf'], collect($res->json('sections'))->pluck('key')->all());
        $this->assertSame(['Latest trends', 'Jokes'], collect($res->json('projects'))->pluck('name')->all());
        $this->assertFalse($this->sections()['general']['edited']);
    }

    public function test_starter_projects_are_created_once_even_after_they_are_deleted(): void
    {
        foreach ($this->getJson('/api/ai/assistant')->json('projects') as $p) {
            $this->deleteJson("/api/ai/projects/{$p['id']}")->assertOk();
        }

        $this->assertSame([], $this->getJson('/api/ai/assistant')->json('projects'));
    }

    public function test_a_section_can_be_edited_and_reset_to_the_starter_text(): void
    {
        $this->putJson('/api/ai/instructions/remember', ['body' => 'About me: a cook.'])->assertOk();

        $this->assertSame('About me: a cook.', $this->sections()['remember']['body']);
        $this->assertTrue($this->sections()['remember']['edited']);

        $this->deleteJson('/api/ai/instructions/remember')->assertOk();
        $this->assertFalse($this->sections()['remember']['edited']);
        $this->assertStringContainsString('About me:', $this->sections()['remember']['body']);

        $this->putJson('/api/ai/instructions/nonsense', ['body' => 'x'])->assertNotFound();
        $this->putJson('/api/ai/instructions/general', ['body' => ''])->assertStatus(422);
    }

    public function test_rulebooks_are_private_to_each_user(): void
    {
        $this->putJson('/api/ai/instructions/remember', ['body' => 'Mine'])->assertOk();
        Sanctum::actingAs(User::create(['name' => 'O', 'email' => 'o@example.com', 'password' => 'password123']));

        $this->assertStringNotContainsString('Mine', $this->sections()['remember']['body']);
    }

    public function test_projects_can_be_created_edited_and_only_with_own_platforms(): void
    {
        $mine = Platform::create(['user_id' => $this->user->id, 'platform_type' => 'linkedin', 'credentials' => ['t' => 1], 'is_active' => true]);
        $other = User::create(['name' => 'O', 'email' => 'o@example.com', 'password' => 'password123']);
        $theirs = Platform::create(['user_id' => $other->id, 'platform_type' => 'linkedin', 'credentials' => ['t' => 1], 'is_active' => true]);

        $id = $this->postJson('/api/ai/projects', [
            'name' => 'Tips', 'type' => 'custom', 'instructions' => 'Daily tip', 'platform_ids' => [$mine->id, $theirs->id], 'options' => ['hashtags' => true],
        ])->assertCreated()->assertJsonPath('platform_ids', [$mine->id])->assertJsonPath('options.hashtags', true)->assertJsonPath('options.web_search', false)->json('id');

        $this->putJson("/api/ai/projects/{$id}", ['name' => 'Daily tips', 'type' => 'custom'])->assertOk()->assertJsonPath('name', 'Daily tips');

        Sanctum::actingAs($other);
        $this->putJson("/api/ai/projects/{$id}", ['name' => 'Hijack', 'type' => 'custom'])->assertNotFound();
        $this->deleteJson("/api/ai/projects/{$id}")->assertNotFound();
    }

    public function test_the_drafts_system_prompt_holds_the_rulebook_the_project_and_its_memory(): void
    {
        AiIntegration::create(['provider' => 'anthropic', 'api_key' => 'sk-test', 'enabled' => true]);
        $this->putJson('/api/ai/instructions/remember', ['body' => 'My voice: very formal.']);
        $project = AiProject::create(['user_id' => $this->user->id, 'name' => 'Jokes', 'type' => 'jokes', 'instructions' => 'Write clean jokes only.']);
        AiGeneration::record($this->user->id, $project->id, 'earlier', "Why do programmers prefer dark mode?\nBecause light attracts bugs.", null, []);
        Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => json_encode(['long' => "A new joke\nPunchline", 'short' => null, 'hashtags' => [], 'hashtags_short' => []])]]])]);

        $res = $this->postJson('/api/ai/draft', ['prompt' => 'one joke', 'project_id' => $project->id])->assertOk();

        Http::assertSent(function ($r) {
            $s = $r['system'];

            return str_contains($s, 'My voice: very formal.')            // the edited section
                && str_contains($s, 'Follow these steps for every request') // a starter section
                && str_contains($s, 'Write clean jokes only.')             // the project
                && str_contains($s, 'Why do programmers prefer dark mode?') // memory
                && !str_contains($s, 'Made images:');                       // image rules only when an image is wanted
        });

        // and the new draft is remembered, ready for the next request
        $this->assertSame('A new joke', AiGeneration::findOrFail($res->json('generation_id'))->hook);
        $this->assertSame($project->id, AiGeneration::findOrFail($res->json('generation_id'))->project_id);
    }

    public function test_only_the_instructions_picked_for_the_chat_are_followed(): void
    {
        AiIntegration::create(['provider' => 'anthropic', 'api_key' => 'sk-test', 'enabled' => true]);
        $this->putJson('/api/ai/instructions/remember', ['body' => 'My voice: very formal.']);
        Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => json_encode(['long' => 'x', 'short' => null, 'hashtags' => [], 'hashtags_short' => []])]]])]);

        $this->postJson('/api/ai/draft', ['prompt' => 'x', 'instructions' => ['general']])->assertOk();

        Http::assertSent(fn ($r) => str_contains($r['system'], 'Follow these steps for every request') && !str_contains($r['system'], 'My voice: very formal.'));
    }

    public function test_image_rules_are_added_when_an_image_is_wanted(): void
    {
        AiIntegration::create(['provider' => 'anthropic', 'api_key' => 'sk-test', 'enabled' => true]);
        Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => json_encode(['long' => 'x', 'short' => null, 'hashtags' => [], 'hashtags_short' => []])]]])]);

        $this->postJson('/api/ai/draft', ['prompt' => 'x', 'make_image' => true])->assertOk();

        Http::assertSent(fn ($r) => str_contains($r['system'], 'Made images:'));
    }

    public function test_memory_lists_clears_and_marks_the_drafts_that_became_posts(): void
    {
        $project = AiProject::create(['user_id' => $this->user->id, 'name' => 'Trends', 'type' => 'trends']);
        $used = AiGeneration::record($this->user->id, $project->id, 'p', 'First idea', null, []);
        AiGeneration::record($this->user->id, $project->id, 'p', 'Second idea', null, []);
        $platform = Platform::create(['user_id' => $this->user->id, 'platform_type' => 'linkedin', 'credentials' => ['t' => 1], 'is_active' => true]);

        $post = $this->postJson('/api/posts', [
            'content' => 'First idea', 'platforms' => [$platform->id], 'scheduled_at' => now()->addDay()->toIso8601String(), 'ai_generation_id' => $used->id,
        ])->assertCreated()->json('id');

        $memory = $this->getJson("/api/ai/projects/{$project->id}/memory")->assertOk();
        $this->assertSame(['Second idea', 'First idea'], collect($memory->json())->pluck('hook')->all());
        $this->assertSame([false, true], collect($memory->json())->pluck('posted')->all());
        $this->assertSame($post, $used->fresh()->post_id);

        $this->deleteJson("/api/ai/projects/{$project->id}/memory")->assertOk();
        $this->assertSame([], $this->getJson("/api/ai/projects/{$project->id}/memory")->json());
    }

    public function test_a_draft_cannot_use_another_users_project_or_link_their_memory(): void
    {
        $other = User::create(['name' => 'O', 'email' => 'o@example.com', 'password' => 'password123']);
        $theirs = AiProject::create(['user_id' => $other->id, 'name' => 'Theirs', 'type' => 'custom']);
        $theirMemory = AiGeneration::record($other->id, $theirs->id, 'p', 'secret', null, []);
        $platform = Platform::create(['user_id' => $this->user->id, 'platform_type' => 'linkedin', 'credentials' => ['t' => 1], 'is_active' => true]);

        $this->postJson('/api/ai/draft', ['prompt' => 'x', 'project_id' => $theirs->id])->assertStatus(422);
        $this->postJson('/api/posts', ['content' => 'x', 'platforms' => [$platform->id], 'scheduled_at' => now()->addDay()->toIso8601String(), 'ai_generation_id' => $theirMemory->id])->assertCreated();
        $this->assertNull($theirMemory->fresh()->post_id);
    }
}
