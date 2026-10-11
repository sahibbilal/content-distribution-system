<?php

namespace App\Support;

use App\Models\Media;
use App\Models\Platform;

/**
 * What each platform can publish, mirroring what its service in App\Services\Platforms really supports.
 * Used to reject a bad combination (e.g. an image on YouTube) before anything is published.
 *
 * Rule keys: media (required|optional), kinds (kind => extensions / max_files / max_bytes),
 * max_total (files per post), single_kind (images and videos can't be mixed).
 */
class PlatformMediaRules
{
    private const RULES = [
        'youtube' => [
            'label' => 'YouTube',
            'media' => 'required',
            'max_total' => 1,
            'kinds' => ['video' => []],
        ],
        'tiktok' => [
            'label' => 'TikTok',
            'media' => 'required',
            'single_kind' => true,
            'kinds' => [
                'video' => ['extensions' => ['mp4', 'mov', 'webm'], 'max_files' => 1],
                'image' => ['extensions' => ['jpg', 'webp'], 'max_files' => 35],
            ],
        ],
        'instagram' => [
            'label' => 'Instagram',
            'media' => 'required',
            'max_total' => 1,
            'kinds' => ['image' => [], 'video' => []],
        ],
        'facebook' => [
            'label' => 'Facebook',
            'media' => 'optional',
            'max_total' => 1,
            'kinds' => ['image' => [], 'video' => []],
        ],
        'linkedin' => [
            'label' => 'LinkedIn',
            'media' => 'optional',
            'max_total' => 1,
            'kinds' => ['image' => []],
        ],
        'twitter' => [
            'label' => 'X',
            'media' => 'optional',
            'max_total' => 4,
            'kinds' => ['image' => ['extensions' => ['jpg', 'png', 'webp', 'gif'], 'max_bytes' => 5 * 1024 * 1024]],
        ],
    ];

    private const NOUNS = ['image' => 'image', 'video' => 'video', 'audio' => 'audio file'];

    /**
     * Problems for every selected platform, keyed by platform id (platforms with no problem are left out).
     * Only the user's own platforms and media are considered.
     */
    public static function forPost(int $userId, array $platformIds, array $mediaIds): array
    {
        $files = Media::whereIn('id', $mediaIds)->where('user_id', $userId)->get()
            ->map(fn (Media $m) => [
                'type' => $m->type,
                'ext' => strtolower(pathinfo($m->filename, PATHINFO_EXTENSION)),
                'name' => $m->original_filename,
                'size' => (int) $m->size,
            ])->all();

        $problems = [];
        foreach (Platform::whereIn('id', $platformIds)->where('user_id', $userId)->get() as $platform) {
            $messages = self::issues($platform->platform_type, $files);
            if ($messages) {
                $problems[$platform->id] = $messages;
            }
        }

        return $problems;
    }

    /** @param array<int, array{type: string, ext: string, name: string, size: int}> $files */
    public static function issues(string $platformType, array $files): array
    {
        $rule = self::RULES[$platformType] ?? null;
        if (!$rule) {
            return [];
        }

        $label = $rule['label'];
        $supported = array_keys($rule['kinds']);
        $issues = [];

        if (!$files) {
            return $rule['media'] === 'required'
                ? ["{$label} needs " . self::listKinds($supported, 'a ') . ' to publish.']
                : [];
        }

        // Kinds this platform can't post at all (e.g. an image on YouTube, audio anywhere)
        $byKind = [];
        foreach ($files as $file) {
            $byKind[$file['type']][] = $file;
        }
        foreach ($byKind as $kind => $items) {
            if (!in_array($kind, $supported, true)) {
                $names = implode(', ', array_column($items, 'name'));
                $noun = self::NOUNS[$kind] ?? $kind;
                $issues[] = "{$label} can't post {$noun}" . (count($items) > 1 ? 's' : '') . " ({$names}). It only supports "
                    . self::listKinds($supported) . '. Remove the file or deselect ' . $label . '.';
            }
        }
        if ($issues) {
            return $issues;
        }

        if (!empty($rule['single_kind']) && count($byKind) > 1) {
            return ["{$label} can't mix images and videos in one post."];
        }

        foreach ($byKind as $kind => $items) {
            $kindRule = $rule['kinds'][$kind];
            $noun = self::NOUNS[$kind];

            if (!empty($kindRule['extensions'])) {
                $allowed = strtoupper(implode(', ', $kindRule['extensions']));
                foreach ($items as $item) {
                    if (!in_array($item['ext'], $kindRule['extensions'], true)) {
                        $issues[] = "{$label} only accepts {$allowed} {$noun}s ({$item['name']} is ." . $item['ext'] . ').';
                    }
                }
            }
            if (!empty($kindRule['max_bytes'])) {
                foreach ($items as $item) {
                    if ($item['size'] > $kindRule['max_bytes']) {
                        $issues[] = "{$label} {$noun}s must be " . round($kindRule['max_bytes'] / 1048576) . "MB or smaller ({$item['name']} is " . round($item['size'] / 1048576, 1) . 'MB).';
                    }
                }
            }
            if (!empty($kindRule['max_files']) && count($items) > $kindRule['max_files']) {
                $issues[] = "{$label} accepts at most {$kindRule['max_files']} {$noun}" . ($kindRule['max_files'] > 1 ? 's' : '') . ' per post (you added ' . count($items) . ').';
            }
        }

        if (!empty($rule['max_total']) && count($files) > $rule['max_total']) {
            $issues[] = "{$label} accepts at most {$rule['max_total']} file" . ($rule['max_total'] > 1 ? 's' : '') . ' per post (you added ' . count($files) . ').';
        }

        return $issues;
    }

    /** "videos", "images or videos", or with $article = 'a ': "an image or a video" */
    private static function listKinds(array $kinds, string $article = ''): string
    {
        $words = array_map(
            fn ($k) => $article ? self::article(self::NOUNS[$k]) . self::NOUNS[$k] : self::NOUNS[$k] . 's',
            $kinds
        );

        return implode(' or ', $words);
    }

    private static function article(string $noun): string
    {
        return in_array($noun[0], ['a', 'e', 'i', 'o', 'u'], true) ? 'an ' : 'a ';
    }
}
