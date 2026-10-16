<?php

use App\Jobs\EpisodeWatchdogJob;
use App\Jobs\QueueHeartbeatJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Schedule::command('posts:process-scheduled')->everyMinute();

// Series A: research at 17:00 (series timezone), and a watchdog for stuck or late episodes
Schedule::command('episodes:start-daily')
    ->dailyAt(config('series.a.research_at'))
    ->timezone(config('series.a.timezone'));
Schedule::job(new EpisodeWatchdogJob)->everyFifteenMinutes();

// Heartbeats: the Admin > Content pipeline page shows whether the cron and the queue worker are alive
Schedule::call(fn () => Cache::put('pipeline:scheduler-ran', now()->timestamp, 86400))->name('scheduler-heartbeat')->everyMinute();
Schedule::job(new QueueHeartbeatJob)->everyFiveMinutes();
