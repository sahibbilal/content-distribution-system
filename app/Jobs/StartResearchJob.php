<?php

namespace App\Jobs;

use App\Models\Episode;
use App\Services\ClaudeWorkerClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** pending -> researching: asks the worker for a topic and an Urdu script */
class StartResearchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public int $episodeId)
    {
    }

    public function handle(ClaudeWorkerClient $worker): void
    {
        $e = Episode::find($this->episodeId);
        if (!$e || !$e->transition('pending', 'researching', ['step_started_at' => now(), 'error' => null])) {
            return; // not waiting for research (duplicate job, cancelled, ...)
        }

        $jobId = $worker->research($e);
        $e->update(['worker_job_id' => $jobId]);
        $e->log('research', 'started', $e->rejection_note ? 'With feedback: ' . $e->rejection_note : null, ['worker_job_id' => $jobId]);
    }

    public function failed(\Throwable $ex): void
    {
        Episode::find($this->episodeId)?->fail('Research: ' . $ex->getMessage());
    }
}
