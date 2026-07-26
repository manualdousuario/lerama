<?php

namespace App\Console\Commands;

use App\Services\Feeds\FeedProcessor;
use App\Services\ItemCountService;
use App\Services\ProxyService;
use Illuminate\Console\Command;

class ProcessFeeds extends Command
{
    protected $signature = 'feed:process';

    protected $description = 'Process feeds that are due (next_fetch_at elapsed), up to FEED_MAX_PER_RUN per run';

    public function handle(): int
    {
        $this->makeProcessor()->process();

        return self::SUCCESS;
    }

    protected function makeProcessor(): FeedProcessor
    {
        return new FeedProcessor(
            app(ProxyService::class),
            app(ItemCountService::class),
            fn (string $msg) => $this->line($msg),
        );
    }
}
