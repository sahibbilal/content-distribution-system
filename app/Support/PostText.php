<?php

namespace App\Support;

use App\Models\Platform;
use App\Models\Post;

/**
 * The text each channel really receives: the post text (or that channel's own version) followed by the
 * hashtags (the shared ones, or that channel's own). resources/js/postText.js does the same on the page,
 * so the character counters match what is published.
 */
class PostText
{
    public const X_FREE_LIMIT = 280;
    public const X_PREMIUM_LIMIT = 25000;

    /** Trimmed hashtags without "#" or spaces, unique (case-insensitive), letters/digits/underscore of any language */
    public static function cleanHashtags(?array $tags): array
    {
        $clean = [];
        foreach ($tags ?? [] as $tag) {
            $tag = preg_replace('/[^\p{L}\p{N}_]+/u', '', ltrim(trim((string) $tag), '#'));
            if ($tag !== '' && mb_strlen($tag) <= 60 && !in_array(mb_strtolower($tag), array_map('mb_strtolower', $clean), true)) {
                $clean[] = $tag;
            }
        }

        return array_slice($clean, 0, 30);
    }

    /** "text\n\n#a #b" (just the hashtags when there is no text) */
    public static function compose(string $content, array $hashtags): string
    {
        $content = trim($content);
        $line = implode(' ', array_map(fn ($t) => '#' . $t, self::cleanHashtags($hashtags)));

        return trim($content . ($content !== '' && $line !== '' ? "\n\n" : '') . $line);
    }

    /** The final text for one channel of a post */
    public static function forPlatform(string $content, array $hashtags, array $overrides, int|string $platformId): string
    {
        $own = $overrides[(string) $platformId] ?? [];

        return self::compose($own['content'] ?? $content, $own['hashtags'] ?? $hashtags);
    }

    public static function forPost(Post $post, int|string $platformId): string
    {
        return self::forPlatform($post->content, $post->hashtags ?? [], $post->platform_overrides ?? [], $platformId);
    }

    /** Character limit enforced before publishing (null: the platform's own service checks it) */
    public static function limit(Platform $platform): ?int
    {
        return match ($platform->platform_type) {
            'twitter' => ($platform->credentials['account_tier'] ?? 'free') === 'premium' ? self::X_PREMIUM_LIMIT : self::X_FREE_LIMIT,
            'instagram' => 2200,
            'linkedin' => 3000,
            default => null,
        };
    }

    /** Cleaned per-channel versions: only the given platforms, only what differs from nothing */
    public static function cleanOverrides(?array $overrides, array $platformIds): array
    {
        $clean = [];
        foreach ($overrides ?? [] as $id => $own) {
            if (!in_array((int) $id, array_map('intval', $platformIds), true) || !is_array($own)) {
                continue;
            }
            $entry = [];
            if (isset($own['content']) && trim((string) $own['content']) !== '') {
                $entry['content'] = trim((string) $own['content']);
            }
            // an empty list is a real choice here: "no hashtags on this channel"
            if (array_key_exists('hashtags', $own) && is_array($own['hashtags'])) {
                $entry['hashtags'] = self::cleanHashtags($own['hashtags']);
            }
            if ($entry) {
                $clean[(string) $id] = $entry;
            }
        }

        return $clean;
    }
}
