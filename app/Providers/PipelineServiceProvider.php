<?php

namespace App\Providers;

use App\Support\PipelineSettings;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;

class PipelineServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        PipelineSettings::apply();

        // a queue worker boots once and runs for days, so it re-reads the saved settings before each job
        Queue::before(fn () => PipelineSettings::apply());
    }
}
