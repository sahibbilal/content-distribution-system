<?php

namespace App\Services;

use App\Models\AiIntegration;
use App\Models\Media;
use App\Models\Platform;
use App\Support\PlatformMediaRules;
use App\Support\PostText;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Writes a post draft from a request with the Anthropic API (the key is saved in Admin > AI integrations).
 * It can search the web, look at attached images, and write a separate short version for X.
 */
class AiDraftService
{
    /** Image types the API accepts, and how many / how large (per image) we send */
    private const IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    private const MAX_IMAGES = 4;
    private const MAX_IMAGE_BYTES = 5 * 1024 * 1024;

    /**
     * @param  Platform[]  $platforms  the channels the post is for
     * @param  Media[]  $images  attached images the AI should look at
     * @return array{long: string, short: ?string, hashtags: array, hashtags_short: array, image_prompt: ?string, image_check: ?array, notes: array}
     */
    public function draft(string $request, array $platforms, bool $hashtags, bool $webSearch, array $images, bool $wantImage, string $rules = ''): array
    {
        $integration = AiIntegration::where('provider', 'anthropic')->where('enabled', true)->first();
        if (!$integration?->api_key) {
            throw new \RuntimeException('Connect and enable Anthropic in Admin > AI integrations first, or choose the Claude worker.');
        }

        $xLimit = $this->xLimit($platforms);
        $notes = [];
        $system = $this->system($platforms, $hashtags, $wantImage, $xLimit, (bool) $images, $rules);
        $result = $this->ask($integration, $system, $request, $images, $webSearch, $notes);

        // the short text and its hashtags together must fit X; ask once more if they do not
        if ($xLimit && ($over = $this->overBy($result, $xLimit))) {
            $keep = max(20, mb_strlen($result['short']) - $over - 5);
            $retry = $this->ask($integration, $system,
                $request . "\n\nYour previous \"short\" was {$over} characters too long with its hashtags. Make \"short\" at most {$keep} characters.",
                $images, false, $notes);
            $result = $retry;
        }
        if ($xLimit && $this->overBy($result, $xLimit)) {
            $notes[] = 'The short text for X is still over the limit. Shorten it by hand.';
        }

        $result['notes'] = $notes;

        return $result;
    }

    private function ask(AiIntegration $integration, string $system, string $request, array $images, bool $webSearch, array &$notes): array
    {
        $content = [];
        foreach (array_slice($images, 0, self::MAX_IMAGES) as $media) {
            $file = $this->imageBlock($media);
            if ($file) {
                $content[] = $file;
            } else {
                $notes[] = "The AI could not look at {$media->original_filename} (use a JPG, PNG, GIF or WebP up to 5 MB).";
            }
        }
        $content[] = ['type' => 'text', 'text' => $request];

        $body = [
            'model' => $integration->model ?: 'claude-sonnet-5-5',
            'max_tokens' => 2500,
            'system' => $system,
            'messages' => [['role' => 'user', 'content' => $content]],
        ];
        $send = fn (array $b) => Http::timeout(120)->withHeaders(['x-api-key' => $integration->api_key, 'anthropic-version' => '2023-06-01'])
            ->post('https://api.anthropic.com/v1/messages', $b);

        $response = null;
        if ($webSearch) {
            $response = $send($body + ['tools' => [['type' => 'web_search_20250305', 'name' => 'web_search', 'max_uses' => 3]]]);
            if ($response->status() === 400) {
                $notes[] = 'Web search was not available, so the draft was written without it.';
                $response = null;
            }
        }
        $response ??= $send($body);

        if (!$response->successful()) {
            throw new \RuntimeException($response->json('error.message') ?: 'The AI provider rejected the request (HTTP ' . $response->status() . ').');
        }

        $text = collect($response->json('content') ?? [])->where('type', 'text')->pluck('text')->implode("\n");

        return $this->parse($text);
    }

    private function system(array $platforms, bool $hashtags, bool $wantImage, ?int $xLimit, bool $hasImages, string $rules = ''): string
    {
        $channels = collect($platforms)->map(fn (Platform $p) => '- ' . PlatformMediaRules::label($p->platform_type) . ($p->platform_type === 'twitter' ? " (at most {$xLimit} characters, counting spaces and hashtags)" : ''))->implode("\n") ?: '- any social channel';

        $rulesBlock = $rules !== '' ? "\n\nThe user's rulebook. Follow it in everything you write:\n\n{$rules}\n" : '';

        return trim("
You write social media posts for the user. Reply with ONLY one JSON object, no markdown fences and no other text.{$rulesBlock}
Write in the language of the request (an Urdu request gets an Urdu post). Be accurate: do not invent facts; if you searched the web, use only what the sources say.

Channels for this post:
{$channels}

JSON keys:
- \"long\": the full post for the channels with room (Facebook, LinkedIn, Instagram, website): 3 to 8 short lines or sentences, no hashtags inside the text.
- \"short\": " . ($xLimit ? "a separate, shorter post for X. The short text plus a blank line plus its hashtags (#tag #tag) must be at most {$xLimit} characters in total. Not a cut-off copy of \"long\": write it to stand alone." : 'null (X is not selected).') . "
- \"hashtags\": " . ($hashtags ? 'an array of 3 to 8 relevant hashtags for the long post, written without the # sign.' : 'an empty array (the user did not ask for hashtags).') . "
- \"hashtags_short\": " . ($hashtags && $xLimit ? 'an array of at most 3 hashtags for X, without the # sign.' : 'an empty array.') . "
- \"image_prompt\": " . ($wantImage ? 'a detailed English prompt for an image generator: subject, style, colours, composition, no text or logos in the picture.' : 'null.') . "
- \"image_check\": " . ($hasImages ? '{"ok": true or false, "note": "one sentence"}. Look at every attached image. ok is false when an image is unclear, unsuitable, or does not fit the request; say why in the note. Write the post about what the images show and what was asked.' : 'null.'));
    }

    private function parse(string $text): array
    {
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        $data = $start !== false && $end > $start ? json_decode(substr($text, $start, $end - $start + 1), true) : null;
        if (!is_array($data) || !isset($data['long'])) {
            throw new \RuntimeException('The AI answer could not be read. Please try again.');
        }

        return [
            'long' => trim((string) $data['long']),
            'short' => isset($data['short']) ? trim((string) $data['short']) : null,
            'hashtags' => PostText::cleanHashtags($data['hashtags'] ?? []),
            'hashtags_short' => PostText::cleanHashtags($data['hashtags_short'] ?? []),
            'image_prompt' => !empty($data['image_prompt']) ? (string) $data['image_prompt'] : null,
            'image_check' => is_array($data['image_check'] ?? null) ? ['ok' => (bool) ($data['image_check']['ok'] ?? true), 'note' => (string) ($data['image_check']['note'] ?? '')] : null,
        ];
    }

    /** Characters the short text is over the X limit by (0 when it fits or there is no short text) */
    private function overBy(array $result, int $limit): int
    {
        if (!$result['short']) {
            return 0;
        }

        return max(0, mb_strlen(PostText::compose($result['short'], $result['hashtags_short'])) - $limit);
    }

    /** The smallest limit among the selected X accounts (null when X is not selected) */
    private function xLimit(array $platforms): ?int
    {
        $limits = collect($platforms)->where('platform_type', 'twitter')->map(fn (Platform $p) => PostText::limit($p));

        return $limits->isEmpty() ? null : $limits->min();
    }

    private function imageBlock(Media $media): ?array
    {
        if (!in_array($media->mime_type, self::IMAGE_MIMES, true) || $media->size > self::MAX_IMAGE_BYTES || !Storage::disk('public')->exists($media->path)) {
            return null;
        }

        return ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $media->mime_type, 'data' => base64_encode(Storage::disk('public')->get($media->path))]];
    }
}
