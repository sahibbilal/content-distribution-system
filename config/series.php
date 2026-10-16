<?php

return [

    // Series A: one episode per day, researched at research_at, posted at publish_at (both in `timezone`)
    'a' => [
        'timezone' => env('SERIES_TIMEZONE', 'Asia/Karachi'),
        'research_at' => env('SERIES_A_RESEARCH_AT', '17:00'),
        'publish_at' => env('SERIES_A_PUBLISH_AT', '21:00'),
        // if the episode is not ready this many minutes before its publish time, the day is skipped
        'skip_before_minutes' => 15,
        'shorts' => 3,
        // the account that owns the daily episodes (defaults to the first admin)
        'user_id' => env('SERIES_A_USER_ID'),
        // platform types per output. X and LinkedIn are left out: they can't publish video yet.
        'platforms' => [
            'long' => ['youtube', 'facebook'],
            'short' => ['youtube', 'facebook', 'instagram', 'tiktok'],
        ],
        // minutes an episode may sit in a worker step before the watchdog fails it
        'limits' => ['researching' => 30, 'voicing' => 10, 'editing' => 60],
    ],

];
