<?php

namespace App\Jobs;

use App\Models\Post;
use App\Models\Platform;
use App\Services\PlatformServiceFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PublishPostJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Post $post)
    {
    }

    public function handle(): void
    {
        $platformStatuses = [];
        $media = $this->post->media->map(function ($m) {
            return [
                'url' => Storage::url($m->path),
                'path' => $m->path,
                'type' => $m->type,
            ];
        })->toArray();

        foreach ($this->post->platforms as $platformId) {
            $platform = Platform::find($platformId);
            
            if (!$platform || !$platform->is_active) {
                $platformStatuses[$platformId] = [
                    'status' => 'failed',
                    'error' => 'Platform not found or inactive',
                ];
                continue;
            }

            try {
                $service = PlatformServiceFactory::create($platform->platform_type);
                $result = $service->publish(
                    $this->post->content,
                    $platform->credentials,
                    $media
                );

                // Always store the URL if available, even if success is false
                $url = $result['dataset_url'] ?? $result['url'] ?? $result['post_url'] ?? null;
                
                $platformStatuses[$platformId] = [
                    'status' => $result['success'] ? 'published' : 'failed',
                    'post_id' => $result['post_id'] ?? null,
                    'platform' => $result['platform'] ?? $platform->platform_type,
                    'url' => $url,
                    'message' => $result['message'] ?? null,
                ];
            } catch (\Exception $e) {
                Log::error("Failed to publish to {$platform->platform_type}: " . $e->getMessage(), [
                    'platform_id' => $platformId,
                    'platform_type' => $platform->platform_type,
                    'exception' => get_class($e),
                ]);
                $platformStatuses[$platformId] = [
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                    'message' => $e->getMessage(), // Also store in message for display
                ];
            }
        }

        // Determine overall status based on platform statuses
        $hasPublished = collect($platformStatuses)->contains('status', 'published');
        $allFailed = collect($platformStatuses)->every(fn($status) => $status['status'] === 'failed');
        
        $this->post->update([
            'status' => $hasPublished ? 'published' : ($allFailed ? 'failed' : 'published'),
            'published_at' => now(),
            'platform_statuses' => $platformStatuses,
        ]);

        // Update schedule status if exists
        $schedule = $this->post->schedules()->where('status', 'pending')->first();
        if ($schedule) {
            $hasFailures = collect($platformStatuses)->contains('status', 'failed');
            $schedule->update([
                'status' => $hasFailures ? 'failed' : 'completed',
            ]);
        }
    }
}

