<?php

namespace App\Console\Commands;

use App\Services\CacheWarmer;
use Illuminate\Console\Command;

class WarmCache extends Command
{
    protected $signature = 'cache:warm';

    protected $description = 'Warm the hottest cache keys (home, categories, tags, feeds)';

    public function handle(CacheWarmer $warmer): int
    {
        $this->info('Warming cache...');

        $summary = $warmer->warmImportant();

        $this->info("  ✓ {$summary['categories']} categories");
        $this->info("  ✓ {$summary['tags']} tags");
        $this->info("  ✓ {$summary['feeds_dropdown']} feeds (dropdown)");
        $this->info("  ✓ {$summary['home']['items_count']} home items (total: {$summary['home']['total_count']})");
        $this->info("  ✓ {$summary['top_feeds']} top feeds");

        $this->info('✓ Cache warming completed');

        return self::SUCCESS;
    }
}
