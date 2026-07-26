<?php

use Illuminate\Support\Facades\Schedule;

// Runs under `schedule:work`, supervised by s6 in the Docker image.
// feed:process picks which feeds are due via the next_fetch_at column.
Schedule::command('feed:process')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('feed:check-status')
    ->daily()
    ->withoutOverlapping();
