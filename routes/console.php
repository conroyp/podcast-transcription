<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule podcast feed checking for new episodes
Schedule::command('podcast:fetch --process')
    ->hourly()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/podcast-fetch.log'));
