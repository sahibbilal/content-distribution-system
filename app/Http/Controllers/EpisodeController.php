<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateVoiceJob;
use App\Jobs\ScheduleEpisodePostsJob;
use App\Jobs\StartEditJob;
use App\Jobs\StartResearchJob;
use App\Models\Episode;
use App\Models\Media;
use App\Models\Platform;
use App\Models\Post;
use App\Support\PlatformMediaRules;
use App\Services\ClaudeWorkerClient;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class EpisodeController extends Controller
{
    /** Statuses where the episode can still be changed or stopped (nothing is scheduled or finished) */
    private const OPEN = ['pending', 'researching', 'awaiting_approval', 'approved', 'voicing', 'awaiting_avatar', 'editing', 'importing', 'ready', 'failed'];

    public function index(Request $request)
    {
        $request->validate(['status' => 'nullable|string|max:30', 'kind' => 'nullable|in:video,text,image']);

        return response()->json(
            Episode::where('user_id', $request->user()->id)
                ->when($request->status, fn ($q, $status) => $q->where('status', $status))
                ->when($request->kind, fn ($q, $kind) => $q->where('kind', $kind))
                ->orderByDesc('run_date')
                ->paginate(15)
                ->through(fn (Episode $e) => $this->present($e, false))
        );
    }

    /**
     * Start an episode by hand.
     * - video (default): today's daily video; with script_ur it skips research. One a day.
     * - text / image: an on-demand post. The worker searches and writes (and makes the image) from `prompt`;
     *   it is posted to `platform_ids`. With script_ur (text only) the research is skipped.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'kind' => 'nullable|in:video,text,image',
            'run_date' => 'nullable|date_format:Y-m-d',
            'scheduled_for' => 'nullable|date',
            'topic' => 'nullable|string|max:255',
            'script_ur' => 'nullable|string',
            'prompt' => 'nullable|string|max:2000',
            'platform_ids' => 'nullable|array|max:10',
            'platform_ids.*' => 'integer',
        ]);

        $kind = $data['kind'] ?? 'video';
        $scheduledFor = !empty($data['scheduled_for']) ? Carbon::parse($data['scheduled_for'])->setTimezone(config('app.timezone')) : null;

        if ($kind !== 'video') {
            return $this->storePost($request, $data, $kind, $scheduledFor);
        }

        $date = $data['run_date'] ?? Episode::today();
        $attributes = [];
        if ($scheduledFor) {
            $attributes['scheduled_for'] = $scheduledFor;
        }
        if (!empty($data['script_ur'])) {
            $attributes += ['status' => 'awaiting_approval', 'script_ur' => $data['script_ur'], 'topic' => $data['topic'] ?? null, 'sources' => []];
        }

        [$episode, $created] = Episode::open($request->user(), $date, $attributes);
        if (!$created) {
            return response()->json(['message' => "There is already a video episode for {$date}."], 422);
        }

        if ($episode->status === 'pending') {
            StartResearchJob::dispatch($episode->id);
        } else {
            $episode->log('script', 'ok', 'Script pasted by hand', ['source' => 'manual', 'script' => $episode->script_ur]);
        }

        return response()->json($this->present($episode), 201);
    }

    private function storePost(Request $request, array $data, string $kind, ?Carbon $scheduledFor)
    {
        $pasted = $kind === 'text' ? trim((string) ($data['script_ur'] ?? '')) : '';
        if (!$pasted && !trim((string) ($data['prompt'] ?? ''))) {
            return $this->refuse('Tell the assistant what to write about.');
        }

        $ids = array_values(array_unique($data['platform_ids'] ?? []));
        $platforms = Platform::where('user_id', $request->user()->id)->where('is_active', true)->whereIn('id', $ids)->get();
        if ($platforms->isEmpty() || $platforms->count() !== count($ids)) {
            return $this->refuse('Choose at least one connected platform to post to.');
        }

        // refuse a platform that can't take this kind of post (e.g. text only on Instagram) before any work is done
        $files = $kind === 'image' ? [['type' => 'image', 'ext' => 'png', 'name' => 'image.png', 'size' => 1]] : [];
        $problems = $platforms->flatMap(fn (Platform $p) => PlatformMediaRules::issues($p->platform_type, $files))->all();
        if ($problems) {
            return $this->refuse(implode("\n", $problems));
        }

        $scheduledFor ??= now()->addHour();
        if ($scheduledFor->lte(now())) {
            return $this->refuse('Pick a publish time in the future.');
        }

        $episode = Episode::create([
            'user_id' => $request->user()->id,
            'series' => 'ai-post',
            'kind' => $kind,
            'prompt' => $data['prompt'] ?? null,
            'platform_ids' => $platforms->pluck('id')->all(),
            'run_date' => Episode::today(),
            'topic' => $data['topic'] ?? null,
            'script_ur' => $pasted ?: null,
            'status' => $pasted ? 'awaiting_approval' : 'pending',
            'scheduled_for' => $scheduledFor,
        ]);

        if ($pasted) {
            $episode->log('script', 'ok', 'Text pasted by hand', ['source' => 'manual', 'script' => $pasted]);
        } else {
            StartResearchJob::dispatch($episode->id);
        }

        return response()->json($this->present($episode), 201);
    }

    public function show(Request $request, $id)
    {
        return response()->json($this->present($this->mine($request, $id)));
    }

    /** Change the publish time (any open episode), or the script/topic while it waits for approval */
    public function update(Request $request, $id)
    {
        $e = $this->mine($request, $id);
        $data = $request->validate([
            'scheduled_for' => 'nullable|date|after:now',
            'topic' => 'nullable|string|max:255',
            'script_ur' => 'nullable|string',
        ]);

        if (isset($data['scheduled_for'])) {
            if (!in_array($e->status, self::OPEN, true)) {
                return $this->refuse('The publish time can no longer be changed. Cancel posting first.');
            }
            $e->scheduled_for = Carbon::parse($data['scheduled_for'])->setTimezone(config('app.timezone'));
        }
        if (isset($data['script_ur']) || isset($data['topic'])) {
            if ($e->status !== 'awaiting_approval') {
                return $this->refuse('The script can only be edited while it waits for approval.');
            }
            $this->saveScript($e, $data['script_ur'] ?? $e->script_ur, $data['topic'] ?? $e->topic);
        }
        $e->save();

        return response()->json($this->present($e->fresh()));
    }

    /** Approve (optionally with edits) and start the voice */
    public function approve(Request $request, $id)
    {
        $e = $this->mine($request, $id);
        $data = $request->validate(['script_ur' => 'nullable|string']);

        if ($e->status !== 'awaiting_approval') {
            return $this->refuse('This episode is not waiting for approval.');
        }
        $script = $data['script_ur'] ?? $e->script_ur;
        if (!trim((string) $script)) {
            return $this->refuse('The script is empty.');
        }

        if (!$e->isVideo()) {
            // one tap: approve the text (and image) and schedule the posts
            if (!$e->scheduled_for || $e->scheduled_for->lte(now()->addMinute())) {
                return $this->refuse('Pick a publish time in the future first.');
            }
            if ($e->transition('awaiting_approval', 'ready', ['script_ur' => $script, 'error' => null])) {
                $e->log('approve', 'ok', 'Approved', ['script' => $script]);
                ScheduleEpisodePostsJob::dispatch($e->id);
            }
        } elseif ($e->transition('awaiting_approval', 'approved', ['script_ur' => $script, 'error' => null])) {
            $e->log('approve', 'ok', 'Approved', ['script' => $script]);
            GenerateVoiceJob::dispatch($e->id);
        }

        return response()->json($this->present($e->fresh()));
    }

    /** Reject with a note: research again from scratch with that feedback, or stop the day */
    public function reject(Request $request, $id)
    {
        $e = $this->mine($request, $id);
        $data = $request->validate(['note' => 'required|string|max:1000', 'retry' => 'required|boolean']);

        if ($e->status !== 'awaiting_approval') {
            return $this->refuse('This episode is not waiting for approval.');
        }

        // the rejected script stays in the history; a re-run starts clean so the worker picks a new angle
        $moved = $data['retry']
            ? $e->transition('awaiting_approval', 'pending', ['rejection_note' => $data['note'], 'script_ur' => null, 'sources' => null, 'topic' => null])
            : $e->transition('awaiting_approval', 'rejected', ['rejection_note' => $data['note']]);

        if ($moved) {
            $e->log('reject', 'ok', $data['note'], ['retry' => $data['retry']]);
            if ($data['retry']) {
                StartResearchJob::dispatch($e->id);
            }
        }

        return response()->json($this->present($e->fresh()));
    }

    /** Ask the worker to change the current script by an instruction (keeps the script, unlike reject) */
    public function revise(Request $request, $id)
    {
        $e = $this->mine($request, $id);
        $data = $request->validate(['instruction' => 'required|string|max:1000']);

        if ($e->status !== 'awaiting_approval') {
            return $this->refuse('This episode is not waiting for approval.');
        }

        if ($e->transition('awaiting_approval', 'pending', ['rejection_note' => $data['instruction']])) {
            $e->log('revise', 'ok', $data['instruction'], ['script' => $e->script_ur]);
            StartResearchJob::dispatch($e->id);
        }

        return response()->json($this->present($e->fresh()));
    }

    /** The avatar clip (made by hand in ElevenLabs) starts the edit */
    public function avatar(Request $request, $id)
    {
        $e = $this->mine($request, $id);
        $request->validate(['file' => 'required|file|mimetypes:video/mp4,video/quicktime|max:2097152']);

        if ($e->status !== 'awaiting_avatar') {
            return $this->refuse('This episode is not waiting for the avatar clip.');
        }

        $path = "episodes/{$e->id}/avatar.mp4";
        Storage::putFileAs("episodes/{$e->id}", $request->file('file'), 'avatar.mp4');
        $e->update(['avatar_path' => $path]);
        $e->log('avatar', 'ok', $request->file('file')->getClientOriginalName());
        StartEditJob::dispatch($e->id);

        return response()->json($this->present($e->fresh()));
    }

    /** Final approval of the finished videos: creates the posts in the scheduler */
    public function approveOutputs(Request $request, $id)
    {
        $e = $this->mine($request, $id);

        if ($e->status !== 'ready') {
            return $this->refuse('The videos are not ready for approval.');
        }
        if (!$e->scheduled_for || $e->scheduled_for->lte(now()->addMinute())) {
            return $this->refuse('Pick a publish time in the future first.');
        }

        $e->log('approve-outputs', 'ok');
        ScheduleEpisodePostsJob::dispatch($e->id);

        return response()->json($this->present($e->fresh()));
    }

    /** Take the episode's posts back out of the scheduler (works until they start publishing) */
    public function cancelPosting(Request $request, $id)
    {
        $e = $this->mine($request, $id);

        if ($e->status !== 'scheduled') {
            return $this->refuse('Nothing is scheduled for this episode.');
        }

        $posts = Post::where('user_id', $e->user_id)->whereIn('id', $e->post_ids ?? [])->get();
        if ($posts->contains(fn (Post $p) => $p->status !== 'scheduled' || $p->schedules()->where('status', '!=', 'pending')->exists())) {
            return $this->refuse('Publishing has already started and can no longer be cancelled.');
        }

        // the videos stay in the media library so the episode can be approved again
        foreach ($posts as $post) {
            Media::where('post_id', $post->id)->update(['post_id' => null]);
            $post->delete();
        }

        $e->transition('scheduled', 'ready', ['post_ids' => null]);
        $e->log('cancel-posting', 'ok');

        return response()->json($this->present($e->fresh()));
    }

    /** Go back to the step before the one that failed and run it again */
    public function retry(Request $request, $id)
    {
        $e = $this->mine($request, $id);

        $back = $e->retryTarget();
        if ($e->status !== 'failed' || !$back) {
            return $this->refuse('This episode cannot be retried.');
        }

        if ($e->transition('failed', $back, ['error' => null, 'failed_step' => null])) {
            $e->log('retry', 'ok', "Back to {$back}");
            match ($back) {
                'pending' => StartResearchJob::dispatch($e->id),
                'approved' => GenerateVoiceJob::dispatch($e->id),
                'awaiting_avatar' => $e->avatar_path ? StartEditJob::dispatch($e->id) : null,
                default => null, // ready: waits for the final approval again
            };
        }

        return response()->json($this->present($e->fresh()));
    }

    /** Stop the episode for good (nothing is posted) */
    public function cancel(Request $request, $id)
    {
        $e = $this->mine($request, $id);

        if (!in_array($e->status, self::OPEN, true) || !$e->transition($e->status, 'cancelled')) {
            return $this->refuse('This episode cannot be cancelled now.');
        }
        $e->log('cancel', 'ok');

        return response()->json($this->present($e->fresh()));
    }

    /** Signed download of an input file (voice, avatar, captions), used by the worker and the voice preview */
    public function file(Request $request, $episode, $kind)
    {
        $e = Episode::findOrFail($episode);
        $path = match ($kind) {
            'voice' => $e->voice_path,
            'avatar' => $e->avatar_path,
            'captions' => $e->captions_path,
            default => null,
        };
        abort_unless($path && Storage::exists($path), 404);

        $file = Storage::path($path);

        return $request->boolean('download') ? response()->download($file, basename($path)) : response()->file($file);
    }

    private function mine(Request $request, $id): Episode
    {
        return Episode::where('user_id', $request->user()->id)->findOrFail($id);
    }

    private function refuse(string $message)
    {
        return response()->json(['message' => $message], 422);
    }

    /** Store a hand edit of the script and keep the text in the history */
    private function saveScript(Episode $e, string $script, ?string $topic): void
    {
        $e->script_ur = $script;
        $e->topic = $topic;
        $e->log('script', 'ok', 'Edited by hand', ['source' => 'manual', 'script' => $script]);
    }

    private function present(Episode $e, bool $full = true): array
    {
        $media = fn (?int $id) => $id && ($m = Media::find($id)) ? ['id' => $m->id, 'url' => Storage::url($m->path)] : null;
        $o = $e->outputs;

        $data = [
            'id' => $e->id,
            'run_date' => $e->run_date->toDateString(),
            'status' => $e->status,
            'topic' => $e->topic,
            'kind' => $e->kind,
            'scheduled_for' => $e->scheduled_for,
            'error' => $e->error,
            'failed_step' => $e->failed_step,
            'updated_at' => $e->updated_at,
        ];

        if (!$full) {
            return $data;
        }

        return $data + [
            'prompt' => $e->prompt,
            'platform_ids' => $e->platform_ids ?? [],
            'script_ur' => $e->script_ur,
            'sources' => $e->sources ?? [],
            'rejection_note' => $e->rejection_note,
            'voice_url' => $e->voice_path ? ClaudeWorkerClient::fileUrl($e, 'voice') : null,
            'has_avatar' => (bool) $e->avatar_path,
            'outputs' => $o ? [
                'image' => $media($o['image'] ?? null),
                'long' => $media($o['long'] ?? null),
                'shorts' => array_map($media, $o['shorts'] ?? []),
                'thumbnail' => $media($o['thumbnail'] ?? null),
                'titles' => $o['titles'] ?? [],
                'descriptions' => $o['descriptions'] ?? [],
            ] : null,
            'post_ids' => $e->post_ids ?? [],
            'logs' => $e->logs()->get(['id', 'step', 'status', 'message', 'created_at']),
        ];
    }
}
