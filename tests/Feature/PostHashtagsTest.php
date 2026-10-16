<?php

namespace Tests\Feature;

use App\Jobs\PublishPostJob;
use App\Models\Media;
use App\Models\Platform;
use App\Models\Post;
use App\Models\User;
use App\Support\PostText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Hashtags, a different text per channel, image-only posts and the X length limit */
class PostHashtagsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Platform $x;
    private Platform $linkedin;
    private Platform $instagram;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create(['name' => 'Bilal', 'email' => 'b@example.com', 'password' => 'password123']);
        $this->user->forceFill(['email_verified_at' => now()])->save();
        Sanctum::actingAs($this->user);

        $make = fn (string $type, array $credentials = ['t' => 1]) => Platform::create(['user_id' => $this->user->id, 'platform_type' => $type, 'credentials' => $credentials, 'is_active' => true]);
        $this->x = $make('twitter', ['access_token' => 'x']);
        $this->linkedin = $make('linkedin');
        $this->instagram = $make('instagram');
    }

    private function future(): string
    {
        return now()->addDay()->toIso8601String();
    }

    private function image(): Media
    {
        return Media::create(['user_id' => $this->user->id, 'filename' => 'a.jpg', 'original_filename' => 'a.jpg', 'mime_type' => 'image/jpeg', 'size' => 10, 'path' => 'uploads/a.jpg', 'type' => 'image']);
    }

    // ---- text composition

    public function test_hashtags_are_cleaned_and_appended_after_a_blank_line(): void
    {
        $this->assertSame(['AI', 'laravel', 'اردو'], PostText::cleanHashtags(['#AI', ' ai ', 'lara vel', '', '##اردو']));
        $this->assertSame("Hello\n\n#AI #php", PostText::compose('Hello', ['AI', '#php']));
        $this->assertSame('#AI', PostText::compose('', ['AI']));
        $this->assertSame('Hello', PostText::compose('Hello', []));
    }

    public function test_a_channel_can_have_its_own_text_and_hashtags_including_none(): void
    {
        $overrides = ['7' => ['content' => 'short', 'hashtags' => []], '8' => ['hashtags' => ['x']]];

        $this->assertSame("long\n\n#a #b", PostText::forPlatform('long', ['a', 'b'], $overrides, 1));
        $this->assertSame('short', PostText::forPlatform('long', ['a', 'b'], $overrides, 7));
        $this->assertSame("long\n\n#x", PostText::forPlatform('long', ['a', 'b'], $overrides, 8));
    }

    // ---- saving

    public function test_post_keeps_hashtags_and_only_the_overrides_of_selected_channels(): void
    {
        $res = $this->postJson('/api/posts', [
            'content' => 'A long post for LinkedIn',
            'hashtags' => ['#AI', 'ai', 'Laravel'],
            'platforms' => [$this->linkedin->id, $this->x->id],
            'platform_overrides' => [
                $this->x->id => ['content' => 'Short for X', 'hashtags' => ['AI']],
                $this->instagram->id => ['content' => 'not selected'],
            ],
            'scheduled_at' => $this->future(),
        ])->assertCreated();

        $post = Post::findOrFail($res->json('id'));
        $this->assertSame(['AI', 'Laravel'], $post->hashtags);
        $this->assertSame([(string) $this->x->id => ['content' => 'Short for X', 'hashtags' => ['AI']]], $post->platform_overrides);
    }

    // ---- X limit

    public function test_x_text_with_hashtags_over_280_is_refused_and_a_short_version_fixes_it(): void
    {
        $long = str_repeat('a', 270);
        $body = ['content' => $long, 'hashtags' => ['AIAIAIAI'], 'platforms' => [$this->x->id], 'scheduled_at' => $this->future()];

        $this->postJson('/api/posts', $body)->assertStatus(422)
            ->assertJsonFragment(['message' => 'X: the text is 281 characters and the limit is 280 (spaces and hashtags count). Write a shorter version for X.']);

        $this->postJson('/api/posts', $body + ['platform_overrides' => [$this->x->id => ['content' => 'Short one']]] )->assertCreated();
    }

    public function test_a_premium_x_account_may_post_longer(): void
    {
        $this->x->credentials = ['access_token' => 'x', 'account_tier' => 'premium'];
        $this->x->save();

        $this->postJson('/api/posts', ['content' => str_repeat('a', 1000), 'platforms' => [$this->x->id], 'scheduled_at' => $this->future()])->assertCreated();
    }

    public function test_x_account_type_can_be_changed_and_is_listed(): void
    {
        $this->patchJson('/api/platforms/twitter/settings', ['account_tier' => 'premium'])->assertOk();
        $this->patchJson('/api/platforms/twitter/settings', ['account_tier' => 'gold'])->assertStatus(422);
        $this->patchJson('/api/platforms/linkedin/settings', ['account_tier' => 'premium'])->assertNotFound();

        $listed = collect($this->getJson('/api/platforms')->json())->firstWhere('platform_type', 'twitter');
        $this->assertSame('premium', $listed['account_tier']);
        $this->assertSame('x', $this->x->fresh()->credentials['access_token'], 'the connection itself is untouched');
    }

    // ---- image only

    public function test_an_image_alone_can_be_posted_without_any_text(): void
    {
        $media = $this->image();

        $this->postJson('/api/posts', ['content' => '', 'platforms' => [$this->instagram->id], 'media' => [$media->id], 'scheduled_at' => $this->future()])
            ->assertCreated()->assertJsonPath('content', '');
    }

    public function test_a_post_with_no_text_and_no_file_is_refused(): void
    {
        $this->postJson('/api/posts', ['content' => '  ', 'platforms' => [$this->linkedin->id], 'scheduled_at' => $this->future()])
            ->assertStatus(422)->assertJsonFragment(['message' => 'Add some text or a file to post.']);
    }

    // ---- publishing

    public function test_each_channel_receives_its_own_text_and_hashtags(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['id' => '1_2']),
            'api.twitter.com/*' => Http::response(['data' => ['id' => '9']]),
        ]);
        $page = Platform::create(['user_id' => $this->user->id, 'platform_type' => 'facebook', 'credentials' => ['access_token' => 't', 'page_id' => 'A'], 'is_active' => true]);
        $post = Post::create([
            'user_id' => $this->user->id, 'content' => 'Long text', 'hashtags' => ['one', 'two'],
            'platform_overrides' => [(string) $this->x->id => ['content' => 'Short text', 'hashtags' => ['three']]],
            'platforms' => [$page->id, $this->x->id], 'status' => 'scheduled',
        ]);

        (new PublishPostJob($post))->handle();

        Http::assertSent(fn ($r) => str_contains($r->url(), '/A/feed') && $r['message'] === "Long text\n\n#one #two");
        Http::assertSent(fn ($r) => str_contains($r->url(), '/tweets') && $r['text'] === "Short text\n\n#three");
    }
}
