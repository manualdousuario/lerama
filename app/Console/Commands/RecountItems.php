<?php

namespace App\Console\Commands;

use App\Services\ItemCountService;
use Illuminate\Console\Command;

class RecountItems extends Command
{
    protected $signature = 'lerama:recount';

    protected $description = 'Recount the item_count columns of feeds, categories and tags';

    public function handle(ItemCountService $counts): int
    {
        $this->info('Recounting...');

        $counts->recountAll();

        $this->info('Recounted: feeds, categories and tags.');

        return self::SUCCESS;
    }
}
