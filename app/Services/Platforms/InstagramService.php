<?php

namespace App\Services\Platforms;

use App\Models\Platform;
use App\Services\PlatformServiceInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class InstagramService implements PlatformServiceInterface
{
    private const API = 'https://graph.instagram.com/v23.0';
    private const MAX_CAPTION = 2200;
    private const VIDEO_EXTENSIONS = ['mp4', 'mov'];

    /**
     * Publish one image (or a video as a Reel) via container -> media_publish.
     * Instagram downloads the file itself, so the media URL must be publicly reachable.
     */
    public function publish(string $content, array $credentials, array $media = []): array
    {
        if (!$this->validateCredentials($credentials)) {
            throw new \Exception('Instagram credentials are incomplete. Please connect your account via Instagram login.');
        }
        if (empty($media[0])) {
            throw new \Exception('Instagram requires an image or video to publish.');
        }
        if (mb_strlen($content) > self::MAX_CAPTION) {
            throw new \Exception('Instagram captions are limited to ' . self::MAX_CAPTION . ' characters (got ' . mb_strlen($content) . ').');
        }

        try {
            $credentials = $this->refreshIfNeeded($credentials);
            $token = $credentials['access_token'];
            $igId = $credentials['instagram_user_id'];

            $mediaUrl = $media[0]['url'] ?? '';
            if (!preg_match('#^https?://#i', $mediaUrl)) {
                $mediaUrl = url($mediaUrl);
            }
            $isVideo = ($media[0]['type'] ?? '') === 'video'
                || in_array(strtolower(pathinfo(parse_url($mediaUrl, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION)), self::VIDEO_EXTENSIONS);

            // 1. Create container
            $container = Http::asForm()->timeout(60)->post(self::API . "/{$igId}/media", array_filter([
                'access_token' => $token,
                'caption' => $content,
                'image_url' => $isVideo ? null : $mediaUrl,
                'video_url' => $isVideo ? $mediaUrl : null,
                'media_type' => $isVideo ? 'REELS' : null,
            ]));
            $containerId = $container->json('id');
            if (!$container->successful() || !$containerId) {
                throw new \Exception('Instagram container creation failed: ' . ($container->json('error.message') ?? $container->body()));
            }

            // 2. Wait until Instagram has processed the media (videos take a while)
            $this->waitUntilReady($containerId, $token);

            // 3. Publish
            $publish = Http::asForm()->timeout(60)->post(self::API . "/{$igId}/media_publish", [
                'creation_id' => $containerId,
                'access_token' => $token,
            ]);
            $mediaId = $publish->json('id');
            if (!$publish->successful() || !$mediaId) {
                throw new \Exception('Instagram publish failed: ' . ($publish->json('error.message') ?? $publish->body()));
            }

            $permalink = Http::timeout(10)->get(self::API . "/{$mediaId}", [
                'fields' => 'permalink',
                'access_token' => $token,
            ])->json('permalink');

            return [
                'success' => true,
                'post_id' => $mediaId,
                'url' => $permalink,
                'platform' => 'instagram',
                'message' => 'Post published successfully on Instagram',
            ];
        } catch (\Exception $e) {
            Log::error('Instagram publish failed: ' . $e->getMessage(), [
                'platform_type' => 'instagram',
                'exception' => get_class($e),
            ]);
            throw $e;
        }
    }

    private function waitUntilReady(string $containerId, string $token): void
    {
        // Images are ready almost at once; videos can take a minute or two
        for ($i = 0; $i < 30; $i++) {
            $status = Http::timeout(10)->get(self::API . "/{$containerId}", [
                'fields' => 'status_code,status',
                'access_token' => $token,
            ])->json() ?? [];

            $code = $status['status_code'] ?? null;
            if ($code === 'FINISHED') {
                return;
            }
            if (in_array($code, ['ERROR', 'EXPIRED'], true)) {
                throw new \Exception('Instagram could not process the media: ' . ($status['status'] ?? $code));
            }
            sleep(4);
        }

        throw new \Exception('Instagram is still processing the media. Please try again in a few minutes.');
    }

    /**
     * Long-lived tokens last 60 days; refresh (and persist) once inside the last 7 days.
     */
    private function refreshIfNeeded(array $credentials): array
    {
        $connectedAt = strtotime($credentials['connected_at'] ?? '');
        $expiresIn = (int) ($credentials['expires_in'] ?? 0);

        if (!$connectedAt || !$expiresIn || time() < $connectedAt + $expiresIn - 7 * 86400) {
            return $credentials;
        }

        $response = Http::timeout(30)->get('https://graph.instagram.com/refresh_access_token', [
            'grant_type' => 'ig_refresh_token',
            'access_token' => $credentials['access_token'],
        ]);
        $json = $response->json() ?? [];

        if (!$response->successful() || empty($json['access_token'])) {
            throw new \Exception('Instagram token refresh failed: ' . ($json['error']['message'] ?? $response->body()) . ' Please reconnect Instagram.');
        }

        $newCredentials = array_merge($credentials, [
            'access_token' => $json['access_token'],
            'expires_in' => $json['expires_in'] ?? $expiresIn,
            'connected_at' => now()->toIso8601String(),
        ]);

        $platform = Platform::where('platform_type', 'instagram')->get()
            ->first(fn ($p) => ($p->credentials['instagram_user_id'] ?? null) === ($credentials['instagram_user_id'] ?? null));
        $platform?->update(['credentials' => $newCredentials]);

        return $newCredentials;
    }

    public function validateCredentials(array $credentials): bool
    {
        return !empty($credentials['access_token']) && !empty($credentials['instagram_user_id']);
    }

    public function testConnection(array $credentials): array
    {
        if (!$this->validateCredentials($credentials)) {
            return ['success' => false, 'message' => 'Instagram credentials are incomplete. Please connect via Instagram login.'];
        }

        try {
            $credentials = $this->refreshIfNeeded($credentials);

            $response = Http::timeout(10)->get(self::API . '/me', [
                'fields' => 'user_id,username,account_type',
                'access_token' => $credentials['access_token'],
            ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'Connection successful! Authenticated as: @' . ($response->json('username') ?? 'unknown'),
                    'account' => $response->json(),
                ];
            }

            return [
                'success' => false,
                'message' => 'Connection failed: ' . ($response->json('error.message') ?? $response->body()),
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Connection test failed: ' . $e->getMessage()];
        }
    }
}
