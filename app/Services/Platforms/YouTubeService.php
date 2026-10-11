<?php

namespace App\Services\Platforms;

use App\Services\PlatformServiceInterface;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class YouTubeService implements PlatformServiceInterface
{
    private const MAX_TITLE = 100;
    private const MAX_DESCRIPTION = 5000;

    /**
     * Upload one video via the resumable upload API. The post text becomes the
     * description; its first line becomes the title.
     */
    public function publish(string $content, array $credentials, array $media = []): array
    {
        if (!$this->validateCredentials($credentials)) {
            throw new \Exception('YouTube credentials are incomplete. Please reconnect your account via Google login.');
        }

        $video = collect($media)->first(fn ($m) => ($m['type'] ?? '') === 'video');
        if (!$video) {
            throw new \Exception('YouTube requires a video to publish.');
        }

        $disk = Storage::disk('public');
        if (empty($video['path']) || !$disk->exists($video['path'])) {
            throw new \Exception('The video file could not be found on the server.');
        }
        $filePath = $disk->path($video['path']);
        $mime = $disk->mimeType($video['path']) ?: 'video/mp4';

        try {
            $accessToken = $this->accessToken($credentials);

            $title = trim(strtok($content, "\n") ?: '');
            $title = mb_substr($title !== '' ? $title : 'Untitled video', 0, self::MAX_TITLE);

            // 1. Start the resumable session
            $init = Http::withToken($accessToken)
                ->withHeaders([
                    'X-Upload-Content-Type' => $mime,
                    'X-Upload-Content-Length' => (string) filesize($filePath),
                ])
                ->timeout(30)
                ->post('https://www.googleapis.com/upload/youtube/v3/videos?uploadType=resumable&part=snippet,status', [
                    'snippet' => [
                        'title' => $title,
                        'description' => mb_substr($content, 0, self::MAX_DESCRIPTION),
                    ],
                    'status' => [
                        'privacyStatus' => 'public',
                        'selfDeclaredMadeForKids' => false,
                    ],
                ]);

            $uploadUrl = $init->header('Location');
            if (!$init->successful() || !$uploadUrl) {
                throw new \Exception('YouTube upload could not start: ' . ($init->json('error.message') ?? $init->body()));
            }

            // 2. Send the file (streamed, not loaded into memory)
            $handle = fopen($filePath, 'rb');
            try {
                $upload = Http::withBody(Utils::streamFor($handle), $mime)->timeout(1800)->put($uploadUrl);
            } finally {
                if (is_resource($handle)) {
                    fclose($handle);
                }
            }

            $videoId = $upload->json('id');
            if (!$upload->successful() || !$videoId) {
                throw new \Exception('YouTube upload failed: ' . ($upload->json('error.message') ?? $upload->body()));
            }

            return [
                'success' => true,
                'post_id' => $videoId,
                'url' => 'https://www.youtube.com/watch?v=' . $videoId,
                'platform' => 'youtube',
                'message' => 'Video uploaded to YouTube',
            ];
        } catch (\Exception $e) {
            Log::error('YouTube publish failed: ' . $e->getMessage(), [
                'platform_type' => 'youtube',
                'exception' => get_class($e),
            ]);
            throw $e;
        }
    }

    /**
     * Access tokens last an hour; mint a fresh one from the stored refresh token each time.
     */
    private function accessToken(array $credentials): string
    {
        $response = Http::asForm()->timeout(30)->post('https://oauth2.googleapis.com/token', [
            'client_id' => trim(env('YOUTUBE_CLIENT_ID', '')),
            'client_secret' => trim(env('YOUTUBE_CLIENT_SECRET', '')),
            'refresh_token' => $credentials['refresh_token'],
            'grant_type' => 'refresh_token',
        ]);

        $token = $response->json('access_token');
        if (!$response->successful() || !$token) {
            throw new \Exception('YouTube token refresh failed: ' . ($response->json('error_description') ?? $response->body()) . ' Please reconnect YouTube.');
        }

        return $token;
    }

    public function validateCredentials(array $credentials): bool
    {
        return !empty($credentials['refresh_token']);
    }

    public function testConnection(array $credentials): array
    {
        if (!$this->validateCredentials($credentials)) {
            return ['success' => false, 'message' => 'YouTube credentials are incomplete. Please connect via Google login.'];
        }

        try {
            $response = Http::withToken($this->accessToken($credentials))->timeout(15)
                ->get('https://www.googleapis.com/youtube/v3/channels', ['part' => 'snippet', 'mine' => 'true']);

            $channel = $response->json('items.0');
            if ($response->successful() && $channel) {
                return [
                    'success' => true,
                    'message' => 'Connection successful! YouTube channel: ' . ($channel['snippet']['title'] ?? 'unknown'),
                    'channel' => $channel['snippet']['title'] ?? null,
                ];
            }

            return [
                'success' => false,
                'message' => $response->successful()
                    ? 'No YouTube channel found'
                    : 'Connection failed: ' . ($response->json('error.message') ?? $response->body()),
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Connection test failed: ' . $e->getMessage()];
        }
    }
}
