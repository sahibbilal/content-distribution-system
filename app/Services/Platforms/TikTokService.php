<?php

namespace App\Services\Platforms;

use App\Models\Platform;
use App\Services\PlatformServiceInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class TikTokService implements PlatformServiceInterface
{
    private const API = 'https://open.tiktokapis.com/v2';
    private const CHUNK_SIZE = 20 * 1024 * 1024; // TikTok allows 5MB-64MB chunks; the last one may be larger
    private const STATUS_POLLS = 10;

    public function publish(string $content, array $credentials, array $media = []): array
    {
        $accessToken = $credentials['access_token'] ?? null;
        $openId = $credentials['open_id'] ?? null;

        if (!$accessToken || !$openId) {
            $missing = implode(' and ', array_filter([!$accessToken ? 'access_token' : null, !$openId ? 'open_id' : null]));
            throw new \Exception("TikTok credentials are incomplete (missing {$missing}). Please reconnect your TikTok account via OAuth.");
        }

        if (($media[0]['type'] ?? null) === 'image') {
            return $this->publishPhotos($content, $credentials, $media);
        }

        $videoPath = $media[0]['path'] ?? null;
        $disk = Storage::disk('public'); // MediaController stores uploads on the public disk

        if (!$videoPath || !$disk->exists($videoPath)) {
            throw new \Exception('TikTok requires a video file to publish. Please upload a video.');
        }

        $extension = strtolower(pathinfo($videoPath, PATHINFO_EXTENSION));
        if (($media[0]['type'] ?? null) !== 'video' || !in_array($extension, ['mp4', 'mov', 'webm'], true)) {
            throw new \Exception("TikTok only accepts MP4, MOV or WebM video files (got .{$extension}). Audio can't be posted, and images must be JPEG or WebP.");
        }

        try {
            $credentials = $this->refreshIfExpired($credentials);
            $accessToken = $credentials['access_token'];

            // sandbox apps only get video.upload: the video lands in the TikTok inbox as a draft
            $draft = env('TIKTOK_MODE', 'production') === 'sandbox';

            $fileSize = $disk->size($videoPath);
            $chunkSize = $fileSize < self::CHUNK_SIZE ? $fileSize : self::CHUNK_SIZE;
            $totalChunks = max(1, intdiv($fileSize, $chunkSize));

            $body = [
                'source_info' => [
                    'source' => 'FILE_UPLOAD',
                    'video_size' => $fileSize,
                    'chunk_size' => $chunkSize,
                    'total_chunk_count' => $totalChunks,
                ],
            ];

            $privacyLevel = null;
            if (!$draft) {
                $privacyLevel = $this->pickPrivacyLevel($accessToken);
                $body['post_info'] = [
                    'title' => mb_substr($content ?: 'Video Post', 0, 2200),
                    'privacy_level' => $privacyLevel,
                    'disable_duet' => false,
                    'disable_comment' => false,
                    'disable_stitch' => false,
                ];
            }

            $init = $this->call(
                $accessToken,
                $draft ? '/post/publish/inbox/video/init/' : '/post/publish/video/init/',
                $body
            );

            $uploadUrl = $init['data']['upload_url'] ?? null;
            $publishId = $init['data']['publish_id'] ?? null;

            if (!$uploadUrl || !$publishId) {
                throw new \Exception('TikTok did not return an upload URL or publish ID');
            }

            $this->uploadChunks($uploadUrl, $disk->path($videoPath), $fileSize, $chunkSize, $totalChunks);

            $status = $this->waitForStatus($accessToken, $publishId);

            Log::info('TikTok publish finished', [
                'publish_id' => $publishId,
                'status' => $status['status'] ?? null,
                'mode' => $draft ? 'draft' : 'direct',
                'privacy_level' => $privacyLevel,
            ]);

            $state = $status['status'] ?? 'unknown';

            if ($draft) {
                $message = $state === 'SEND_TO_USER_INBOX'
                    ? 'Video sent to your TikTok inbox as a draft. Open the TikTok app to finish posting it.'
                    : "Video uploaded. TikTok is still processing it ({$state}).";
            } else {
                $message = $state === 'PUBLISH_COMPLETE'
                    ? "Video published on TikTok ({$privacyLevel})."
                    : "Video uploaded. TikTok is still processing it ({$state}).";
            }

            return [
                'success' => true,
                'post_id' => $status['publicaly_available_post_id'][0] ?? $publishId, // sic: TikTok's spelling
                'publish_id' => $publishId,
                'url' => null,
                'platform' => 'tiktok',
                'message' => $message,
            ];
        } catch (\Exception $e) {
            Log::error('TikTok publish failed: ' . $e->getMessage(), [
                'platform_type' => 'tiktok',
                'exception' => get_class($e),
            ]);
            throw $e;
        }
    }

    /**
     * Photo posts: TikTok pulls the images from public URLs, so they must be served from a
     * domain verified in the TikTok developer portal. Only JPEG and WebP are accepted.
     */
    private function publishPhotos(string $content, array $credentials, array $media): array
    {
        $urls = [];
        foreach (array_slice($media, 0, 35) as $item) { // TikTok allows up to 35 photos
            $extension = strtolower(pathinfo($item['path'] ?? '', PATHINFO_EXTENSION));
            if (($item['type'] ?? null) !== 'image' || !in_array($extension, ['jpg', 'jpeg', 'webp'], true)) {
                throw new \Exception("TikTok photo posts only accept JPEG or WebP images (got .{$extension}). PNG is not supported.");
            }
            $url = $item['url'] ?? '';
            $urls[] = str_starts_with($url, 'http') ? $url : url($url);
        }

        try {
            $credentials = $this->refreshIfExpired($credentials);
            $accessToken = $credentials['access_token'];

            $draft = env('TIKTOK_MODE', 'production') === 'sandbox';

            $postInfo = [
                'title' => mb_substr($content ?: 'Photo Post', 0, 90),
                'description' => mb_substr($content, 0, 4000),
            ];

            $privacyLevel = null;
            if (!$draft) {
                $privacyLevel = $this->pickPrivacyLevel($accessToken);
                $postInfo['privacy_level'] = $privacyLevel;
                $postInfo['disable_comment'] = false;
            }

            Log::info('TikTok photo publish: pulling images from', ['urls' => $urls]);

            $init = $this->call($accessToken, '/post/publish/content/init/', [
                'media_type' => 'PHOTO',
                'post_mode' => $draft ? 'MEDIA_UPLOAD' : 'DIRECT_POST',
                'post_info' => $postInfo,
                'source_info' => [
                    'source' => 'PULL_FROM_URL',
                    'photo_cover_index' => 0,
                    'photo_images' => $urls,
                ],
            ]);

            $publishId = $init['data']['publish_id'] ?? null;
            if (!$publishId) {
                throw new \Exception('TikTok did not return a publish ID');
            }

            $status = $this->waitForStatus($accessToken, $publishId);
            $state = $status['status'] ?? 'unknown';

            Log::info('TikTok photo publish finished', [
                'publish_id' => $publishId,
                'status' => $state,
                'mode' => $draft ? 'draft' : 'direct',
                'photos' => count($urls),
            ]);

            $done = $draft ? $state === 'SEND_TO_USER_INBOX' : $state === 'PUBLISH_COMPLETE';
            $message = $done
                ? ($draft
                    ? 'Photos sent to your TikTok inbox as a draft. Open the TikTok app to finish posting.'
                    : "Photos published on TikTok ({$privacyLevel}).")
                : "Photos submitted. TikTok is still processing them ({$state}).";

            return [
                'success' => true,
                'post_id' => $status['publicaly_available_post_id'][0] ?? $publishId,
                'publish_id' => $publishId,
                'url' => null,
                'platform' => 'tiktok',
                'message' => $message,
            ];
        } catch (\Exception $e) {
            Log::error('TikTok photo publish failed: ' . $e->getMessage(), [
                'platform_type' => 'tiktok',
                'exception' => get_class($e),
            ]);
            throw $e;
        }
    }

    /**
     * POST to a TikTok API endpoint. TikTok reports failures in error.code, not only the HTTP status.
     */
    private function call(string $accessToken, string $path, array $body = []): array
    {
        $response = Http::withToken($accessToken)->timeout(30)->post(self::API . $path, $body);
        $json = $response->json() ?? [];
        $code = $json['error']['code'] ?? 'ok';

        if (!$response->successful() || $code !== 'ok') {
            $message = $json['error']['message'] ?? $response->body();
            throw new \Exception("TikTok API error ({$code}): {$message}");
        }

        return $json;
    }

    /**
     * Use the most open privacy level this account is currently allowed to post with.
     * Unaudited apps are limited to SELF_ONLY.
     */
    private function pickPrivacyLevel(string $accessToken): string
    {
        $info = $this->call($accessToken, '/post/publish/creator_info/query/');
        $options = $info['data']['privacy_level_options'] ?? [];

        foreach (['PUBLIC_TO_EVERYONE', 'MUTUAL_FOLLOW_FRIENDS', 'FOLLOWER_OF_CREATOR', 'SELF_ONLY'] as $level) {
            if (in_array($level, $options, true)) {
                return $level;
            }
        }

        throw new \Exception('TikTok returned no usable privacy options for this account.');
    }

    private function uploadChunks(string $uploadUrl, string $filePath, int $fileSize, int $chunkSize, int $totalChunks): void
    {
        $handle = fopen($filePath, 'rb');
        if (!$handle) {
            throw new \Exception('Could not open video file for reading');
        }

        try {
            for ($i = 0; $i < $totalChunks; $i++) {
                $start = $i * $chunkSize;
                $length = ($i === $totalChunks - 1) ? $fileSize - $start : $chunkSize; // last chunk takes the remainder
                $end = $start + $length - 1;

                $data = fread($handle, $length);
                if ($data === false || strlen($data) !== $length) {
                    throw new \Exception('Error reading video file chunk ' . ($i + 1));
                }

                $response = Http::withBody($data, 'video/mp4')
                    ->withHeaders(['Content-Range' => "bytes {$start}-{$end}/{$fileSize}"])
                    ->timeout(120)
                    ->put($uploadUrl);

                // 206 = more chunks expected, 201 = upload complete
                if (!in_array($response->status(), [201, 206], true)) {
                    throw new \Exception('Failed to upload video chunk ' . ($i + 1) . ' (HTTP ' . $response->status() . '): ' . $response->body());
                }
            }
        } finally {
            fclose($handle);
        }
    }

    private function waitForStatus(string $accessToken, string $publishId): array
    {
        $data = [];

        for ($i = 0; $i < self::STATUS_POLLS; $i++) {
            $json = $this->call($accessToken, '/post/publish/status/fetch/', ['publish_id' => $publishId]);
            $data = $json['data'] ?? [];
            $status = $data['status'] ?? null;

            if ($status === 'FAILED') {
                throw new \Exception('TikTok rejected the video: ' . ($data['fail_reason'] ?? 'unknown reason'));
            }
            if (in_array($status, ['PUBLISH_COMPLETE', 'SEND_TO_USER_INBOX'], true)) {
                break;
            }

            sleep(3);
        }

        return $data;
    }

    /**
     * Access tokens last ~24h. Refresh when expired and persist the result,
     * since TikTok may rotate the refresh token.
     */
    private function refreshIfExpired(array $credentials): array
    {
        $connectedAt = strtotime($credentials['connected_at'] ?? '');
        $expiresIn = (int) ($credentials['expires_in'] ?? 0);
        $refreshToken = $credentials['refresh_token'] ?? null;

        if (!$connectedAt || !$expiresIn || !$refreshToken || time() < $connectedAt + $expiresIn - 300) {
            return $credentials;
        }

        $response = Http::asForm()->timeout(30)->post(self::API . '/oauth/token/', [
            'client_key' => trim(env('TIKTOK_CLIENT_KEY', '')),
            'client_secret' => trim(env('TIKTOK_CLIENT_SECRET', '')),
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
        ]);

        $json = $response->json() ?? [];
        if (!$response->successful() || empty($json['access_token'])) {
            throw new \Exception('TikTok token refresh failed: ' . ($json['error_description'] ?? $response->body()) . ' Please reconnect TikTok.');
        }

        $credentials = array_merge($credentials, [
            'access_token' => $json['access_token'],
            'refresh_token' => $json['refresh_token'] ?? $refreshToken,
            'expires_in' => $json['expires_in'] ?? $expiresIn,
            'connected_at' => now()->toIso8601String(),
        ]);

        $platform = Platform::where('platform_type', 'tiktok')->get()
            ->first(fn ($p) => ($p->credentials['open_id'] ?? null) === $credentials['open_id']);
        $platform?->update(['credentials' => $credentials]);

        return $credentials;
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
