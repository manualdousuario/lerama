<?php

namespace App\Console\Commands;

use App\Services\Feeds\FeedImporter;
use App\Services\FeedTypeDetector;
use App\Services\ItemCountService;
use Illuminate\Console\Command;

class ImportFeeds extends Command
{
    protected $signature = 'feed:import {csv : Path to the CSV file (columns: url, name, tags, category)}';

    protected $description = 'Import feeds from a semicolon-separated CSV file';

    public function handle(FeedImporter $importer): int
    {
        $csvPath = $this->argument('csv');

        $importer = new FeedImporter(
            app(FeedTypeDetector::class),
            app(ItemCountService::class),
            fn (string $msg) => $this->line($msg),
        );

        try {
            $summary = $importer->import($csvPath);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Import completed! Successful: {$summary['success']}, Errors: {$summary['errors']}");

        return self::SUCCESS;
    }
}
