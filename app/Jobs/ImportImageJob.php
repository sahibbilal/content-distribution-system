<?php

namespace App\Jobs;

use App\Models\Episode;
use App\Models\Media;
use App\Services\WorkerFileDownloader;
use App\Support\Notify;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * researching -> importing -> awaiting_approval for a text + image job: stores the text and the worker's image,
 * so the image is on the approval screen next to the text.
 */
class ImportImageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 600;

    /** @param array{topic?: ?string, script: string, sources?: array} $draft  the worker's text result */
    public function __construct(public int $episodeId, public string $imageUrl, public array $draft)
    {
    }

    public function handle(): void
    {
        $e = Episode::find($this->episodeId);
        if (!$e || !$e->transition('researching', 'importing', ['step_started_at' => now()])) {
            return; // duplicate webhook
        }

        $created = [];
        try {
            $image = WorkerFileDownloader::download($e, $this->imageUrl, 'image/png', 'png', $created);
        } catch (\Throwable $ex) {
            WorkerFileDownloader::discard($created);
            throw $ex;
        }

        // a revise makes a new image, so the previous one goes
        $previous = Media::where('user_id', $e->user_id)->whereKey($e->outputs['image'] ?? 0)->get()->all();
        WorkerFileDownloader::discard($previous);

        $e->transition('importing', 'awaiting_approval', [
            'topic' => $this->draft['topic'] ?? null,
            'script_ur' => $this->draft['script'],
            'sources' => $this->draft['sources'] ?? [],
            'outputs' => self::draftOutputs($e, $this->draft) + ['image' => $image->id],
            'error' => null,
        ]);
        $e->log('script', 'ok', 'Draft and image from the worker', ['source' => 'worker', 'script' => $this->draft['script']]);
        if ($e->series !== 'ai-post') { // a draft for the Create Post page is read right away, no email needed
            Notify::user($e->user, 'Post ready for approval', ($e->topic ?: 'A new post') . ' is waiting for your approval.', Notify::episodeLink($e->id));
        }
    }

    /** The episode's outputs plus the worker's per-channel versions and hashtags (kept, so the Create Post page can read them) */
    public static function draftOutputs(Episode $e, array $draft): array
    {
        return array_merge($e->outputs ?? [], array_filter([
            'versions' => $draft['versions'] ?? null,
            'hashtags' => $draft['hashtags'] ?? null,
            'hashtags_short' => $draft['hashtags_short'] ?? null,
        ], fn ($v) => $v !== null));
    }

    public function failed(\Throwable $ex): void
    {
        Episode::find($this->episodeId)?->fail('Image: ' . $ex->getMessage());
    }
}
