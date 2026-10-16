<?php

namespace App\Http\Controllers;

use App\Jobs\StartResearchJob;
use App\Models\AiGeneration;
use App\Models\AiProject;
use App\Models\Episode;
use App\Models\Media;
use App\Models\Platform;
use App\Services\AiDraftService;
use App\Services\AiInstructions;
use App\Services\ImageGenerator;
use App\Support\PostText;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * "Ask AI" on the Create Post page. The draft only fills the form; nothing is posted until the user presses Publish.
 * engine = anthropic: answers in the request (seconds). engine = worker: runs on the Claude worker (minutes), poll show().
 * Both follow the user's rulebook and the chosen project (its instructions and its memory of earlier drafts).
 */
class AiDraftController extends Controller
{
    public function draft(Request $request, AiDraftService $ai, ImageGenerator $images)
    {
        $data = $request->validate([
            'prompt' => 'required|string|max:2000',
            'engine' => 'nullable|in:anthropic,worker',
            'project_id' => 'nullable|integer',
            'instructions' => 'nullable|array|max:10', // rulebook sections to follow in this chat; omitted = the standard ones
            'instructions.*' => 'string|max:30',
            'platforms' => 'nullable|array|max:10',
            'platforms.*' => 'integer',
            'hashtags' => 'boolean',
            'web_search' => 'boolean',
            'make_image' => 'boolean',
            'media' => 'nullable|array|max:10',
            'media.*' => 'integer',
        ]);

        $user = $request->user();
        $project = !empty($data['project_id']) ? AiProject::where('user_id', $user->id)->find($data['project_id']) : null;
        if (!empty($data['project_id']) && !$project) {
            return response()->json(['message' => 'That project no longer exists.'], 422);
        }
        $platforms = Platform::where('user_id', $user->id)->whereIn('id', $data['platforms'] ?? [])->get();
        $attached = Media::where('user_id', $user->id)->whereIn('id', $data['media'] ?? [])->where('type', 'image')->get();
        $rules = AiInstructions::compose($user, $project, (bool) ($data['make_image'] ?? false), $data['instructions'] ?? null);

        if (($data['engine'] ?? 'anthropic') === 'worker') {
            return $this->startWorker($user->id, $data, $platforms, $project, $rules);
        }

        try {
            $result = $ai->draft($data['prompt'], $platforms->all(), (bool) ($data['hashtags'] ?? false), (bool) ($data['web_search'] ?? false), $attached->all(), (bool) ($data['make_image'] ?? false), $rules);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        // the picture is made after the text; if that fails the text is still returned
        $image = null;
        $imageError = null;
        if (!empty($data['make_image']) && $result['image_prompt']) {
            try {
                $image = $images->generate($user, $result['image_prompt']);
            } catch (\Throwable $e) {
                $imageError = $e->getMessage();
            }
        }

        // remembered, so the next draft of this project does not repeat it
        $memory = AiGeneration::record($user->id, $project?->id, $data['prompt'], $result['long'], $result['short'], $result['hashtags']);

        return response()->json($this->shape($result['long'], $result['short'], $result['hashtags'], $result['hashtags_short'], $image, $result['image_check'], $result['notes'], $imageError, $memory->id));
    }

    /** Result of a worker draft: {status: working|ready|failed, ...draft} */
    public function show(Request $request, $id)
    {
        $e = Episode::where('user_id', $request->user()->id)->where('series', 'ai-post')->findOrFail($id);

        if ($e->status === 'failed') {
            return response()->json(['status' => 'failed', 'message' => $e->error ?: 'The worker could not write the draft.']);
        }
        if (!in_array($e->status, ['awaiting_approval', 'cancelled'], true)) {
            return response()->json(['status' => 'working', 'step' => $e->status]);
        }

        // the draft now lives in the form, so the episode is closed (it would otherwise wait for an approval)
        $e->transition('awaiting_approval', 'cancelled');
        $e->refresh();
        $o = $e->outputs ?? [];
        $long = $o['versions']['long'] ?? (string) $e->script_ur;
        $short = $o['versions']['short'] ?? null;

        if (empty($o['generation_id'])) {
            $o['generation_id'] = AiGeneration::record($e->user_id, $o['options']['project_id'] ?? null, (string) $e->prompt, $long, $short, $o['hashtags'] ?? [])->id;
            $e->update(['outputs' => $o]);
        }
        $image = !empty($o['image']) ? Media::where('user_id', $e->user_id)->find($o['image']) : null;

        return response()->json(['status' => 'ready'] + $this->shape($long, $short, $o['hashtags'] ?? [], $o['hashtags_short'] ?? [], $image, null, [], null, $o['generation_id']));
    }

    private function startWorker(int $userId, array $data, $platforms, ?AiProject $project, string $rules)
    {
        if (!config('services.worker.url')) {
            return response()->json(['message' => 'The Claude worker is not set up. Choose the Anthropic API, or connect the worker in Admin > Content pipeline.'], 422);
        }

        $xLimit = $platforms->where('platform_type', 'twitter')->map(fn (Platform $p) => PostText::limit($p))->min();
        $episode = Episode::create([
            'user_id' => $userId,
            'series' => 'ai-post',
            'kind' => !empty($data['make_image']) ? 'image' : 'text',
            'prompt' => $data['prompt'],
            'platform_ids' => $platforms->pluck('id')->all(),
            'run_date' => Episode::today(),
            'status' => 'pending',
            'scheduled_for' => now()->addHour(),
            // read by the worker: the user's rulebook, whether to add hashtags, and how long the X version may be
            'outputs' => ['options' => ['hashtags' => (bool) ($data['hashtags'] ?? false), 'x_limit' => $xLimit, 'instructions' => $rules, 'project_id' => $project?->id]],
        ]);
        StartResearchJob::dispatch($episode->id);

        return response()->json(['engine' => 'worker', 'episode_id' => $episode->id], 202);
    }

    private function shape(string $long, ?string $short, array $hashtags, array $hashtagsShort, ?Media $image, ?array $imageCheck, array $notes, ?string $imageError, ?int $generationId): array
    {
        return [
            'generation_id' => $generationId,
            'versions' => ['long' => $long, 'short' => $short ?: null],
            'hashtags' => PostText::cleanHashtags($hashtags),
            'hashtags_short' => PostText::cleanHashtags($hashtagsShort),
            'image' => $image ? ['id' => $image->id, 'url' => Storage::url($image->path), 'filename' => $image->original_filename, 'type' => 'image', 'size' => (int) $image->size] : null,
            'image_check' => $imageCheck,
            'image_error' => $imageError,
            'notes' => $notes,
        ];
    }
}
