<?php

namespace App\Services\Platforms;

use App\Services\PlatformServiceInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Publishes to the user's own website/blog through its REST API (see docs/website-blog-api.md).
 * Credentials: api_url (base URL, e.g. https://bilalmahmood.dev/api) and api_token (bearer token).
 */
class WebsiteService implements PlatformServiceInterface
{
    private const MAX_TITLE = 150;

    /**
     * Upload each media file, then create the post as published.
     * The first line of the text becomes the title; $contentHtml (the editor's cleaned HTML) is the body when present.
     */
    public function publish(string $content, array $credentials, array $media = [], ?string $contentHtml = null): array
    {
        if (!$this->validateCredentials($credentials)) {
            throw new \Exception('Website credentials are incomplete. Please enter the API URL and bearer token.');
        }

        $content = trim($content);
        if ($content === '') {
            throw new \Exception('A website post needs some text.');
        }

        $base = $this->baseUrl($credentials);

        try {
            [$title, $body] = $this->titleAndBody($content, $contentHtml);

            $uploaded = array_map(fn ($item) => [
                'type' => $item['type'],
                'url' => $this->uploadMedia($base, $credentials['api_token'], $item),
            ], $media);
            $cover = collect($uploaded)->firstWhere('type', 'image')['url'] ?? null;

            $response = Http::withToken($credentials['api_token'])
                ->acceptJson()
                ->timeout(30)
                ->post("{$base}/posts", [
                    'title' => $title,
                    'content' => $body,
                    'excerpt' => mb_substr(trim(preg_replace('/\s+/', ' ', $content)), 0, 200),
                    'status' => 'published',
                    'cover_image' => $cover,
                    'media' => $uploaded,
                ]);

            $json = $response->json() ?? [];
            $postId = $json['id'] ?? null;

            if (!$response->successful() || !$postId) {
                $message = $json['message'] ?? $response->body();
                throw new \Exception("Website API error ({$response->status()}): {$message}");
            }

            return [
                'success' => true,
                'post_id' => (string) $postId,
                'url' => $json['url'] ?? null,
                'platform' => 'website',
                'message' => 'Post published on your website.',
            ];
        } catch (\Exception $e) {
            Log::error('Website publish failed: ' . $e->getMessage(), [
                'platform_type' => 'website',
                'exception' => get_class($e),
            ]);
            throw $e;
        }
    }

    /** First non-empty line is the title; the body is the HTML (or the remaining lines as paragraphs) without that title. */
    private function titleAndBody(string $content, ?string $contentHtml): array
    {
        $lines = preg_split('/\R/', $content);
        $first = trim(array_shift($lines));
        $title = mb_substr($first, 0, self::MAX_TITLE);

        if ($contentHtml !== null && trim($contentHtml) !== '') {
            // The editor's first block usually is the title line; drop it so the page doesn't show it twice
            if ($title === $first && preg_match('#^\s*<(p|h[1-6])\b[^>]*>(.*?)</\1>#is', $contentHtml, $m)
                && trim(html_entity_decode(strip_tags($m[2]))) === $first) {
                $contentHtml = substr($contentHtml, strlen($m[0]));
            }

            return [$title, trim($contentHtml) !== '' ? trim($contentHtml) : '<p>' . e($first) . '</p>'];
        }

        $paragraphs = array_filter(array_map('trim', $lines), fn ($l) => $l !== '');
        $body = $paragraphs
            ? implode('', array_map(fn ($l) => '<p>' . e($l) . '</p>', $paragraphs))
            : '<p>' . e($first) . '</p>';

        return [$title, $body];
    }

    /** Upload one file from the public disk to {base}/media and return its public URL. */
    private function uploadMedia(string $base, string $token, array $item): string
    {
        $disk = Storage::disk('public'); // MediaController stores uploads on the public disk
        $path = $item['path'] ?? '';

        if (!$disk->exists($path)) {
            throw new \Exception('Media file not found: ' . basename($path));
        }

        $response = Http::withToken($token)
            ->acceptJson()
            ->timeout(300)
            ->attach('file', $disk->get($path), basename($path))
            ->post("{$base}/media", ['type' => $item['type']]);

        $url = $response->json('url');
        if (!$response->successful() || !$url) {
            $message = $response->json('message') ?? $response->body();
            throw new \Exception("Website media upload failed ({$response->status()}) for " . basename($path) . ": {$message}");
        }

        return $url;
    }

    private function baseUrl(array $credentials): string
    {
        return rtrim(trim($credentials['api_url']), '/');
    }

    public function validateCredentials(array $credentials): bool
    {
        $url = trim($credentials['api_url'] ?? '');

        return !empty($credentials['api_token'])
            && filter_var($url, FILTER_VALIDATE_URL) !== false
            && in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true);
    }

    public function testConnection(array $credentials): array
    {
        if (!$this->validateCredentials($credentials)) {
            return ['success' => false, 'message' => 'Enter a valid API URL (https://...) and bearer token.'];
        }

        try {
            $response = Http::withToken($credentials['api_token'])
                ->acceptJson()
                ->timeout(10)
                ->get($this->baseUrl($credentials) . '/me');

            if ($response->successful()) {
                $name = $response->json('name');

                return [
                    'success' => true,
                    'message' => 'Connection successful!' . ($name ? " Authenticated as: {$name}" : ''),
                ];
            }

            return [
                'success' => false,
                'message' => 'Connection failed with status ' . $response->status() . ': ' . ($response->json('message') ?? $response->body()),
                'status' => $response->status(),
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Connection test failed: ' . $e->getMessage()];
        }
    }
}
