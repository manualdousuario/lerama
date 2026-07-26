<?php

namespace Tests\Feature\Admin;

use App\Enums\FeedStatus;
use App\Filament\Resources\Feeds\Pages\CreateFeed;
use App\Filament\Resources\Feeds\Pages\EditFeed;
use App\Filament\Resources\Feeds\Pages\ListFeeds;
use App\Models\Category;
use App\Models\Feed;
use App\Models\Tag;
use App\Models\User;
use App\Services\ItemCountService;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class FeedResourceTest extends TestCase
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

    public function test_creating_a_feed_generates_a_slug_and_forces_online(): void
    {
        $category = Category::create(['name' => 'Blogs', 'slug' => 'blogs']);

        Livewire::test(CreateFeed::class)
            ->fillForm([
                'title' => 'Blog Novo',
                'site_url' => 'https://novo.example.com/secao',
                'feed_url' => 'https://novo.example.com/feed.xml',
                'language' => 'pt_BR',
                // Set explicitly so FeedTypeDetector does not hit the network.
                'feed_type' => 'rss2',
                'shuffle' => true,
                'categories' => [$category->id],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $feed = Feed::where('feed_url', 'https://novo.example.com/feed.xml')->firstOrFail();

        $this->assertSame('novo-example-com-secao', $feed->slug);
        $this->assertSame(FeedStatus::Online, $feed->status);
        $this->assertEqualsCanonicalizing([$category->id], $feed->categories()->pluck('categories.id')->all());
    }

    public function test_creating_a_feed_rejects_a_private_url(): void
    {
        Livewire::test(CreateFeed::class)
            ->fillForm([
                'title' => 'Interno',
                'site_url' => 'http://127.0.0.1/painel',
                'feed_url' => 'http://127.0.0.1/feed.xml',
                'language' => 'pt_BR',
                'feed_type' => 'rss2',
            ])
            ->call('create')
            ->assertHasFormErrors(['site_url', 'feed_url']);

        $this->assertSame(0, Feed::where('title', 'Interno')->count());
    }

    public function test_editing_only_regenerates_the_slug_when_site_url_changes(): void
    {
        [$feed] = $this->seedBasicData();
        $originalSlug = $feed->slug;

        Livewire::test(EditFeed::class, ['record' => $feed->getKey()])
            ->fillForm(['title' => 'Título Novo'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($originalSlug, $feed->fresh()->slug);

        Livewire::test(EditFeed::class, ['record' => $feed->getKey()])
            ->fillForm(['site_url' => 'https://outro.example.org'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('outro-example-org', $feed->fresh()->slug);
    }

    public function test_editing_keeps_the_feed_type_when_the_select_is_cleared(): void
    {
        [$feed] = $this->seedBasicData();

        Livewire::test(EditFeed::class, ['record' => $feed->getKey()])
            ->fillForm(['feed_type' => null])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('rss2', $feed->fresh()->feed_type->value);
    }

    public function test_table_searches_across_title_and_urls(): void
    {
        [$feed] = $this->seedBasicData();
        $other = Feed::create([
            'title' => 'Outro Blog',
            'feed_url' => 'https://outro.test/feed',
            'site_url' => 'https://outro.test',
            'slug' => 'outro-test',
            'feed_type' => 'rss2',
            'status' => 'online',
        ]);

        Livewire::test(ListFeeds::class)
            ->searchTable('outro.test')
            ->assertCanSeeTableRecords([$other])
            ->assertCanNotSeeTableRecords([$feed]);
    }

    public function test_bulk_status_updates_every_selected_feed(): void
    {
        [$feed] = $this->seedBasicData();

        Livewire::test(ListFeeds::class)
            ->callTableBulkAction('bulkStatus', [$feed], ['status' => FeedStatus::Paused->value]);

        $this->assertSame(FeedStatus::Paused, $feed->fresh()->status);
    }

    public function test_bulk_categories_replace_the_existing_assignment_and_recount(): void
    {
        [$feed, $category] = $this->seedBasicData();
        $replacement = Category::create(['name' => 'Notícias', 'slug' => 'noticias']);

        Livewire::test(ListFeeds::class)
            ->callTableBulkAction('bulkCategories', [$feed], ['categories' => [$replacement->id]]);

        $this->assertEqualsCanonicalizing(
            [$replacement->id],
            $feed->fresh()->categories()->pluck('categories.id')->all()
        );

        // The feed has one visible item, so the new category inherits it and
        // the detached one drops back to zero.
        $this->assertSame(1, $replacement->fresh()->item_count);
        $this->assertSame(0, $category->fresh()->item_count);
    }

    public function test_bulk_tags_replace_the_existing_assignment_and_recount(): void
    {
        [$feed, , $tag] = $this->seedBasicData();
        $replacement = Tag::create(['name' => 'Cultura', 'slug' => 'cultura']);

        Livewire::test(ListFeeds::class)
            ->callTableBulkAction('bulkTags', [$feed], ['tags' => [$replacement->id]]);

        $this->assertEqualsCanonicalizing(
            [$replacement->id],
            $feed->fresh()->tags()->pluck('tags.id')->all()
        );

        $this->assertSame(1, $replacement->fresh()->item_count);
        $this->assertSame(0, $tag->fresh()->item_count);
    }

    public function test_deleting_a_feed_recounts_the_taxonomy_it_left_behind(): void
    {
        [$feed, $category, $tag] = $this->seedBasicData();

        // Give the counters a non-zero starting point.
        app(ItemCountService::class)->recountTaxonomy([$category->id], [$tag->id]);
        $this->assertSame(1, $category->fresh()->item_count);

        $feed->delete();

        $this->assertSame(0, $category->fresh()->item_count);
        $this->assertSame(0, $tag->fresh()->item_count);
    }
}
