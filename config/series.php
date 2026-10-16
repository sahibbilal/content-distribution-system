<?php

return [

    // Series A: one episode per day, researched at research_at, posted at publish_at (both in `timezone`).
    // These are only the starting values: every one of them can be changed on Admin > Content pipeline,
    // and what is saved there wins (see App\Support\PipelineSettings).
    'a' => [
        'timezone' => 'Asia/Karachi',
        'research_at' => '17:00',
        'publish_at' => '21:00',
        // if the episode is not ready this many minutes before its publish time, the day is skipped
        'skip_before_minutes' => 15,
        'shorts' => 3,
        // the account that owns the daily episodes (defaults to the first admin)
        'user_id' => null,
        // platform types per output
        'platforms' => [
            'long' => ['youtube', 'facebook'],
            'short' => ['youtube', 'facebook', 'instagram', 'tiktok'],
        ],
        // minutes an episode may sit in a worker step before the watchdog fails it
        'limits' => ['researching' => 30, 'voicing' => 10, 'editing' => 60],
    ],

];
