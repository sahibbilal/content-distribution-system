<?php

namespace App\Jobs;

use App\Models\Episode;
use App\Models\Media;
use App\Models\Platform;
use App\Models\Post;
use App\Support\PlatformMediaRules;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * ready -> scheduling -> scheduled: after the final approval, creates the posts in the existing scheduler.
 * No publishing code changes: the posts are normal scheduled posts and PublishPostJob handles them.
 */
class ScheduleEpisodePostsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public int $episodeId)
    {
    }

    public function handle(): void
    {
        $e = Episode::find($this->episodeId);
        if (!$e || !$e->scheduled_for || !$e->transition('ready', 'scheduling', ['error' => null, 'step_started_at' => now()])) {
            return; // claiming the episode first stops a duplicate job from creating the posts twice
        }
        if ($e->scheduled_for->lte(now())) {
            throw new \RuntimeException('The publish time has already passed. Pick a new time and approve again.');
        }

        $postIds = DB::transaction(function () use ($e) {
            $ids = [];
            foreach ($this->plan($e) as $item) {
                // only platforms that are connected; one that isn't is skipped rather than failing the episode
                $platformIds = Platform::where('user_id', $e->user_id)->where('is_active', true)
                    ->when(isset($item['types']), fn ($q) => $q->whereIn('platform_type', $item['types']))
                    ->when(isset($item['platform_ids']), fn ($q) => $q->whereIn('id', $item['platform_ids']))
                    ->pluck('id')->all();
                if (!$platformIds) {
                    continue;
                }

                $problems = PlatformMediaRules::forPost($e->user_id, $platformIds, array_filter([$item['media']]));
                if ($problems) {
                    throw new \RuntimeException(implode(' ', collect($problems)->flatten()->all()));
                }

                $ids[] = $this->createPost($e, $platformIds, $item)->id;
            }

            return $ids;
        });

        if (!$postIds) {
            throw new \RuntimeException('No connected platform to publish to. Connect the platforms first.');
        }

        $e->transition('scheduling', 'scheduled', ['post_ids' => $postIds, 'error' => null]);
        $e->log('schedule', 'ok', count($postIds) . ' posts for ' . $e->scheduled_for->toDateTimeString() . ' UTC');
    }

    /** The posts to create: one per video for a video episode, a single one for a text or image job */
    private function plan(Episode $e): array
    {
        $o = $e->outputs ?? [];

        if (!$e->isVideo()) {
            return [['media' => $o['image'] ?? null, 'platform_ids' => $e->platform_ids ?? [], 'title' => null, 'description' => $e->script_ur, 'thumbnail' => null]];
        }

        $config = config('series.a.platforms');
        $plan = [['media' => $o['long'], 'types' => $config['long'], 'title' => $o['titles']['long'] ?? null, 'description' => $o['descriptions']['long'] ?? null, 'thumbnail' => $o['thumbnail'] ?? null]];
        foreach ($o['shorts'] as $i => $mediaId) {
            $plan[] = ['media' => $mediaId, 'types' => $config['short'], 'title' => $o['titles']['shorts'][$i] ?? null, 'description' => $o['descriptions']['shorts'][$i] ?? null, 'thumbnail' => null];
        }

        return $plan;
    }

    private function createPost(Episode $e, array $platformIds, array $item): Post
    {
        $thumbnailOk = $item['thumbnail'] && ($t = Media::find($item['thumbnail']))
            && in_array($t->mime_type, ['image/jpeg', 'image/png'], true) && $t->size <= 2 * 1024 * 1024;

        // a video's first line becomes the title on platforms that have one (YouTube); a text post is just its text
        $content = $e->isVideo()
            ? trim(($item['title'] ?? $e->topic ?? 'Episode') . "\n\n" . ($item['description'] ?? ''))
            : trim((string) $item['description']);

        $post = Post::create([
            'user_id' => $e->user_id,
            'content' => $content,
            'platforms' => $platformIds,
            'status' => 'scheduled',
            'scheduled_at' => $e->scheduled_for,
            'thumbnail_media_id' => $thumbnailOk ? $item['thumbnail'] : null,
        ]);
        if ($item['media']) {
            Media::whereKey($item['media'])->update(['post_id' => $post->id]);
        }
        $post->schedules()->create(['user_id' => $e->user_id, 'scheduled_at' => $e->scheduled_for, 'status' => 'pending']);

        return $post;
    }

    public function failed(\Throwable $ex): void
    {
        Episode::find($this->episodeId)?->fail('Schedule: ' . $ex->getMessage());
    }
}
