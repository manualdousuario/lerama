<?php

namespace App\Console\Commands;

use App\Support\Text;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DecodeItemEntities extends Command
{
    protected $signature = 'lerama:decode-entities {--dry-run : Report what would change without writing}';

    protected $description = 'Decode HTML entities left in feed item titles and authors';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $batchSize = 500;
        $lastId = 0;
        $updated = 0;
        $scanned = 0;

        do {
            $items = DB::table('feed_items')
                ->select('id', 'title', 'author')
                ->where('id', '>', $lastId)
                ->where(function ($query): void {
                    $query->where('title', 'like', '%&%')->orWhere('author', 'like', '%&%');
                })
                ->orderBy('id')
                ->limit($batchSize)
                ->get();

            $batchCount = $items->count();
            if ($batchCount === 0) {
                break;
            }

            foreach ($items as $item) {
                $scanned++;
                $lastId = (int) $item->id;

                $changes = [];

                foreach (['title', 'author'] as $field) {
                    $normalized = Text::plain($item->{$field});

                    if ($normalized !== null && $normalized !== $item->{$field}) {
                        $changes[$field] = $normalized;
                    }
                }

                if ($changes === []) {
                    continue;
                }

                $updated++;

                if ($dryRun) {
                    $this->line("#{$item->id} ".implode(' | ', array_map(
                        fn (string $field, string $value) => "{$field}: {$value}",
                        array_keys($changes),
                        $changes
                    )));

                    continue;
                }

                DB::table('feed_items')->where('id', $item->id)->update($changes);
            }
        } while ($batchCount === $batchSize);

        if ($updated > 0 && ! $dryRun) {
            Cache::flush();
        }

        $verb = $dryRun ? 'would be updated' : 'updated';
        $this->info("✓ Scanned {$scanned} item(s), {$updated} {$verb}.");

        return self::SUCCESS;
    }
}
