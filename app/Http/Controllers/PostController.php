<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Jobs\PublishPostJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PostController extends Controller
{
    public function index(Request $request)
    {
        $posts = Post::where('user_id', $request->user()->id)
            ->with('media')
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return response()->json($posts);
    }

    public function store(Request $request)
    {
        $request->validate([
            'content' => 'required|string',
            'platforms' => 'required|array|min:1',
            'platforms.*' => 'exists:platforms,id',
            'scheduled_at' => 'nullable|date|after:now',
            'media' => 'nullable|array',
            'media.*' => 'exists:media,id',
        ]);

        $post = Post::create([
            'user_id' => $request->user()->id,
            'content' => $request->content,
            'platforms' => $request->platforms,
            'status' => $request->scheduled_at ? 'scheduled' : 'draft',
            'scheduled_at' => $request->scheduled_at,
        ]);

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

    public function show(Request $request, $id)
    {
        $post = Post::where('user_id', $request->user()->id)
            ->with('media', 'schedules')
            ->findOrFail($id);

        return response()->json($post);
    }
}

