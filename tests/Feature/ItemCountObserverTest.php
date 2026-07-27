<?php

namespace Tests\Feature;

use App\Models\FeedItem;
use App\Services\ItemCountService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ItemCountObserverTest extends TestCase
{
    public function test_counters_follow_item_lifecycle(): void
    {
        [$feed, $category, $tag] = $this->seedBasicData();

        // seedBasicData created 1 visible item
        $this->assertSame(1, $feed->fresh()->item_count);
        $this->assertSame(1, $feed->fresh()->visible_item_count);
        $this->assertSame(1, $category->fresh()->item_count);
        $this->assertSame(1, $tag->fresh()->item_count);

        $item = FeedItem::create([
            'feed_id' => $feed->id,
            'title' => 'Hidden',
            'url' => 'https://example.com/2',
            'guid' => 'guid-2',
            'is_visible' => false,
        ]);

        // Invisible: counted on the feed totals, not on the visible counter
        // nor on the taxonomy
        $this->assertSame(2, $feed->fresh()->item_count);
        $this->assertSame(1, $feed->fresh()->visible_item_count);
        $this->assertSame(1, $category->fresh()->item_count);

        // Toggle to visible
        $item->is_visible = true;
        $item->save();
        $this->assertSame(2, $feed->fresh()->visible_item_count);
        $this->assertSame(2, $category->fresh()->item_count);
        $this->assertSame(2, $tag->fresh()->item_count);

        // Toggle back to invisible
        $item->is_visible = false;
        $item->save();
        $this->assertSame(1, $feed->fresh()->visible_item_count);
        $this->assertSame(1, $category->fresh()->item_count);

        // Toggle visible again and delete the item
        $item->is_visible = true;
        $item->save();
        $item->delete();
        $this->assertSame(1, $feed->fresh()->item_count);
        $this->assertSame(1, $feed->fresh()->visible_item_count);
        $this->assertSame(1, $category->fresh()->item_count);
    }

    public function test_recount_feed_and_taxonomy_rebuilds_visible_counter(): void
    {
        [$feed] = $this->seedBasicData();

        // Simulate a bulk insert bypassing events, like the feed processor.
        DB::table('feed_items')->insert([
            'feed_id' => $feed->id,
            'title' => 'Bulk visible',
            'url' => 'https://example.com/3',
            'guid' => 'guid-3',
            'is_visible' => 1,
        ]);
        DB::table('feed_items')->insert([
            'feed_id' => $feed->id,
            'title' => 'Bulk hidden',
            'url' => 'https://example.com/4',
            'guid' => 'guid-4',
            'is_visible' => 0,
        ]);

        app(ItemCountService::class)->recountFeedAndTaxonomy($feed->id);

        $this->assertSame(3, $feed->fresh()->item_count);
        $this->assertSame(2, $feed->fresh()->visible_item_count);
    }

    public function test_feed_delete_cascade_with_manual_recount(): void
    {
        [$feed, $category, $tag] = $this->seedBasicData();

        $categoryIds = [$category->id];
        $feed->delete();

        // Cascade removes items/pivots without firing events
        app(ItemCountService::class)->recountTaxonomy($categoryIds, [$tag->id]);

        $this->assertSame(0, $category->fresh()->item_count);
        $this->assertSame(0, $tag->fresh()->item_count);
        $this->assertSame(0, FeedItem::count());
    }
}
