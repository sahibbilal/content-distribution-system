<?php

namespace App\Jobs;

use App\Models\Episode;
use App\Services\ClaudeWorkerClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** awaiting_avatar -> editing: sends voice, avatar and captions to the worker for the video edit */
class StartEditJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public int $episodeId)
    {
    }

    public function handle(ClaudeWorkerClient $worker): void
    {
        $e = Episode::find($this->episodeId);
        if (!$e || !$e->avatar_path || !$e->transition('awaiting_avatar', 'editing', ['step_started_at' => now(), 'error' => null])) {
            return;
        }

        $jobId = $worker->edit($e);
        $e->update(['worker_job_id' => $jobId]);
        $e->log('edit', 'started', null, ['worker_job_id' => $jobId]);
    }

    public function failed(\Throwable $ex): void
    {
        Episode::find($this->episodeId)?->fail('Edit: ' . $ex->getMessage());
    }
}
