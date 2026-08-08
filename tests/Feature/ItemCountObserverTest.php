<?php

namespace Tests\Feature;

use App\Models\FeedItem;
use App\Services\ItemCountService;
use Illuminate\Support\Facades\DB;

it('follows the item lifecycle on the counters', function () {
    [$feed, $category, $tag] = $this->seedBasicData();

    // seedBasicData created 1 visible item
    expect($feed->fresh()->item_count)->toBe(1)
        ->and($feed->fresh()->visible_item_count)->toBe(1)
        ->and($category->fresh()->item_count)->toBe(1)
        ->and($tag->fresh()->item_count)->toBe(1);

    $item = FeedItem::create([
        'feed_id' => $feed->id,
        'title' => 'Hidden',
        'url' => 'https://example.com/2',
        'guid' => 'guid-2',
        'is_visible' => false,
    ]);

    expect($feed->fresh()->item_count)->toBe(2)
        ->and($feed->fresh()->visible_item_count)->toBe(1)
        ->and($category->fresh()->item_count)->toBe(1);

    $item->is_visible = true;
    $item->save();

    expect($feed->fresh()->visible_item_count)->toBe(2)
        ->and($category->fresh()->item_count)->toBe(2)
        ->and($tag->fresh()->item_count)->toBe(2);

    $item->is_visible = false;
    $item->save();

    expect($feed->fresh()->visible_item_count)->toBe(1)
        ->and($category->fresh()->item_count)->toBe(1);

    $item->is_visible = true;
    $item->save();
    $item->delete();

    expect($feed->fresh()->item_count)->toBe(1)
        ->and($feed->fresh()->visible_item_count)->toBe(1)
        ->and($category->fresh()->item_count)->toBe(1);
});

it('rebuilds the visible counter on recount feed and taxonomy', function () {
    [$feed] = $this->seedBasicData();

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

    expect($feed->fresh()->item_count)->toBe(3)
        ->and($feed->fresh()->visible_item_count)->toBe(2);
});

it('cascades the feed delete with a manual recount', function () {
    [$feed, $category, $tag] = $this->seedBasicData();

    $categoryIds = [$category->id];
    $feed->delete();

    app(ItemCountService::class)->recountTaxonomy($categoryIds, [$tag->id]);

    expect($category->fresh()->item_count)->toBe(0)
        ->and($tag->fresh()->item_count)->toBe(0)
        ->and(FeedItem::count())->toBe(0);
});
