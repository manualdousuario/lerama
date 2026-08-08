<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\FeedItems\FeedItemResource;
use App\Filament\Resources\FeedItems\Pages\ListFeedItems;
use App\Models\Feed;
use App\Models\FeedItem;
use App\Services\ItemCountService;
use Livewire\Livewire;
use Tests\Feature\Admin\Concerns\AdminUsers;

beforeEach(function () {
    $this->actingAs(AdminUsers::admin());
});

it('does not let items be created from the panel', function () {
    expect(FeedItemResource::canCreate())->toBeFalse();
});

it('uses the fulltext index on search', function () {
    $this->seedBasicData();

    $sql = Livewire::test(ListFeedItems::class)
        ->searchTable('confeitaria')
        ->instance()
        ->getFilteredTableQuery()
        ->toSql();

    expect($sql)->toContain('MATCH(feed_items.title, feed_items.content)')
        ->and($sql)->toContain('IN BOOLEAN MODE')
        ->and(strtolower($sql))->not->toContain('like');
});

it('narrows the listing when filtering by feed', function () {
    [$feed] = $this->seedBasicData();

    $otherFeed = Feed::create([
        'title' => 'Outro',
        'feed_url' => 'https://outro.test/feed',
        'site_url' => 'https://outro.test',
        'slug' => 'outro-test',
        'feed_type' => 'rss2',
        'status' => 'online',
    ]);

    $otherItem = FeedItem::create([
        'feed_id' => $otherFeed->id,
        'title' => 'Item do outro feed',
        'content' => 'Conteúdo.',
        'url' => 'https://outro.test/item',
        'guid' => 'guid-outro',
        'published_at' => now(),
        'is_visible' => true,
    ]);

    $seeded = FeedItem::where('guid', 'guid-1')->firstOrFail();

    Livewire::test(ListFeedItems::class)
        ->filterTable('feed_id', $otherFeed->id)
        ->assertCanSeeTableRecords([$otherItem])
        ->assertCanNotSeeTableRecords([$seeded]);
});

it('updates the taxonomy counters when toggling visibility', function () {
    [$feed, $category, $tag] = $this->seedBasicData();

    app(ItemCountService::class)->recountTaxonomy([$category->id], [$tag->id]);

    expect($category->fresh()->item_count)->toBe(1);

    $item = FeedItem::where('guid', 'guid-1')->firstOrFail();

    $item->update(['is_visible' => false]);

    expect($category->fresh()->item_count)->toBe(0)
        ->and($tag->fresh()->item_count)->toBe(0);
});
