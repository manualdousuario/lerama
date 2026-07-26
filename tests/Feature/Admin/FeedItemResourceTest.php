<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\FeedItems\FeedItemResource;
use App\Filament\Resources\FeedItems\Pages\ListFeedItems;
use App\Models\Feed;
use App\Models\FeedItem;
use App\Models\User;
use App\Services\ItemCountService;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class FeedItemResourceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::create([
            'name' => 'admin',
            'email' => 'admin@lerama.local',
            'password' => Hash::make('strong-password-123'),
        ]));
    }

    public function test_items_cannot_be_created_from_the_panel(): void
    {
        $this->assertFalse(FeedItemResource::canCreate());
    }

    /**
     * InnoDB does not expose uncommitted rows to a FULLTEXT index, and
     * RefreshDatabase wraps each test in a transaction, so asserting on
     * returned records is impossible here. Assert the query instead: this is
     * what guards against the search silently degrading to a LIKE scan, which
     * would not use idx_title_content.
     */
    public function test_search_uses_the_fulltext_index(): void
    {
        $this->seedBasicData();

        $sql = Livewire::test(ListFeedItems::class)
            ->searchTable('confeitaria')
            ->instance()
            ->getFilteredTableQuery()
            ->toSql();

        $this->assertStringContainsString('MATCH(feed_items.title, feed_items.content)', $sql);
        $this->assertStringContainsString('IN BOOLEAN MODE', $sql);
        $this->assertStringNotContainsString('like', strtolower($sql));
    }

    public function test_filtering_by_feed_narrows_the_listing(): void
    {
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
    }

    public function test_toggling_visibility_updates_the_taxonomy_counters(): void
    {
        [$feed, $category, $tag] = $this->seedBasicData();

        app(ItemCountService::class)->recountTaxonomy([$category->id], [$tag->id]);
        $this->assertSame(1, $category->fresh()->item_count);

        $item = FeedItem::where('guid', 'guid-1')->firstOrFail();

        // The FeedItemObserver keeps the counters in step with is_visible.
        $item->update(['is_visible' => false]);

        $this->assertSame(0, $category->fresh()->item_count);
        $this->assertSame(0, $tag->fresh()->item_count);
    }
}
