<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class FeedTaxonomyService
{
    public function __construct(private ItemCountService $counts) {}

    /**
     * @param  array<int>  $feedIds
     * @param  array<int>  $categoryIds
     */
    public function replaceCategories(array $feedIds, array $categoryIds): void
    {
        $affected = $this->replace('feed_categories', 'category_id', $feedIds, $categoryIds);

        $this->counts->recountTaxonomy($affected, []);

        Cache::flush();
    }

    /**
     * @param  array<int>  $feedIds
     * @param  array<int>  $tagIds
     */
    public function replaceTags(array $feedIds, array $tagIds): void
    {
        $affected = $this->replace('feed_tags', 'tag_id', $feedIds, $tagIds);

        $this->counts->recountTaxonomy([], $affected);

        Cache::flush();
    }

    private function replace(string $table, string $taxonomyColumn, array $feedIds, array $taxonomyIds): array
    {
        $detached = DB::table($table)->whereIn('feed_id', $feedIds)->pluck($taxonomyColumn)->all();

        DB::table($table)->whereIn('feed_id', $feedIds)->delete();

        $rows = [];
        foreach ($feedIds as $feedId) {
            foreach ($taxonomyIds as $taxonomyId) {
                $rows[] = ['feed_id' => $feedId, $taxonomyColumn => $taxonomyId];
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table($table)->insertOrIgnore($chunk);
        }

        return array_values(array_unique(array_map(
            'intval',
            array_merge($detached, $taxonomyIds)
        )));
    }
}
