<?php

use Illuminate\Support\Facades\Schedule;

// Runs under `schedule:work`, supervised by s6 in the Docker image.
// feed:process picks which feeds are due via the next_fetch_at column.
Schedule::command('feed:process')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('image:extract 200')
    ->everyFiveMinutes()
    ->withoutOverlapping();

Schedule::command('feed:check-status')
    ->daily()
    ->withoutOverlapping();

// Re-warms the hot keys (home, feeds list, taxonomies) so a flush — from the
// processor or an admin write — never leaves the next visitor with a cold
// cache for long. Cheap when the cache is already warm.
Schedule::command('cache:warm')
    ->everyFiveMinutes()
    ->withoutOverlapping();
