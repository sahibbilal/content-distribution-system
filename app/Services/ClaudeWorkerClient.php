<?php

namespace App\Services;

use App\Models\Episode;
use App\Models\Platform;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;

/** Starts jobs on the cloud worker (document 02). Results come back through POST /api/webhooks/worker. */
class ClaudeWorkerClient
{
    /**
     * Research and write a script. With a rejection note (re-run) or an instruction (revise), the worker also
     * gets the current script so it can change it instead of starting from nothing.
     */
    public function research(Episode $e): string
    {
        return $this->post('/jobs/research', [
            'episode_id' => $e->id,
            'series' => $e->series,
            'kind' => $e->kind,
            'prompt' => $e->prompt,
            'options' => $e->outputs['options'] ?? null,
            'platforms' => $e->kind === 'video' ? null : Platform::whereIn('id', $e->platform_ids ?? [])->pluck('platform_type')->unique()->values()->all(),
            'date' => $e->run_date->toDateString(),
            'feedback' => $e->rejection_note,
            'current_script' => $e->rejection_note ? $e->script_ur : null,
            'callback' => url('/api/webhooks/worker'),
        ])['job_id'];
    }

    public function edit(Episode $e): string
    {
        return $this->post('/jobs/edit', [
            'episode_id' => $e->id,
            'files' => [
                'voice' => self::fileUrl($e, 'voice'),
                'avatar' => self::fileUrl($e, 'avatar'),
                'captions' => self::fileUrl($e, 'captions'),
            ],
            'script' => $e->script_ur,
            'shorts' => config('series.a.shorts'),
            'callback' => url('/api/webhooks/worker'),
        ])['job_id'];
    }

    /** Signed link the worker downloads an episode input file from (valid 3 hours) */
    public static function fileUrl(Episode $e, string $kind): string
    {
        return URL::temporarySignedRoute('episodes.file', now()->addHours(3), ['episode' => $e->id, 'kind' => $kind]);
    }

    /** HMAC of a request body, used in both directions */
    public static function sign(string $body): string
    {
        return hash_hmac('sha256', $body, (string) config('services.worker.secret'));
    }

    private function post(string $path, array $body): array
    {
        if (!config('services.worker.url') || !config('services.worker.secret')) {
            throw new \RuntimeException('The Claude worker is not configured (CLAUDE_WORKER_URL / CLAUDE_WORKER_SECRET).');
        }

        $json = json_encode($body);

        return Http::withHeaders(['X-Signature' => self::sign($json)])
            ->withBody($json, 'application/json')
            ->timeout(30)
            ->post(rtrim(config('services.worker.url'), '/') . $path)
            ->throw()
            ->json();
    }
}
