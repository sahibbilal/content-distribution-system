<?php

// Default public site content. Anything an admin saves from the Admin page overrides these values.
return [
    'contact_email' => 'support@bilalmahmood.dev',

    'hero' => [
        'title' => 'Write once. Publish everywhere.',
        'subtitle' => 'Content Distribution System lets you post text, images and videos to Facebook, LinkedIn, YouTube and TikTok from one place, now or on a schedule.',
    ],

    'features' => [
        ['icon' => '🔗', 'title' => 'Multi-platform posting', 'text' => 'Send one post to several platforms at once instead of repeating yourself.'],
        ['icon' => '🖼️', 'title' => 'Text, images, video', 'text' => 'Attach media with each platform receiving the format it supports.'],
        ['icon' => '⏰', 'title' => 'Scheduled posting', 'text' => 'Pick a date and time. Scheduled posts are published automatically by a background queue.'],
        ['icon' => '🔌', 'title' => 'Platform management', 'text' => 'Connect, test and disconnect your social accounts from a single page.'],
        ['icon' => '📜', 'title' => 'Post history', 'text' => 'See what was published, where, and whether each platform succeeded or failed.'],
        ['icon' => '🔒', 'title' => 'Secure by design', 'text' => 'Token-based authentication, and platform credentials are stored encrypted.'],
    ],

    'platforms' => ['Facebook', 'LinkedIn', 'YouTube', 'TikTok'],

    'plans' => [
        [
            'name' => 'Standard',
            'price' => '500',
            'featured' => false,
            'features' => [
                'Post to all connected platforms',
                'Facebook, LinkedIn, YouTube and TikTok',
                'Text, image and video posts',
                'Scheduled posting and post history',
                'You write your own content',
            ],
        ],
        [
            'name' => 'Standard + AI',
            'price' => '2,000',
            'featured' => true,
            'features' => [
                'Everything in Standard',
                'AI-generated content (coming soon)',
            ],
        ],
    ],

    // e.g. ['name' => 'JazzCash', 'details' => [['label' => 'Account title', 'value' => '...']]]
    'payment_methods' => [],

    // Admin settings. registration_open is public so the register page can say it is closed.
    'registration_open' => true,
    // true: uploaded media is removed once a post is published; false: kept in each user's media library
    'delete_media_after_publish' => true,
];
