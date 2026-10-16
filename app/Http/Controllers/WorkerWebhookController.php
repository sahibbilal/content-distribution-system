<?php

namespace App\Http\Controllers;

use App\Jobs\ImportImageJob;
use App\Jobs\ImportOutputsJob;
use App\Models\Episode;
use App\Services\ClaudeWorkerClient;
use App\Support\Notify;
use Illuminate\Http\Request;

/** POST /api/webhooks/worker: results from the cloud worker (HMAC signed, no user login) */
class WorkerWebhookController extends Controller
{
    public function __invoke(Request $r)
    {
        // an empty secret would make the signature trivial to forge, so refuse everything until it is set
        abort_unless(config('services.worker.secret'), 503, 'Worker secret is not configured.');
        abort_unless(hash_equals(ClaudeWorkerClient::sign($r->getContent()), (string) $r->header('X-Signature', '')), 401);

        $e = Episode::findOrFail($r->input('episode_id'));
        $type = $r->input('type');

        if ($r->input('status') === 'failed') {
            // only a step that is waiting on the worker can be failed by it
            if (in_array($e->status, ['researching', 'editing'], true)) {
                $e->fail(ucfirst((string) $type) . ': ' . ($r->input('error') ?: 'The worker reported a failure.'));
            }

            return response()->noContent();
        }

        match ($type) {
            'research' => $this->research($e, $r),
            'edit' => $this->edit($e, $r),
            default => abort(422, 'Unknown result type.'),
        };

        return response()->noContent();
    }

    private function research(Episode $e, Request $r): void
    {
        $data = $r->validate([
            'topic' => 'nullable|string|max:255',
            'script' => 'required|string',
            'sources' => 'nullable|array',
            'image' => 'nullable|string',
            'versions' => 'nullable|array',
            'versions.long' => 'nullable|string',
            'versions.short' => 'nullable|string',
            'hashtags' => 'nullable|array',
            'hashtags_short' => 'nullable|array',
        ]);

        // a text + image job also needs the picture before it can be approved
        if ($e->kind === 'image') {
            if ($e->status === 'researching') {
                empty($data['image'])
                    ? $e->fail('Research: the worker did not return an image.')
                    : ImportImageJob::dispatch($e->id, $data['image'], $data);
            }

            return;
        }

        $moved = $e->transition('researching', 'awaiting_approval', [
            'topic' => $data['topic'] ?? null,
            'script_ur' => $data['script'],
            'sources' => $data['sources'] ?? [],
            'outputs' => ImportImageJob::draftOutputs($e, $data),
            'error' => null,
        ]);

        if ($moved) {
            $e->log('script', 'ok', 'Draft from the worker', ['source' => 'worker', 'script' => $data['script']]);
            $e->series !== 'ai-post' && Notify::user($e->user, 'Script ready for approval', ($e->topic ?: 'New script') . ' is waiting for your approval.', Notify::episodeLink($e->id));
        }
    }

    private function edit(Episode $e, Request $r): void
    {
        $data = $r->validate([
            'files' => 'required|array',
            'files.long' => 'required|string',
            'files.shorts' => 'nullable|array',
            'files.thumbnail' => 'nullable|string',
            'meta' => 'nullable|array',
        ]);

        if ($e->status === 'editing') {
            ImportOutputsJob::dispatch($e->id, $data['files'], $data['meta'] ?? []);
        }
    }
}
