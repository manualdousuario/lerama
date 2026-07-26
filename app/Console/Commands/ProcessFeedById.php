<?php

namespace App\Console\Commands;

class ProcessFeedById extends ProcessFeeds
{
    protected $signature = 'feed:id {id : ID of the feed to process}';

    protected $description = 'Process a single feed by ID';

    public function handle(): int
    {
        $id = $this->argument('id');

        if (! is_numeric($id)) {
            $this->error('Feed ID is required and must be a number');

            return self::FAILURE;
        }

        $this->makeProcessor()->process((int) $id);

        return self::SUCCESS;
    }
}
