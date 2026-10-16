<?php

namespace App\Jobs;

use App\Models\Episode;
use App\Services\WorkerFileDownloader;
use App\Support\Notify;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * editing -> importing -> ready: downloads the worker's videos and thumbnail into the media library.
 * Posts are NOT created here: the episode waits for the final approval first.
 */
class ImportOutputsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 1800;

    /** @param array{long: string, shorts?: array, thumbnail?: string} $files  worker download URLs */
    public function __construct(public int $episodeId, public array $files, public array $meta)
    {
    }

    public function handle(): void
    {
        $e = Episode::find($this->episodeId);
        // claiming the episode first means a duplicate webhook can't download everything twice
        if (!$e || !$e->transition('editing', 'importing', ['step_started_at' => now()])) {
            return;
        }
        $e->log('import', 'started');

        $created = [];
        try {
            $outputs = [
                'long' => WorkerFileDownloader::download($e, $this->files['long'] ?? null, 'video/mp4', 'mp4', $created)->id,
                'shorts' => [],
                'thumbnail' => null,
                'titles' => $this->meta['titles'] ?? [],
                'descriptions' => $this->meta['descriptions'] ?? [],
            ];
            foreach ($this->files['shorts'] ?? [] as $url) {
                $outputs['shorts'][] = WorkerFileDownloader::download($e, $url, 'video/mp4', 'mp4', $created)->id;
            }
            if (!empty($this->files['thumbnail'])) {
                $outputs['thumbnail'] = WorkerFileDownloader::download($e, $this->files['thumbnail'], 'image/png', 'png', $created)->id;
            }
        } catch (\Throwable $ex) {
            WorkerFileDownloader::discard($created);
            throw $ex;
        }

        $e->transition('importing', 'ready', ['outputs' => $outputs]);
        $e->log('import', 'ok', count($created) . ' files');

        Notify::user($e->user, 'Videos ready: approve to schedule', "The videos for {$e->run_date->toDateString()} are ready. Review them and approve to schedule.", Notify::episodeLink($e->id));
    }

    public function failed(\Throwable $ex): void
    {
        Episode::find($this->episodeId)?->fail('Import: ' . $ex->getMessage());
    }
}
