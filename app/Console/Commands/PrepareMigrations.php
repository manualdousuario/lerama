<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Throwable;

class PrepareMigrations extends Command
{
    protected $signature = 'lerama:prepare-migrations';

    protected $description = 'Retire the legacy `migrations` table so Laravel can own the repository';

    private const ARCHIVE = 'migrations_legacy';

    public function handle(): int
    {
        try {
            if (! Schema::hasTable('migrations') || Schema::hasColumn('migrations', 'batch')) {
                return self::SUCCESS;
            }

            if (Schema::hasTable(self::ARCHIVE)) {
                Schema::drop('migrations');
                $this->info('Dropped the legacy `migrations` table ('.self::ARCHIVE.' already exists).');

                return self::SUCCESS;
            }

            Schema::rename('migrations', self::ARCHIVE);
            $this->info('Legacy `migrations` table renamed to `'.self::ARCHIVE.'`.');

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Could not inspect the migrations table: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
