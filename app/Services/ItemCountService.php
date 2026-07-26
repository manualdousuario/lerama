<?php

namespace App\Services;

use App\Models\FeedItem;
use Illuminate\Support\Facades\DB;

/**
 * Maintains the item_count columns, replacing the 12 legacy MySQL triggers.
 *
 * - feeds.item_count: every item of the feed, visible or not
 * - categories/tags.item_count: DISTINCT visible items across associated feeds
 */
class ItemCountService
{
    // AFTER INSERT ON feed_items.
    public function itemCreated(FeedItem $item): void
    {
        DB::statement('UPDATE feeds SET item_count = item_count + 1 WHERE id = ?', [$item->feed_id]);

        if ($item->is_visible) {
            $this->adjustTaxonomy($item->feed_id, +1);
        }
    }

    // AFTER DELETE ON feed_items.
    public function itemDeleted(FeedItem $item): void
    {
        DB::statement('UPDATE feeds SET item_count = GREATEST(0, item_count - 1) WHERE id = ?', [$item->feed_id]);

        if ($item->is_visible) {
            $this->adjustTaxonomy($item->feed_id, -1);
        }
    }

    // AFTER UPDATE ON feed_items, only when is_visible changed.
    public function itemVisibilityChanged(FeedItem $item): void
    {
        $this->adjustTaxonomy($item->feed_id, $item->is_visible ? +1 : -1);
    }

    /**
     * Aggregate adjustment after a feed processor bulk insert, which bypasses
     * Eloquent events.
     *
     * @param  int  $inserted  rows actually inserted, after dedupe
     * @param  int  $visible  how many of those are visible
     */
    public function bulkItemsInserted(int $feedId, int $inserted, int $visible): void
    {
        if ($inserted <= 0) {
            return;
        }

        DB::statement('UPDATE feeds SET item_count = item_count + ? WHERE id = ?', [$inserted, $feedId]);

        if ($visible > 0) {
            $this->adjustTaxonomy($feedId, $visible);
        }
    }

    /**
     * Recount taxonomy counters affected by feed<->category/tag association
     * changes, mirroring the legacy pivot triggers.
     *
     * @param  array<int>  $categoryIds
     * @param  array<int>  $tagIds
     */
    public function recountTaxonomy(array $categoryIds = [], array $tagIds = []): void
    {
        foreach (array_unique($categoryIds) as $id) {
            DB::statement(
                'UPDATE categories c SET c.item_count = (
                    SELECT COUNT(DISTINCT fi.id) FROM feed_items fi
                    JOIN feeds f ON fi.feed_id = f.id
                    JOIN feed_categories fc ON f.id = fc.feed_id
                    WHERE fc.category_id = ? AND fi.is_visible = 1
                ) WHERE c.id = ?',
                [$id, $id]
            );
        }

        foreach (array_unique($tagIds) as $id) {
            DB::statement(
                'UPDATE tags t SET t.item_count = (
                    SELECT COUNT(DISTINCT fi.id) FROM feed_items fi
                    JOIN feeds f ON fi.feed_id = f.id
                    JOIN feed_tags ft ON f.id = ft.feed_id
                    WHERE ft.tag_id = ? AND fi.is_visible = 1
                ) WHERE t.id = ?',
                [$id, $id]
            );
        }
    }

    // Recount a single feed and its taxonomy, after a processor bulk insert.
    public function recountFeedAndTaxonomy(int $feedId): void
    {
        DB::statement(
            'UPDATE feeds f SET f.item_count = (SELECT COUNT(*) FROM feed_items WHERE feed_id = ?) WHERE f.id = ?',
            [$feedId, $feedId]
        );

        $categoryIds = DB::table('feed_categories')->where('feed_id', $feedId)->pluck('category_id')->all();
        $tagIds = DB::table('feed_tags')->where('feed_id', $feedId)->pluck('tag_id')->all();

        $this->recountTaxonomy($categoryIds, $tagIds);
    }

    // Apply ±delta to every category/tag linked to the feed, mirroring the
    // update_{category,tag}_count_on_* triggers.
    private function adjustTaxonomy(int $feedId, int $delta): void
    {
        if ($delta > 0) {
            DB::statement(
                'UPDATE categories c INNER JOIN feed_categories fc ON c.id = fc.category_id
                 SET c.item_count = c.item_count + ? WHERE fc.feed_id = ?',
                [$delta, $feedId]
            );
            DB::statement(
                'UPDATE tags t INNER JOIN feed_tags ft ON t.id = ft.tag_id
                 SET t.item_count = t.item_count + ? WHERE ft.feed_id = ?',
                [$delta, $feedId]
            );
        } else {
            $abs = abs($delta);
            DB::statement(
                'UPDATE categories c INNER JOIN feed_categories fc ON c.id = fc.category_id
                 SET c.item_count = GREATEST(0, c.item_count - ?) WHERE fc.feed_id = ?',
                [$abs, $feedId]
            );
            DB::statement(
                'UPDATE tags t INNER JOIN feed_tags ft ON t.id = ft.tag_id
                 SET t.item_count = GREATEST(0, t.item_count - ?) WHERE ft.feed_id = ?',
                [$abs, $feedId]
            );
        }
    }
}
