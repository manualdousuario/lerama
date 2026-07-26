<?php

namespace Tests\Feature;

use App\Models\FeedItem;
use App\Services\ItemCountService;
use Tests\TestCase;

class ItemCountObserverTest extends TestCase
{
    public function test_counters_follow_item_lifecycle(): void
    {
        [$feed, $category, $tag] = $this->seedBasicData();

        // seedBasicData created 1 visible item
        $this->assertSame(1, $feed->fresh()->item_count);
        $this->assertSame(1, $category->fresh()->item_count);
        $this->assertSame(1, $tag->fresh()->item_count);

        $item = FeedItem::create([
            'feed_id' => $feed->id,
            'title' => 'Hidden',
            'url' => 'https://example.com/2',
            'guid' => 'guid-2',
            'is_visible' => false,
        ]);

        // Invisible: counted on the feed, not on the taxonomy
        $this->assertSame(2, $feed->fresh()->item_count);
        $this->assertSame(1, $category->fresh()->item_count);

        // Toggle to visible
        $item->is_visible = true;
        $item->save();
        $this->assertSame(2, $category->fresh()->item_count);
        $this->assertSame(2, $tag->fresh()->item_count);

        // Delete the visible item
        $item->delete();
        $this->assertSame(1, $feed->fresh()->item_count);
        $this->assertSame(1, $category->fresh()->item_count);
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
