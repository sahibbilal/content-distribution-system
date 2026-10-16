<?php

namespace Tests\Feature;

use App\Models\Platform;
use App\Models\Post;
use App\Models\PostSeries;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Series of posts (counts, last two) and refusing the same content twice */
class PostSeriesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Platform $linkedin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create(['name' => 'Bilal', 'email' => 'b@example.com', 'password' => 'password123']);
        $this->user->forceFill(['email_verified_at' => now()])->save();
        Sanctum::actingAs($this->user);
        $this->linkedin = Platform::create(['user_id' => $this->user->id, 'platform_type' => 'linkedin', 'credentials' => ['t' => 1], 'is_active' => true]);
    }

    private function future(): string
    {
        return now()->addDay()->toIso8601String();
    }

    private function schedule(string $content, ?int $series = null)
    {
        return $this->postJson('/api/posts', ['content' => $content, 'platforms' => [$this->linkedin->id], 'series_id' => $series, 'scheduled_at' => $this->future()]);
    }

    private function published(PostSeries $series, string $content, string $when): Post
    {
        return Post::create(['user_id' => $this->user->id, 'series_id' => $series->id, 'content' => $content, 'platforms' => [], 'status' => 'published', 'published_at' => $when]);
    }

    public function test_series_are_created_renamed_deleted_and_names_are_unique_per_user(): void
    {
        $id = $this->postJson('/api/post-series', ['name' => ' GKMBA series '])->assertCreated()->assertJsonPath('name', 'GKMBA series')->json('id');
        $this->postJson('/api/post-series', ['name' => 'GKMBA series'])->assertStatus(422);
        $this->putJson("/api/post-series/{$id}", ['name' => 'SiteCliniQ'])->assertOk()->assertJsonPath('name', 'SiteCliniQ');

        $other = User::create(['name' => 'O', 'email' => 'o@example.com', 'password' => 'password123']);
        $foreign = PostSeries::create(['user_id' => $other->id, 'name' => 'Theirs']);
        $this->putJson("/api/post-series/{$foreign->id}", ['name' => 'Mine now'])->assertNotFound();
        $this->schedule('Hello there', $foreign->id)->assertStatus(422); // not their series

        $this->deleteJson("/api/post-series/{$id}")->assertOk();
        $this->assertDatabaseMissing('post_series', ['id' => $id]);
    }

    public function test_a_series_counts_its_posts_and_shows_the_last_two(): void
    {
        $series = PostSeries::create(['user_id' => $this->user->id, 'name' => 'GKMBA']);
        $this->published($series, 'Part 1', '2026-10-01 10:00:00');
        $this->published($series, 'Part 2', '2026-10-05 10:00:00');
        $this->published($series, 'Part 3', '2026-10-09 10:00:00');
        $this->schedule('Part 4', $series->id)->assertCreated()->assertJsonPath('series_id', $series->id);

        $row = collect($this->getJson('/api/post-series')->assertOk()->json())->firstWhere('name', 'GKMBA');

        $this->assertSame(3, $row['published_count']);
        $this->assertSame(1, $row['scheduled_count']);
        $this->assertSame(['Part 3', 'Part 2'], array_column($row['latest'], 'content'));
    }

    public function test_posts_can_be_filtered_by_series(): void
    {
        $a = PostSeries::create(['user_id' => $this->user->id, 'name' => 'A']);
        $this->schedule('In A', $a->id)->assertCreated();
        $this->schedule('In none')->assertCreated();

        $this->assertSame(['In A'], array_column($this->getJson("/api/posts?series_id={$a->id}")->json('data'), 'content'));
    }

    public function test_the_same_content_cannot_be_posted_twice(): void
    {
        $this->schedule('We shipped version one!')->assertCreated();

        $this->schedule("  we SHIPPED   version one! ")->assertStatus(422)
            ->assertJsonFragment(['duplicate_post_id' => Post::first()->id])
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'already'));
        $this->schedule('We shipped version two!')->assertCreated();
        $this->assertSame(2, Post::count());
    }

    public function test_editing_a_scheduled_post_to_copy_another_is_refused_but_saving_itself_is_fine(): void
    {
        $a = $this->schedule('First text')->json('id');
        $b = $this->schedule('Second text')->json('id');
        $body = fn ($text) => ['content' => $text, 'platforms' => [$this->linkedin->id], 'scheduled_at' => $this->future()];

        $this->putJson("/api/posts/{$a}", $body('First text'))->assertOk();
        $this->putJson("/api/posts/{$b}", $body('first TEXT'))->assertStatus(422);
    }

    public function test_media_only_posts_and_failed_posts_do_not_count_as_duplicates(): void
    {
        Post::create(['user_id' => $this->user->id, 'content' => 'Tried and failed', 'platforms' => [], 'status' => 'failed']);

        $this->schedule('Tried and failed')->assertCreated();
        $this->assertNull(Post::hashContent('   '));
    }
}
