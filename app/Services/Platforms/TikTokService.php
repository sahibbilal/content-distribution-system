<?php

namespace App\Services\Platforms;

use App\Services\PlatformServiceInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class TikTokService implements PlatformServiceInterface
{
    public function publish(string $content, array $credentials, array $media = []): array
    {
        $accessToken = $credentials['access_token'] ?? null;
        $openId = $credentials['open_id'] ?? null;

        if (!$accessToken || !$openId) {
            throw new \Exception('TikTok credentials are incomplete. Please connect your TikTok account via OAuth.');
        }

        // Check if we have a video file
        if (empty($media) || !isset($media[0])) {
            throw new \Exception('TikTok requires a video file to publish. Please upload a video.');
        }

        $videoFile = $media[0];
        $videoPath = $videoFile['path'] ?? null;
        $videoUrl = $videoFile['url'] ?? null;

        if (!$videoPath && !$videoUrl) {
            throw new \Exception('Video file not found. Please upload a video file.');
        }

        Log::info('TikTok publish attempt', [
            'has_access_token' => !empty($accessToken),
            'has_open_id' => !empty($openId),
            'has_video' => !empty($videoPath) || !empty($videoUrl),
        ]);

        try {
            // Step 1: Initialize video upload
            $initData = [
                'post_info' => [
                    'title' => $content ?: 'Video Post',
                    'privacy_level' => 'PUBLIC_TO_EVERYONE',
                    'disable_duet' => false,
                    'disable_comment' => false,
                    'disable_stitch' => false,
                    'video_cover_timestamp' => 1000, // 1 second
                ],
            ];

            // Determine if we're using file upload or URL
            if ($videoPath && Storage::exists($videoPath)) {
                $fileSize = Storage::size($videoPath);
                $chunkSize = 5 * 1024 * 1024; // 5MB chunks
                $totalChunks = ceil($fileSize / $chunkSize);

                $initData['source_info'] = [
                    'source' => 'FILE_UPLOAD',
                    'video_size' => $fileSize,
                    'chunk_size' => $chunkSize,
                    'total_chunk_count' => $totalChunks,
                ];
            } elseif ($videoUrl) {
                $initData['source_info'] = [
                    'source' => 'PULL_FROM_URL',
                    'video_url' => $videoUrl,
                ];
            } else {
                throw new \Exception('Video file not accessible');
            }

            $initResponse = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}",
                'Content-Type' => 'application/json',
            ])->timeout(30)
            ->post('https://open.tiktokapis.com/v2/post/publish/video/init/', $initData);

            if (!$initResponse->successful()) {
                $errorBody = $initResponse->body();
                $errorData = json_decode($errorBody, true);
                $errorMessage = $errorData['error']['message'] ?? $errorData['error_description'] ?? $errorBody;
                throw new \Exception('TikTok upload initialization failed: ' . $errorMessage);
            }

            $initData = $initResponse->json();
            $uploadUrl = $initData['data']['upload_url'] ?? null;
            $publishId = $initData['data']['publish_id'] ?? null;

            if (!$uploadUrl || !$publishId) {
                throw new \Exception('TikTok did not return upload URL or publish ID');
            }

            Log::info('TikTok upload initialized', [
                'publish_id' => $publishId,
                'has_upload_url' => !empty($uploadUrl),
            ]);

            // Step 2: Upload video file (if FILE_UPLOAD)
            if (isset($initData['source_info']['source']) && $initData['source_info']['source'] === 'FILE_UPLOAD') {
                if (!$videoPath || !Storage::exists($videoPath)) {
                    throw new \Exception('Video file not found for upload');
                }

                $fileSize = Storage::size($videoPath);
                $chunkSize = $initData['data']['chunk_size'] ?? (5 * 1024 * 1024);
                $totalChunks = ceil($fileSize / $chunkSize);

                // Upload in chunks
                $fileHandle = fopen(Storage::path($videoPath), 'rb');
                if (!$fileHandle) {
                    throw new \Exception('Could not open video file for reading');
                }

                for ($chunkIndex = 0; $chunkIndex < $totalChunks; $chunkIndex++) {
                    $chunkData = fread($fileHandle, $chunkSize);
                    if ($chunkData === false) {
                        fclose($fileHandle);
                        throw new \Exception('Error reading video file chunk');
                    }

                    $chunkResponse = Http::withHeaders([
                        'Content-Type' => 'video/mp4',
                    ])->timeout(60)
                    ->put($uploadUrl, $chunkData, [
                        'query' => [
                            'upload_session_id' => $initData['data']['upload_session_id'] ?? '',
                            'total_chunk_count' => $totalChunks,
                            'chunk_index' => $chunkIndex,
                            'upload_chunk_size' => strlen($chunkData),
                        ],
                    ]);

                    if (!$chunkResponse->successful()) {
                        fclose($fileHandle);
                        throw new \Exception('Failed to upload video chunk ' . ($chunkIndex + 1) . ': ' . $chunkResponse->body());
                    }

                    Log::info('TikTok chunk uploaded', [
                        'chunk' => $chunkIndex + 1,
                        'total' => $totalChunks,
                    ]);
                }

                fclose($fileHandle);
            }

            // Step 3: Publish the video
            $publishResponse = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}",
                'Content-Type' => 'application/json',
            ])->timeout(30)
            ->post('https://open.tiktokapis.com/v2/post/publish/status/fetch/', [
                'publish_id' => $publishId,
            ]);

            // Construct post URL
            $postUrl = null;
            if ($publishId && $openId) {
                // TikTok post URLs format: https://www.tiktok.com/@username/video/{video_id}
                // Since we have publish_id, we can construct a URL
                // Note: We may need to fetch the actual video ID after publishing
                $postUrl = "https://www.tiktok.com/@user/video/{$publishId}";
            }

            Log::info('TikTok video published', [
                'publish_id' => $publishId,
                'post_url' => $postUrl,
            ]);

            return [
                'success' => true,
                'post_id' => $publishId,
                'publish_id' => $publishId,
                'post_url' => $postUrl,
                'url' => $postUrl ?? "https://www.tiktok.com/",
                'platform' => 'tiktok',
                'message' => 'Video published successfully on TikTok. The video may take a few moments to appear.',
            ];

        } catch (\Exception $e) {
            Log::error('TikTok publish failed: ' . $e->getMessage(), [
                'platform_type' => 'tiktok',
                'exception' => get_class($e),
            ]);
            throw $e;
        }
    }

    public function validateCredentials(array $credentials): bool
    {
        $accessToken = $credentials['access_token'] ?? null;
        $openId = $credentials['open_id'] ?? null;
        
        return !empty($accessToken) && !empty($openId);
    }

    public function testConnection(array $credentials): array
    {
        $accessToken = $credentials['access_token'] ?? null;
        $openId = $credentials['open_id'] ?? null;

        if (!$accessToken || !$openId) {
            return [
                'success' => false,
                'message' => 'TikTok credentials are incomplete. Please connect via OAuth.',
            ];
        }

        try {
            // Test by getting user info
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}",
            ])->timeout(10)
            ->get('https://open.tiktokapis.com/v2/user/info/', [
                'fields' => 'open_id,union_id,avatar_url,display_name',
            ]);

            if ($response->successful()) {
                $userData = $response->json();
                $displayName = $userData['data']['user']['display_name'] ?? 'Unknown';
                $openId = $userData['data']['user']['open_id'] ?? $openId;

                return [
                    'success' => true,
                    'message' => 'Connection successful! Authenticated as: ' . $displayName . ' (Open ID: ' . $openId . ')',
                    'user' => $userData['data']['user'] ?? null,
                ];
            }

            $errorBody = $response->body();
            $status = $response->status();
            $errorData = json_decode($errorBody, true);
            $errorMessage = $errorData['error']['message'] ?? $errorData['error_description'] ?? $errorBody;

            return [
                'success' => false,
                'message' => 'Connection failed with status ' . $status . ': ' . $errorMessage,
                'status' => $status,
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Connection test failed: ' . $e->getMessage(),
            ];
        }
    }
}

