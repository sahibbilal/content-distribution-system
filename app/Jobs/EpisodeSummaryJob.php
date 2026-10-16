<?php

namespace App\Jobs;

use App\Models\Episode;
use App\Models\Platform;
use App\Models\Post;
use App\Support\Notify;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** After publishing: one email with the result and the links of every post */
class EpisodeSummaryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $episodeId)
    {
    }

    public function handle(): void
    {
        $e = Episode::with('user')->find($this->episodeId);
        if (!$e) {
            return;
        }

        $types = Platform::where('user_id', $e->user_id)->pluck('platform_type', 'id');
        $lines = [];
        foreach (Post::whereIn('id', $e->post_ids ?? [])->get() as $post) {
            foreach ($post->platform_statuses ?? [] as $platformId => $s) {
                $lines[] = sprintf('%s - %s: %s%s', strtok($post->content, "\n"), $types[$platformId] ?? $platformId, $s['status'] ?? '?', !empty($s['url']) ? ' ' . $s['url'] : (!empty($s['message']) && ($s['status'] ?? '') === 'failed' ? ' (' . $s['message'] . ')' : ''));
            }
        }

        Notify::user($e->user, "Episode {$e->run_date->toDateString()} published", implode("\n", $lines) ?: 'No results were recorded.', Notify::episodeLink($e->id));
    }
}
