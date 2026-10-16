<?php

namespace App\Console\Commands;

use App\Jobs\StartResearchJob;
use App\Models\Episode;
use App\Models\User;
use Illuminate\Console\Command;

class StartDailyEpisode extends Command
{
    protected $signature = 'episodes:start-daily';
    protected $description = "Create today's Series A episode and start its research";

    public function handle()
    {
        $user = config('series.a.user_id')
            ? User::find(config('series.a.user_id'))
            : User::where('is_admin', true)->orderBy('id')->first();

        if (!$user) {
            $this->error('No user owns Series A (set SERIES_A_USER_ID or create an admin).');
            return self::FAILURE;
        }

        [$episode, $created] = Episode::open($user, Episode::today());

        if (!$created) {
            $this->info("Episode {$episode->id} already exists for today ({$episode->status}).");
            return self::SUCCESS;
        }

        StartResearchJob::dispatch($episode->id);
        $this->info("Episode {$episode->id} created and research started.");

        return self::SUCCESS;
    }
}
