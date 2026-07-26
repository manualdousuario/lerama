<?php

namespace App\Console\Commands;

class CheckFeedsStatus extends ProcessFeeds
{
    protected $signature = 'feed:check-status';

    protected $description = 'Check paused feeds: retry after 24h, mark offline and notify after 72h';

    public function handle(): int
    {
        $this->makeProcessor()->checkPausedFeeds();

        return self::SUCCESS;
    }
}
