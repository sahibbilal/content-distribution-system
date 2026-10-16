<?php

namespace App\Services\Platforms;

use App\Models\Platform;
use App\Services\PlatformServiceInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class TwitterService implements PlatformServiceInterface
{
    private const API = 'https://api.twitter.com/2';
    private const MAX_LENGTH = 280;
    private const MAX_IMAGES = 4;
    private const MAX_IMAGE_BYTES = 5 * 1024 * 1024;
    private const MAX_VIDEO_BYTES = 512 * 1024 * 1024;
    private const CHUNK_BYTES = 4 * 1024 * 1024;

    /**
     * Post a tweet: text, optionally with up to 4 images (JPEG, PNG, WebP or GIF, 5MB each).
     */
    public function publish(string $content, array $credentials, array $media = []): array
    {
        if (empty($credentials['access_token'])) {
            throw new \Exception('X (Twitter) credentials are incomplete. Please connect your account via OAuth.');
        }

        $content = trim($content);
        $images = array_slice($media, 0, self::MAX_IMAGES);
        $video = null;

        if (($images[0]['type'] ?? null) === 'video') {
            if (count($media) > 1) {
                throw new \Exception('X accepts one video per post, and it cannot be mixed with images.');
            }
            $video = $images[0];
            $images = [];
            $extension = strtolower(pathinfo($video['path'] ?? '', PATHINFO_EXTENSION));
            if (!in_array($extension, ['mp4', 'mov'], true)) {
                throw new \Exception("X only accepts MP4 or MOV videos (got .{$extension}).");
            }
        }

        foreach ($images as $item) {
            $extension = strtolower(pathinfo($item['path'] ?? '', PATHINFO_EXTENSION));
            if (($item['type'] ?? null) !== 'image' || !in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
                throw new \Exception("X only accepts JPEG, PNG, WebP or GIF images (or one MP4/MOV video) with tweets (got .{$extension}).");
            }
        }

        if ($content === '' && !$images && !$video) {
            throw new \Exception('A tweet needs some text, an image or a video.');
        }
        // free accounts: 280 characters; Premium accounts may post longer
        $limit = ($credentials['account_tier'] ?? 'free') === 'premium' ? \App\Support\PostText::X_PREMIUM_LIMIT : self::MAX_LENGTH;
        if (mb_strlen($content) > $limit) {
            throw new \Exception('Posts on your X account are limited to ' . $limit . ' characters (got ' . mb_strlen($content) . ').');
        }

        try {
            $credentials = $this->refreshIfExpired($credentials);

            $body = ['text' => $content];
            if ($images) {
                $body['media'] = ['media_ids' => array_map(
                    fn ($item) => $this->uploadImage($credentials['access_token'], $item['path']),
                    $images
                )];
            } elseif ($video) {
                $body['media'] = ['media_ids' => [$this->uploadVideo($credentials['access_token'], $video['path'])]];
            }

            $response = Http::withToken($credentials['access_token'])
                ->timeout(30)
                ->post(self::API . '/tweets', $body);

            $json = $response->json() ?? [];
            $tweetId = $json['data']['id'] ?? null;

            if (!$response->successful() || !$tweetId) {
                $message = $json['detail'] ?? $json['title'] ?? $response->body();
                throw new \Exception("X API error ({$response->status()}): {$message}");
            }

            $username = $credentials['username'] ?? null;

            return [
                'success' => true,
                'post_id' => $tweetId,
                'url' => $username ? "https://x.com/{$username}/status/{$tweetId}" : "https://x.com/i/status/{$tweetId}",
                'platform' => 'twitter',
                'message' => 'Tweet published.',
            ];
        } catch (\Exception $e) {
            Log::error('Twitter publish failed: ' . $e->getMessage(), [
                'platform_type' => 'twitter',
                'exception' => get_class($e),
            ]);
            throw $e;
        }
    }

    /**
     * Upload one image from the public disk and return its media id (needs the media.write scope).
     */
    private function uploadImage(string $accessToken, string $path): string
    {
        $disk = Storage::disk('public'); // MediaController stores uploads on the public disk

        if (!$disk->exists($path)) {
            throw new \Exception('Image file not found: ' . basename($path));
        }
        if ($disk->size($path) > self::MAX_IMAGE_BYTES) {
            throw new \Exception('X images must be 5MB or smaller (' . basename($path) . ').');
        }

        $response = Http::withToken($accessToken)
            ->timeout(60)
            ->attach('media', $disk->get($path), basename($path))
            ->post(self::API . '/media/upload', ['media_category' => 'tweet_image']);

        $json = $response->json() ?? [];
        $mediaId = $json['data']['id'] ?? null;

        if (!$response->successful() || !$mediaId) {
            $message = $json['detail'] ?? $json['title'] ?? $response->body();
            if ($response->status() === 403) {
                $message .= ' (reconnect X so the media.write permission is granted)';
            }
            throw new \Exception("X media upload failed ({$response->status()}): {$message}");
        }

        return (string) $mediaId;
    }

    /**
     * Upload a video in chunks (initialize, append, finalize, then wait while X processes it) and return its media id.
     */
    private function uploadVideo(string $accessToken, string $path): string
    {
        $disk = Storage::disk('public');

        if (!$disk->exists($path)) {
            throw new \Exception('Video file not found: ' . basename($path));
        }
        $size = $disk->size($path);
        if ($size > self::MAX_VIDEO_BYTES) {
            throw new \Exception('X videos must be 512MB or smaller (' . basename($path) . ').');
        }

        $fail = function ($response, string $step): \Exception {
            $json = $response->json() ?? [];
            $message = $json['detail'] ?? $json['title'] ?? $response->body();
            if ($response->status() === 403) {
                $message .= ' (reconnect X so the media.write permission is granted)';
            }

            return new \Exception("X video upload failed at {$step} ({$response->status()}): {$message}");
        };

        $init = Http::withToken($accessToken)->timeout(60)->post(self::API . '/media/upload/initialize', [
            'media_type' => $disk->mimeType($path) ?: 'video/mp4',
            'total_bytes' => $size,
            'media_category' => 'tweet_video',
        ]);
        $mediaId = $init->json('data.id');
        if (!$init->successful() || !$mediaId) {
            throw $fail($init, 'start');
        }

        $stream = $disk->readStream($path);
        try {
            for ($segment = 0; !feof($stream); $segment++) {
                $chunk = stream_get_contents($stream, self::CHUNK_BYTES);
                if ($chunk === '' || $chunk === false) {
                    break;
                }
                $append = Http::withToken($accessToken)->timeout(120)
                    ->attach('media', $chunk, basename($path))
                    ->post(self::API . "/media/upload/{$mediaId}/append", ['segment_index' => $segment]);
                if (!$append->successful()) {
                    throw $fail($append, "part {$segment}");
                }
            }
        } finally {
            fclose($stream);
        }

        $finalize = Http::withToken($accessToken)->timeout(60)->post(self::API . "/media/upload/{$mediaId}/finalize");
        if (!$finalize->successful()) {
            throw $fail($finalize, 'finish');
        }

        // X processes the video before it can be attached
        $info = $finalize->json('data.processing_info');
        for ($tries = 0; $info && in_array($info['state'] ?? '', ['pending', 'in_progress'], true) && $tries < 30; $tries++) {
            sleep(max(1, min((int) ($info['check_after_secs'] ?? 3), 10)));
            $status = Http::withToken($accessToken)->timeout(30)->get(self::API . '/media/upload', ['command' => 'STATUS', 'media_id' => $mediaId]);
            $info = $status->json('data.processing_info');
        }
        if ($info && ($info['state'] ?? '') === 'failed') {
            throw new \Exception('X could not process the video: ' . ($info['error']['message'] ?? 'unknown error'));
        }
        if ($info && in_array($info['state'] ?? '', ['pending', 'in_progress'], true)) {
            throw new \Exception('X is still processing the video. Try publishing again in a few minutes.');
        }

        return (string) $mediaId;
    }

    /**
     * Access tokens last 2h. Refresh tokens are single-use, so the new pair must be persisted.
     */
    private function refreshIfExpired(array $credentials): array
    {
        $connectedAt = strtotime($credentials['connected_at'] ?? '');
        $expiresIn = (int) ($credentials['expires_in'] ?? 0);
        $refreshToken = $credentials['refresh_token'] ?? null;

        if (!$connectedAt || !$expiresIn || !$refreshToken || time() < $connectedAt + $expiresIn - 300) {
            return $credentials;
        }

        $response = Http::asForm()
            ->withBasicAuth(trim(env('TWITTER_CLIENT_ID', '')), trim(env('TWITTER_CLIENT_SECRET', '')))
            ->timeout(30)
            ->post(self::API . '/oauth2/token', [
                'grant_type' => 'refresh_token',
                'refresh_token' => $refreshToken,
            ]);

        $json = $response->json() ?? [];
        if (!$response->successful() || empty($json['access_token'])) {
            throw new \Exception('X token refresh failed: ' . ($json['error_description'] ?? $response->body()) . ' Please reconnect X.');
        }

        $newCredentials = array_merge($credentials, [
            'access_token' => $json['access_token'],
            'refresh_token' => $json['refresh_token'] ?? $refreshToken,
            'expires_in' => $json['expires_in'] ?? $expiresIn,
            'connected_at' => now()->toIso8601String(),
        ]);

        $platform = Platform::where('platform_type', 'twitter')->get()
            ->first(fn ($p) => ($p->credentials['user_id'] ?? null) === ($credentials['user_id'] ?? null));
        $platform?->update(['credentials' => $newCredentials]);

        return $newCredentials;
    }

    public function validateCredentials(array $credentials): bool
    {
        return !empty($credentials['access_token']);
    }

    public function testConnection(array $credentials): array
    {
        if (empty($credentials['access_token'])) {
            return ['success' => false, 'message' => 'X credentials are incomplete. Please connect via OAuth.'];
        }

        try {
            $credentials = $this->refreshIfExpired($credentials);

            $response = Http::withToken($credentials['access_token'])->timeout(10)->get(self::API . '/users/me');

            if ($response->successful()) {
                $user = $response->json('data');

                return [
                    'success' => true,
                    'message' => 'Connection successful! Authenticated as: @' . ($user['username'] ?? 'unknown'),
                    'user' => $user,
                ];
            }

            return [
                'success' => false,
                'message' => 'Connection failed with status ' . $response->status() . ': ' . ($response->json('detail') ?? $response->body()),
                'status' => $response->status(),
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Connection test failed: ' . $e->getMessage()];
        }
    }
}
