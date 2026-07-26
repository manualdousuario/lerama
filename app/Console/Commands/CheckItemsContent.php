<?php

namespace App\Console\Commands;

class CheckItemsContent extends ProcessFeeds
{
    protected $signature = 'feed:check-real-content';

    protected $description = 'Reclassify visibility of every item (subscriber-only, password-protected, etc.)';

    public function handle(): int
    {
        $this->makeProcessor()->checkItemsContent();

        return self::SUCCESS;
    }
}
