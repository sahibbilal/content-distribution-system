<?php

namespace App\Console\Commands;

use App\Jobs\PublishPostJob;
use App\Models\Schedule;
use Illuminate\Console\Command;

class ProcessScheduledPosts extends Command
{
    protected $signature = 'posts:process-scheduled';
    protected $description = 'Process scheduled posts that are due';

    public function handle()
    {
        $schedules = Schedule::where('status', 'pending')
            ->where('scheduled_at', '<=', now())
            ->with('post')
            ->get();

        foreach ($schedules as $schedule) {
            $schedule->update(['status' => 'processing']);
            
            PublishPostJob::dispatch($schedule->post);
        }

        $this->info("Processed {$schedules->count()} scheduled posts");
    }
}

