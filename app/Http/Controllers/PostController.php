<?php

namespace App\Http\Controllers;

use App\Models\Platform;
use App\Models\Post;
use App\Models\SiteContent;
use App\Jobs\PublishPostJob;
use App\Support\PlatformMediaRules;
use App\Support\PostText;
use App\Support\RichText;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;

class PostController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'status' => 'nullable|in:published,scheduled,failed,draft',
            'platform' => 'nullable|string|max:50', // platform type, e.g. facebook
            'search' => 'nullable|string|max:200',
            'series_id' => 'nullable|integer',
        ]);

        $userId = $request->user()->id;

        $posts = Post::where('user_id', $userId)
            ->when($request->status, fn ($query, $status) => $query->where('status', $status))
            ->when($request->series_id, fn ($query, $id) => $query->where('series_id', $id))
            ->when($request->platform, function ($query, $type) use ($userId) {
                // posts store platform ids; match every one of the user's platforms of this type
                $ids = Platform::where('user_id', $userId)->where('platform_type', $type)->pluck('id');
                $query->where(function ($q) use ($ids) {
                    foreach ($ids as $id) {
                        $q->orWhereJsonContains('platforms', $id);
                    }
                    $q->orWhereRaw('1 = 0'); // no platform of this type: match nothing
                });
            })
            ->when($request->search, function ($query, $term) {
                // escape LIKE wildcards so "100%" searches for the literal text
                $like = '%' . addcslashes($term, '\\%_') . '%';
                $query->where('content', 'like', $like);
            })
            ->with('media', 'series:id,name')
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return response()->json($posts);
    }

    public function store(Request $request)
    {
        $request->validate(array_merge($this->textRules(), [
            'platforms' => 'required|array|min:1',
            'platforms.*' => 'exists:platforms,id',
            'series_id' => ['nullable', Rule::exists('post_series', 'id')->where('user_id', $request->user()->id)],
            'scheduled_at' => 'nullable|date|after:now',
            'media' => 'nullable|array',
            'media.*' => 'exists:media,id',
            'thumbnail_media_id' => 'nullable|exists:media,id',
        ]));

        [$content, $contentHtml] = $this->postText($request);
        $hashtags = PostText::cleanHashtags($request->hashtags);
        $overrides = PostText::cleanOverrides($request->platform_overrides, $request->platforms);
        if ($problem = $this->textProblem($request->user()->id, $request->platforms, $content, $hashtags, $overrides, $request->media ?? [])) {
            return $problem;
        }
        if ($problem = $this->duplicateProblem($request->user()->id, $content)) {
            return $problem;
        }

        if ($request->thumbnail_media_id) {
            $thumbnailError = $this->thumbnailError($request->user()->id, $request->thumbnail_media_id, $request->media ?? []);
            if ($thumbnailError) {
                return response()->json(['message' => $thumbnailError, 'errors' => ['thumbnail_media_id' => [$thumbnailError]]], 422);
            }
        }

        // Refuse file/platform combinations that can't be published (e.g. an image on YouTube)
        $problems = PlatformMediaRules::forPost($request->user()->id, $request->platforms, $request->media ?? []);
        if ($problems) {
            $messages = collect($problems)->flatten()->all();

            return response()->json([
                'message' => implode("\n", $messages),
                'errors' => ['media' => $messages],
            ], 422);
        }

        $post = Post::create([
            'user_id' => $request->user()->id,
            'series_id' => $request->series_id,
            'content' => $content,
            'content_html' => $contentHtml,
            'tags' => $this->cleanTags($request->tags),
            'hashtags' => $hashtags,
            'platform_overrides' => $overrides,
            'platforms' => $request->platforms,
            'status' => $request->scheduled_at ? 'scheduled' : 'draft',
            'scheduled_at' => $request->scheduled_at,
            'thumbnail_media_id' => $request->thumbnail_media_id,
        ]);

        if ($request->ai_generation_id) {
            \App\Models\AiGeneration::where('user_id', $request->user()->id)->whereKey($request->ai_generation_id)->update(['post_id' => $post->id]);
        }

        if ($request->media && is_array($request->media)) {
            \App\Models\Media::whereIn('id', $request->media)
                ->where('user_id', $request->user()->id)
                ->update(['post_id' => $post->id]);
        }

        if ($request->scheduled_at) {
            $post->schedules()->create([
                'user_id' => $request->user()->id,
                'scheduled_at' => $request->scheduled_at,
                'status' => 'pending',
            ]);
        } else {
            // Publish immediately - run synchronously to update status right away
            try {
                (new PublishPostJob($post))->handle();
                // Reload post to get updated status and platform_statuses
                $post->refresh();
                
                // Check if all platforms failed
                $platformStatuses = $post->platform_statuses ?? [];
                $allFailed = !empty($platformStatuses) && collect($platformStatuses)->every(function ($status) {
                    return ($status['status'] ?? 'failed') === 'failed';
                });
                
                // If all platforms failed, delete the post and throw error
                if ($allFailed && !empty($platformStatuses)) {
                    $errorMessages = collect($platformStatuses)->map(function ($status, $platformId) {
                        $platformType = $status['platform'] ?? 'Unknown';
                        $error = $status['error'] ?? $status['message'] ?? 'Publishing failed';
                        return "{$platformType}: {$error}";
                    })->join('; ');
                    
                    // Delete the post since publishing failed
                    $post->delete();
                    
                    return response()->json([
                        'error' => 'Failed to publish post to all selected platforms',
                        'message' => $errorMessages,
                        'platform_statuses' => $platformStatuses,
                    ], 422);
                }
                
                // If some platforms failed but at least one succeeded, still return success
                // but include the status information
                $hasFailures = collect($platformStatuses)->contains(function ($status) {
                    return ($status['status'] ?? 'failed') === 'failed';
                });
                
                if ($hasFailures) {
                    // Some platforms failed, but at least one succeeded
                    // Keep the post but return a warning
                    $post->load('media');
                    $postData = $post->toArray();
                    $postData['warning'] = 'Some platforms failed to publish';
                    $postData['platform_statuses'] = $platformStatuses;
                    return response()->json($postData, 201);
                }
            } catch (\Exception $e) {
                // If job throws an exception, delete the post and return error
                $post->delete();
                
                return response()->json([
                    'error' => 'Failed to publish post',
                    'message' => $e->getMessage(),
                ], 422);
            }
        }

        $post->load('media');

        return response()->json($post, 201);
    }

    /**
     * [plain text, cleaned HTML|null] for a request. Rich-text posts send content_html; the plain text
     * that platforms receive is always derived from it on the server, never trusted from the client.
     */
    private function postText(Request $request): array
    {
        if ($request->filled('content_html')) {
            $html = RichText::sanitize($request->content_html);
            $plain = RichText::toPlainText($html);

            return [$plain, $plain === '' ? null : $html];
        }

        return [trim((string) $request->content), null];
    }

    /** Validation rules for the text part of a post (shared by create and edit) */
    private function textRules(): array
    {
        return [
            'content' => 'nullable|string',
            'content_html' => 'nullable|string|max:100000',
            'tags' => 'nullable|array|max:20',
            'tags.*' => 'string|max:50',
            'hashtags' => 'nullable|array|max:30',
            'hashtags.*' => 'string|max:60',
            'ai_generation_id' => 'nullable|integer',
            'platform_overrides' => 'nullable|array',
            'platform_overrides.*' => 'array',
            'platform_overrides.*.content' => 'nullable|string|max:20000',
            'platform_overrides.*.hashtags' => 'nullable|array|max:30',
            'platform_overrides.*.hashtags.*' => 'string|max:60',
        ];
    }

    /**
     * A 422 response when the text can't be published: nothing to post, or a channel's text (with its hashtags)
     * is longer than that channel allows (X: 280 characters including spaces and hashtags). Null when fine.
     */
    private function textProblem(int $userId, array $platformIds, string $content, array $hashtags, array $overrides, array $mediaIds)
    {
        $hasMedia = $mediaIds && \App\Models\Media::where('user_id', $userId)->whereIn('id', $mediaIds)->exists();
        $hasOwnText = collect($overrides)->contains(fn ($o) => !empty($o['content']));
        if ($content === '' && !$hasMedia && !$hasOwnText) {
            return response()->json(['message' => 'Add some text or a file to post.', 'errors' => ['content' => ['Add some text or a file to post.']]], 422);
        }

        $messages = [];
        foreach (Platform::whereIn('id', $platformIds)->where('user_id', $userId)->get() as $platform) {
            $limit = PostText::limit($platform);
            $length = mb_strlen(PostText::forPlatform($content, $hashtags, $overrides, $platform->id));
            if ($limit && $length > $limit) {
                $label = PlatformMediaRules::label($platform->platform_type);
                $messages[] = "{$label}: the text is {$length} characters and the limit is {$limit} (spaces and hashtags count). Write a shorter version for {$label}.";
            }
        }

        return $messages ? response()->json(['message' => implode("\n", $messages), 'errors' => ['content' => $messages]], 422) : null;
    }

    /** Refuses text that is already posted (or scheduled) by this user: same words, any case or spacing. */
    private function duplicateProblem(int $userId, string $content, ?int $ignoreId = null)
    {
        $hash = Post::hashContent($content);
        if (!$hash) {
            return null; // a post with only a picture or video has no text to compare
        }

        $same = Post::where('user_id', $userId)->where('content_hash', $hash)
            ->whereIn('status', ['published', 'scheduled'])
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->latest('id')->first();
        if (!$same) {
            return null;
        }

        $when = $same->status === 'published'
            ? 'posted on ' . ($same->published_at ?? $same->created_at)->format('M j, Y')
            : 'already scheduled for ' . $same->scheduled_at?->format('M j, Y H:i');
        $message = "This content is already {$when} (post #{$same->id}). Change the text, or delete the earlier post first.";

        return response()->json(['message' => $message, 'errors' => ['content' => [$message]], 'duplicate_post_id' => $same->id], 422);
    }

    /** Trimmed, non-empty, unique (case-insensitive) tags in the order given. */
    private function cleanTags(?array $tags): array
    {
        $clean = [];
        foreach ($tags ?? [] as $tag) {
            $tag = trim($tag);
            if ($tag !== '' && !in_array(mb_strtolower($tag), array_map('mb_strtolower', $clean), true)) {
                $clean[] = $tag;
            }
        }

        return $clean;
    }

    /** Why the chosen thumbnail can't be used (null when it's fine). Limits match the strictest platform (YouTube: 2MB). */
    private function thumbnailError(int $userId, int $thumbnailId, array $mediaIds): ?string
    {
        $thumbnail = \App\Models\Media::where('user_id', $userId)->find($thumbnailId);
        if (!$thumbnail || $thumbnail->type !== 'image' || !in_array($thumbnail->mime_type, ['image/jpeg', 'image/png'], true)) {
            return 'The thumbnail must be a JPG or PNG image.';
        }
        if ($thumbnail->size > 2 * 1024 * 1024) {
            return 'The thumbnail must be 2MB or smaller (yours is ' . round($thumbnail->size / 1048576, 1) . 'MB).';
        }
        if (!\App\Models\Media::where('user_id', $userId)->whereIn('id', $mediaIds)->where('type', 'video')->exists()) {
            return 'A thumbnail can only be added to a post with a video.';
        }

        return null;
    }

    public function show(Request $request, $id)
    {
        $post = Post::where('user_id', $request->user()->id)
            ->with('media', 'schedules', 'series:id,name')
            ->findOrFail($id);

        return response()->json($post);
    }

    /**
     * Edit a post that is still waiting to be published: text, platforms and publish time.
     * Published posts can't be edited because their content is already live on the platforms.
     */
    public function update(Request $request, $id)
    {
        $post = Post::where('user_id', $request->user()->id)->with('media')->findOrFail($id);

        if ($post->status !== 'scheduled') {
            return response()->json(['message' => 'Only scheduled posts can be edited.'], 422);
        }

        $data = $request->validate(array_merge($this->textRules(), [
            'platforms' => 'required|array|min:1',
            'platforms.*' => 'exists:platforms,id',
            'series_id' => ['nullable', Rule::exists('post_series', 'id')->where('user_id', $request->user()->id)],
            'scheduled_at' => 'required|date|after:now',
        ]));

        [$data['content'], $data['content_html']] = $this->postText($request);
        $data['tags'] = $this->cleanTags($data['tags'] ?? null);
        $data['hashtags'] = PostText::cleanHashtags($request->hashtags);
        $data['platform_overrides'] = PostText::cleanOverrides($request->platform_overrides, $data['platforms']);
        if ($problem = $this->textProblem($request->user()->id, $data['platforms'], $data['content'], $data['hashtags'], $data['platform_overrides'], $post->media->pluck('id')->all())) {
            return $problem;
        }
        if ($problem = $this->duplicateProblem($request->user()->id, $data['content'], $post->id)) {
            return $problem;
        }

        $problems = PlatformMediaRules::forPost($request->user()->id, $data['platforms'], $post->media->pluck('id')->all());
        if ($problems) {
            $messages = collect($problems)->flatten()->all();

            return response()->json([
                'message' => implode("\n", $messages),
                'errors' => ['media' => $messages],
            ], 422);
        }

        DB::transaction(function () use ($post, $data) {
            $post->update($data);
            // per model (not a bulk update) so the ISO timestamp is cast to a DB datetime
            foreach ($post->schedules()->where('status', 'pending')->get() as $schedule) {
                $schedule->update(['scheduled_at' => $data['scheduled_at']]);
            }
        });

        return response()->json($post->fresh(['media', 'schedules']));
    }

    public function destroy(Request $request, $id)
    {
        $post = Post::where('user_id', $request->user()->id)->with(['media', 'thumbnail'])->findOrFail($id);

        // Deleting a post would cascade to its media rows and leave the files behind,
        // so either remove the files or release them back to the user's library
        $deleteFiles = SiteContent::setting('delete_media_after_publish');
        foreach ($post->media as $media) {
            if ($deleteFiles) {
                Storage::disk('public')->delete($media->path);
                $media->delete();
            } else {
                $media->update(['post_id' => null]);
            }
        }

        // the video's custom cover is a library file that is not attached through post_id
        if ($post->thumbnail && $deleteFiles) {
            Storage::disk('public')->delete($post->thumbnail->path);
            $post->thumbnail->delete();
        }

        $post->delete();

        return response()->json(['message' => 'Post deleted successfully']);
    }
}

