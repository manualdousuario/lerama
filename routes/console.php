<?php

use Illuminate\Support\Facades\Schedule;

// Runs under a `schedule:run` loop, supervised by s6 in the Docker image.
// feed:process picks which feeds are due via the next_fetch_at column; feeds
// are refetched daily, so every five minutes (15 per run) keeps up with ease.
Schedule::command('feed:process')
    ->everyFiveMinutes()
    ->withoutOverlapping();

Schedule::command('image:extract 200')
    ->everyFiveMinutes()
    ->withoutOverlapping();

Schedule::command('feed:check-status')
    ->daily()
    ->withoutOverlapping();

// Re-warms the hot keys (home, feeds list, taxonomies) so a flush — from the
// image extractor or an admin write — never leaves visitors with a cold cache
// for long. The feed processor already warms right after its own flush.
Schedule::command('cache:warm')
    ->everyFifteenMinutes()
    ->withoutOverlapping();
