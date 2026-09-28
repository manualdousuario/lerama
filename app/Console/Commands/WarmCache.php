<?php

namespace App\Console\Commands;

use App\Services\CacheWarmer;
use Illuminate\Console\Command;
use Illuminate\Database\DetectsLostConnections;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

class WarmCache extends Command
{
    use DetectsLostConnections;

    protected $signature = 'cache:warm';

    protected $description = 'Warm the hottest cache keys (home, feeds list, categories, tags, thumbnails)';

    public function handle(CacheWarmer $warmer): int
    {
        $this->info('Warming cache...');

        try {
            $summary = $warmer->warmImportant();
        } catch (QueryException $e) {
            if (! $this->causedByLostConnection($e->getPrevious() ?? $e)) {
                throw $e;
            }

            $this->warn('Database unavailable, skipping cache warm');
            Log::warning('cache:warm skipped, database unavailable', ['error' => $e->getMessage()]);

            return self::FAILURE;
        }

        $this->info("  ✓ {$summary['categories']} categories");
        $this->info("  ✓ {$summary['tags']} tags");
        $this->info("  ✓ {$summary['feeds_dropdown']} feeds (dropdown)");
        $this->info("  ✓ {$summary['home']['items_count']} home items (total: {$summary['home']['total_count']})");
        $this->info("  ✓ {$summary['feeds_list']} feeds (list page 1)");
        $this->info("  ✓ {$summary['top_feeds']} top feeds");
        $this->info("  ✓ {$summary['thumbnails']} thumbnails generated");

        $this->info('✓ Cache warming completed');

        return self::SUCCESS;
    }
}
