<?php

namespace App\Services;

use App\Services\Platforms\FacebookService;
use App\Services\Platforms\InstagramService;
use App\Services\Platforms\LinkedInService;
use App\Services\Platforms\YouTubeService;
use App\Services\Platforms\TikTokService;
use App\Services\Platforms\TwitterService;
use App\Services\Platforms\WebsiteService;

class PlatformServiceFactory
{
    public static function create(string $platformType): PlatformServiceInterface
    {
        return match ($platformType) {
            'facebook' => new FacebookService(),
            'linkedin' => new LinkedInService(),
            'youtube' => new YouTubeService(),
            'tiktok' => new TikTokService(),
            'twitter' => new TwitterService(),
            'instagram' => new InstagramService(),
            'website' => new WebsiteService(),
            default => throw new \InvalidArgumentException("Unsupported platform: {$platformType}"),
        };
    }
}

