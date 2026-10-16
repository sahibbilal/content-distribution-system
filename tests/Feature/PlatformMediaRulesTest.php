<?php

namespace Tests\Feature;

use App\Support\PlatformMediaRules;
use PHPUnit\Framework\TestCase;

/** What each channel accepts: text only, image, video, and the combinations */
class PlatformMediaRulesTest extends TestCase
{
    private function file(string $type, string $ext, int $size = 1000): array
    {
        return ['type' => $type, 'ext' => $ext, 'name' => "file.{$ext}", 'size' => $size];
    }

    /** @dataProvider textOnlyProvider */
    public function test_text_only_posts(string $platform, bool $allowed): void
    {
        $this->assertSame($allowed, PlatformMediaRules::issues($platform, []) === []);
    }

    public static function textOnlyProvider(): array
    {
        return [
            'x' => ['twitter', true], 'linkedin' => ['linkedin', true], 'facebook' => ['facebook', true], 'website' => ['website', true],
            'instagram' => ['instagram', false], 'youtube' => ['youtube', false], 'tiktok' => ['tiktok', false],
        ];
    }

    /** @dataProvider videoProvider */
    public function test_a_video_with_text(string $platform, bool $allowed): void
    {
        $this->assertSame($allowed, PlatformMediaRules::issues($platform, [$this->file('video', 'mp4')]) === []);
    }

    public static function videoProvider(): array
    {
        return [
            'x' => ['twitter', true], 'linkedin' => ['linkedin', true], 'facebook' => ['facebook', true],
            'instagram' => ['instagram', true], 'youtube' => ['youtube', true], 'tiktok' => ['tiktok', true],
        ];
    }

    /** @dataProvider imageProvider */
    public function test_an_image(string $platform, bool $allowed): void
    {
        $this->assertSame($allowed, PlatformMediaRules::issues($platform, [$this->file('image', 'jpg')]) === []);
    }

    public static function imageProvider(): array
    {
        return [
            'x' => ['twitter', true], 'linkedin' => ['linkedin', true], 'facebook' => ['facebook', true], 'instagram' => ['instagram', true],
            'youtube' => ['youtube', false], 'tiktok' => ['tiktok', true],
        ];
    }

    public function test_x_cannot_mix_images_and_a_video_or_take_two_videos(): void
    {
        $this->assertNotEmpty(PlatformMediaRules::issues('twitter', [$this->file('image', 'jpg'), $this->file('video', 'mp4')]));
        $this->assertNotEmpty(PlatformMediaRules::issues('twitter', [$this->file('video', 'mp4'), $this->file('video', 'mp4')]));
        $this->assertSame([], PlatformMediaRules::issues('twitter', array_fill(0, 4, $this->file('image', 'png'))));
    }

    public function test_linkedin_video_must_be_mp4_and_within_200mb(): void
    {
        $this->assertNotEmpty(PlatformMediaRules::issues('linkedin', [$this->file('video', 'mov')]));
        $this->assertNotEmpty(PlatformMediaRules::issues('linkedin', [$this->file('video', 'mp4', 201 * 1048576)]));
    }

    public function test_audio_is_not_accepted_by_social_channels(): void
    {
        foreach (['twitter', 'linkedin', 'facebook', 'instagram', 'youtube', 'tiktok'] as $platform) {
            $this->assertNotEmpty(PlatformMediaRules::issues($platform, [$this->file('audio', 'mp3')]), $platform);
        }
    }
}
