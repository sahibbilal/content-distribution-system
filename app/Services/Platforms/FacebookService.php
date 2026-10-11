<?php

namespace App\Services\Platforms;

use App\Services\PlatformServiceInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class FacebookService implements PlatformServiceInterface
{
    public function publish(string $content, array $credentials, array $media = []): array
    {
        $pageId = $credentials['page_id'] ?? null;
        $accessToken = $credentials['access_token'] ?? null;
        $instagramAccountId = $credentials['instagram_account_id'] ?? null;
        $platformType = $credentials['platform_type'] ?? 'facebook'; // 'facebook' or 'instagram'

        if (!$accessToken) {
            throw new \Exception('Facebook access token is required. Please connect your Facebook account via OAuth.');
        }

        // If Instagram account ID is provided, post to Instagram
        if ($platformType === 'instagram' && $instagramAccountId) {
            return $this->publishToInstagram($content, $instagramAccountId, $accessToken, $media);
        }

        // Otherwise, post to Facebook Page
        if (!$pageId) {
            throw new \Exception('Facebook Page ID is required. Please connect your Facebook Page via OAuth.');
        }

        return $this->publishToFacebook($content, $pageId, $accessToken, $media);
    }

    /**
     * Publish to Facebook Page
     */
    private function publishToFacebook(string $content, string $pageId, string $accessToken, array $media = []): array
    {
        Log::info('Facebook publish attempt', [
            'page_id' => $pageId,
            'has_content' => !empty($content),
            'has_media' => !empty($media),
        ]);

        $video = collect($media)->first(fn ($m) => ($m['type'] ?? '') === 'video');
        if ($video) {
            return $this->publishVideoToFacebook($content, $pageId, $accessToken, $video);
        }

        if (!empty($media[0])) {
            return $this->publishPhotoToFacebook($content, $pageId, $accessToken, $media[0]);
        }

        $url = "https://graph.facebook.com/v18.0/{$pageId}/feed";

        $data = [
            'message' => $content,
            'access_token' => $accessToken,
        ];

        $response = Http::post($url, $data);

        if ($response->successful()) {
            $postId = $response->json('id');
            $postUrl = "https://www.facebook.com/{$postId}";

            Log::info('Facebook post published', [
                'post_id' => $postId,
                'post_url' => $postUrl,
            ]);

            return [
                'success' => true,
                'post_id' => $postId,
                'post_url' => $postUrl,
                'url' => $postUrl,
                'platform' => 'facebook',
                'message' => 'Post published successfully on Facebook',
            ];
        }

        $errorBody = $response->body();
        $errorData = json_decode($errorBody, true);
        $errorMessage = $errorData['error']['message'] ?? $errorBody;
        
        throw new \Exception('Facebook API error: ' . $errorMessage);
    }

    /**
     * Publish an image to the Page's /photos edge with the post text as its caption.
     */
    private function publishPhotoToFacebook(string $content, string $pageId, string $accessToken, array $image): array
    {
        $disk = Storage::disk('public');
        if (empty($image['path']) || !$disk->exists($image['path'])) {
            throw new \Exception('The image file could not be found on the server.');
        }

        $response = Http::timeout(120)
            ->attach('source', $disk->get($image['path']), basename($image['path']))
            ->post("https://graph.facebook.com/v18.0/{$pageId}/photos", [
                'caption' => $content,
                'access_token' => $accessToken,
            ]);

        $postId = $response->json('post_id') ?? $response->json('id');
        if (!$response->successful() || !$postId) {
            throw new \Exception('Facebook photo upload failed: ' . ($response->json('error.message') ?? $response->body()));
        }

        return [
            'success' => true,
            'post_id' => $postId,
            'post_url' => "https://www.facebook.com/{$postId}",
            'url' => "https://www.facebook.com/{$postId}",
            'platform' => 'facebook',
            'message' => 'Post published successfully on Facebook',
        ];
    }

    /**
     * Upload a video to the Page's /videos edge, with the custom thumbnail ("thumb") if one was chosen.
     */
    private function publishVideoToFacebook(string $content, string $pageId, string $accessToken, array $video): array
    {
        $disk = Storage::disk('public');
        if (empty($video['path']) || !$disk->exists($video['path'])) {
            throw new \Exception('The video file could not be found on the server.');
        }

        $request = Http::timeout(1800)->attach('source', $disk->get($video['path']), basename($video['path']));

        $thumbPath = $video['thumbnail']['path'] ?? null;
        $hasThumb = $thumbPath && $disk->exists($thumbPath);
        if ($hasThumb) {
            $request = $request->attach('thumb', $disk->get($thumbPath), basename($thumbPath));
        }

        $response = $request->post("https://graph.facebook.com/v18.0/{$pageId}/videos", [
            'description' => $content,
            'access_token' => $accessToken,
        ]);

        $videoId = $response->json('id');
        if (!$response->successful() || !$videoId) {
            throw new \Exception('Facebook video upload failed: ' . ($response->json('error.message') ?? $response->body()));
        }

        return [
            'success' => true,
            'post_id' => $videoId,
            'post_url' => "https://www.facebook.com/{$videoId}",
            'url' => "https://www.facebook.com/{$videoId}",
            'platform' => 'facebook',
            'message' => 'Video published on Facebook' . ($hasThumb ? ' with custom thumbnail' : ''),
        ];
    }

    /**
     * Publish to Instagram Business Account
     */
    private function publishToInstagram(string $content, string $instagramAccountId, string $accessToken, array $media = []): array
    {
        Log::info('Instagram publish attempt', [
            'instagram_account_id' => $instagramAccountId,
            'has_content' => !empty($content),
            'has_media' => !empty($media),
        ]);

        if (empty($media) || !isset($media[0])) {
            throw new \Exception('Instagram requires a media file (image or video) to publish.');
        }

        $mediaFile = $media[0];
        $mediaPath = $mediaFile['path'] ?? null;
        $mediaUrl = $mediaFile['url'] ?? null;

        if (!$mediaPath && !$mediaUrl) {
            throw new \Exception('Media file not found for Instagram post');
        }

        // Step 1: Create media container
        $mediaUrlToUse = $mediaUrl;
        if ($mediaPath && file_exists(storage_path('app/' . $mediaPath))) {
            // Upload media to Facebook first
            $mediaUrlToUse = $this->uploadMediaToFacebook($accessToken, storage_path('app/' . $mediaPath));
            if (!$mediaUrlToUse) {
                throw new \Exception('Failed to upload media to Facebook');
            }
        }

        // Determine if it's an image or video
        $isVideo = $this->isVideoFile($mediaPath ?? $mediaUrl);
        
        if ($isVideo) {
            // For video, create video container
            $containerResponse = Http::post("https://graph.facebook.com/v18.0/{$instagramAccountId}/media", [
                'media_type' => 'VIDEO',
                'video_url' => $mediaUrlToUse,
                'caption' => $content,
                'access_token' => $accessToken,
            ]);
        } else {
            // For image, create image container
            $containerResponse = Http::post("https://graph.facebook.com/v18.0/{$instagramAccountId}/media", [
                'image_url' => $mediaUrlToUse,
                'caption' => $content,
                'access_token' => $accessToken,
            ]);
        }

        if (!$containerResponse->successful()) {
            $errorBody = $containerResponse->body();
            $errorData = json_decode($errorBody, true);
            $errorMessage = $errorData['error']['message'] ?? $errorBody;
            throw new \Exception('Instagram container creation failed: ' . $errorMessage);
        }

        $containerId = $containerResponse->json('id');

        // Step 2: Publish the container
        $publishResponse = Http::post("https://graph.facebook.com/v18.0/{$instagramAccountId}/media_publish", [
            'creation_id' => $containerId,
            'access_token' => $accessToken,
        ]);

        if (!$publishResponse->successful()) {
            $errorBody = $publishResponse->body();
            $errorData = json_decode($errorBody, true);
            $errorMessage = $errorData['error']['message'] ?? $errorBody;
            throw new \Exception('Instagram publish failed: ' . $errorMessage);
        }

        $mediaId = $publishResponse->json('id');
        $postUrl = "https://www.instagram.com/p/{$mediaId}/";

        Log::info('Instagram post published', [
            'media_id' => $mediaId,
            'post_url' => $postUrl,
        ]);

        return [
            'success' => true,
            'post_id' => $mediaId,
            'post_url' => $postUrl,
            'url' => $postUrl,
            'platform' => 'instagram',
            'message' => 'Post published successfully on Instagram',
        ];
    }

    /**
     * Upload media to Facebook and return URL
     */
    private function uploadMediaToFacebook(string $accessToken, string $filePath): ?string
    {
        try {
            // Upload to Facebook's temporary storage
            $response = Http::attach('source', file_get_contents($filePath), basename($filePath))
                ->post("https://graph.facebook.com/v18.0/me/photos", [
                    'published' => false,
                    'access_token' => $accessToken,
                ]);

            if ($response->successful()) {
                $photoId = $response->json('id');
                // Return the photo URL
                return "https://graph.facebook.com/v18.0/{$photoId}/picture";
            }
        } catch (\Exception $e) {
            Log::error('Facebook media upload failed: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Check if file is a video
     */
    private function isVideoFile(?string $filePath): bool
    {
        if (!$filePath) {
            return false;
        }

        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $videoExtensions = ['mp4', 'mov', 'avi', 'mkv', 'webm', 'flv', 'wmv'];

        return in_array($extension, $videoExtensions);
    }

    public function validateCredentials(array $credentials): bool
    {
        $accessToken = $credentials['access_token'] ?? null;
        $pageId = $credentials['page_id'] ?? null;
        $instagramAccountId = $credentials['instagram_account_id'] ?? null;
        $platformType = $credentials['platform_type'] ?? 'facebook';

        // For Instagram, we need instagram_account_id
        if ($platformType === 'instagram') {
            return !empty($accessToken) && !empty($instagramAccountId);
        }

        // For Facebook, we need page_id and access_token
        return !empty($accessToken) && !empty($pageId);
    }

    public function testConnection(array $credentials): array
    {
        $pageId = $credentials['page_id'] ?? null;
        $accessToken = $credentials['access_token'] ?? null;
        $instagramAccountId = $credentials['instagram_account_id'] ?? null;
        $platformType = $credentials['platform_type'] ?? 'facebook';

        if (!$accessToken) {
            return [
                'success' => false,
                'message' => 'Facebook access token is required. Please connect via OAuth.',
            ];
        }

        try {
            if ($platformType === 'instagram' && $instagramAccountId) {
                // Test Instagram connection
                $response = Http::timeout(10)
                    ->get("https://graph.facebook.com/v18.0/{$instagramAccountId}", [
                        'fields' => 'id,username',
                        'access_token' => $accessToken,
                    ]);

                if ($response->successful()) {
                    $accountData = $response->json();
                    return [
                        'success' => true,
                        'message' => 'Connection successful! Connected to Instagram: @' . ($accountData['username'] ?? $instagramAccountId),
                        'account' => $accountData,
                    ];
                }
            } else {
                // Test Facebook Page connection
                if (!$pageId) {
                    return [
                        'success' => false,
                        'message' => 'Facebook Page ID is required',
                    ];
                }

                $response = Http::timeout(10)
                    ->get("https://graph.facebook.com/v18.0/{$pageId}", [
                        'fields' => 'id,name',
                        'access_token' => $accessToken,
                    ]);

                if ($response->successful()) {
                    $pageData = $response->json();
                    return [
                        'success' => true,
                        'message' => 'Connection successful! Connected to page: ' . ($pageData['name'] ?? $pageId),
                        'page' => $pageData,
                    ];
                }
            }

            $errorBody = $response->body();
            $errorData = json_decode($errorBody, true);
            $errorMessage = $errorData['error']['message'] ?? $errorBody;

            return [
                'success' => false,
                'message' => 'Connection failed: ' . $errorMessage,
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Connection test failed: ' . $e->getMessage(),
            ];
        }
    }
}
