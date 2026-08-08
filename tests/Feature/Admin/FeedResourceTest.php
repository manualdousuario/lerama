<?php

namespace Tests\Feature\Admin;

use App\Enums\FeedStatus;
use App\Filament\Resources\Feeds\Pages\CreateFeed;
use App\Filament\Resources\Feeds\Pages\EditFeed;
use App\Filament\Resources\Feeds\Pages\ListFeeds;
use App\Models\Category;
use App\Models\Feed;
use App\Models\Tag;
use App\Services\ItemCountService;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;
use Tests\Feature\Admin\Concerns\AdminUsers;

beforeEach(function () {
    $this->actingAs(AdminUsers::admin());
});

it('generates a slug and forces online when creating a feed', function () {
    $category = Category::create(['name' => 'Blogs', 'slug' => 'blogs']);

    Livewire::test(CreateFeed::class)
        ->fillForm([
            'title' => 'Blog Novo',
            'site_url' => 'https://novo.example.com/secao',
            'feed_url' => 'https://novo.example.com/feed.xml',
            'language' => 'pt_BR',
            'feed_type' => 'rss2',
            'shuffle' => true,
            'categories' => [$category->id],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $feed = Feed::where('feed_url', 'https://novo.example.com/feed.xml')->firstOrFail();

    expect($feed->slug)->toBe('novo-example-com-secao')
        ->and($feed->status)->toBe(FeedStatus::Online);

    $this->assertEqualsCanonicalizing([$category->id], $feed->categories()->pluck('categories.id')->all());
});

it('rejects a private url when creating a feed', function () {
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

    expect(Feed::where('title', 'Interno')->count())->toBe(0);
});

it('only regenerates the slug on edit when the site url changes', function () {
    [$feed] = $this->seedBasicData();
    $originalSlug = $feed->slug;

    Livewire::test(EditFeed::class, ['record' => $feed->getKey()])
        ->fillForm(['title' => 'Título Novo'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($feed->fresh()->slug)->toBe($originalSlug);

    Livewire::test(EditFeed::class, ['record' => $feed->getKey()])
        ->fillForm(['site_url' => 'https://outro.example.org'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($feed->fresh()->slug)->toBe('outro-example-org');
});

it('keeps the feed type on edit when the select is cleared', function () {
    [$feed] = $this->seedBasicData();

    Livewire::test(EditFeed::class, ['record' => $feed->getKey()])
        ->fillForm(['feed_type' => null])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($feed->fresh()->feed_type->value)->toBe('rss2');
});

it('searches across title and urls in the table', function () {
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
});

it('updates every selected feed on bulk status', function () {
    [$feed] = $this->seedBasicData();

    Livewire::test(ListFeeds::class)
        ->selectTableRecords([$feed])
        ->callAction(TestAction::make('bulkStatus')->table()->bulk(), ['status' => FeedStatus::Paused->value]);

    expect($feed->fresh()->status)->toBe(FeedStatus::Paused);
});

it('replaces the existing assignment and recounts on bulk categories', function () {
    [$feed, $category] = $this->seedBasicData();
    $replacement = Category::create(['name' => 'Notícias', 'slug' => 'noticias']);

    Livewire::test(ListFeeds::class)
        ->selectTableRecords([$feed])
        ->callAction(TestAction::make('bulkCategories')->table()->bulk(), ['categories' => [$replacement->id]]);

    $this->assertEqualsCanonicalizing(
        [$replacement->id],
        $feed->fresh()->categories()->pluck('categories.id')->all()
    );

    expect($replacement->fresh()->item_count)->toBe(1)
        ->and($category->fresh()->item_count)->toBe(0);
});

it('replaces the existing assignment and recounts on bulk tags', function () {
    [$feed, , $tag] = $this->seedBasicData();
    $replacement = Tag::create(['name' => 'Cultura', 'slug' => 'cultura']);

    Livewire::test(ListFeeds::class)
        ->selectTableRecords([$feed])
        ->callAction(TestAction::make('bulkTags')->table()->bulk(), ['tags' => [$replacement->id]]);

    $this->assertEqualsCanonicalizing(
        [$replacement->id],
        $feed->fresh()->tags()->pluck('tags.id')->all()
    );

    expect($replacement->fresh()->item_count)->toBe(1)
        ->and($tag->fresh()->item_count)->toBe(0);
});

it('recounts the taxonomy left behind when deleting a feed', function () {
    [$feed, $category, $tag] = $this->seedBasicData();

    app(ItemCountService::class)->recountTaxonomy([$category->id], [$tag->id]);

    expect($category->fresh()->item_count)->toBe(1);

    $feed->delete();

    expect($category->fresh()->item_count)->toBe(0)
        ->and($tag->fresh()->item_count)->toBe(0);
});
