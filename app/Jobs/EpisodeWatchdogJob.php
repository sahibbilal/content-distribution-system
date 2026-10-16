<?php

namespace App\Jobs;

use App\Models\Episode;
use App\Models\Post;
use App\Support\Notify;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Every 15 minutes: fails stuck steps, skips days that will not make it, and closes out published episodes */
class EpisodeWatchdogJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Statuses where the day can still be skipped: nothing has been scheduled yet */
    private const BEFORE_SCHEDULED = [
        'pending', 'researching', 'awaiting_approval', 'approved', 'voicing',
        'awaiting_avatar', 'editing', 'importing', 'ready', 'scheduling',
    ];

    public function handle(): void
    {
        $this->failStuckSteps();
        $this->skipLateEpisodes();
        $this->closePublished();
    }

    private function failStuckSteps(): void
    {
        $limits = config('series.a.limits') + ['importing' => 30, 'scheduling' => 10];

        foreach ($limits as $status => $minutes) {
            Episode::where('status', $status)
                ->where('step_started_at', '<', now()->subMinutes($minutes))
                ->each(fn (Episode $e) => $e->fail("Timed out: stuck in {$status} for over {$minutes} minutes."));
        }
    }

    private function skipLateEpisodes(): void
    {
        $cutoff = now()->addMinutes(config('series.a.skip_before_minutes'));

        Episode::whereIn('status', self::BEFORE_SCHEDULED)->where('scheduled_for', '<=', $cutoff)->each(function (Episode $e) {
            $was = $e->status;
            if ($e->transition($was, 'skipped', ['error' => "Not ready in time (was {$was}). Nothing was posted."])) {
                $e->log($was, 'failed', 'Skipped: not ready before the publish time.');
                Notify::user($e->user, "Episode {$e->run_date->toDateString()} skipped", "It was still in '{$was}' near the publish time, so nothing was posted today.", Notify::episodeLink($e->id));
            }
        });
    }

    /** scheduled -> published once none of its posts is waiting any more */
    private function closePublished(): void
    {
        Episode::where('status', 'scheduled')->where('scheduled_for', '<=', now())->each(function (Episode $e) {
            $posts = Post::whereIn('id', $e->post_ids ?? [])->get();
            if ($posts->contains('status', 'scheduled')) {
                return; // still waiting for the scheduler
            }

            if ($e->transition('scheduled', 'published')) {
                EpisodeSummaryJob::dispatch($e->id);
            }
        });
    }
}
