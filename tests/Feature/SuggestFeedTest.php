<?php

namespace Tests\Feature;

use App\Models\Feed;
use App\Services\FeedTypeDetector;
use Livewire\Livewire;

it('renders the form on the page', function () {
    $this->seedBasicData();
    suggestFakeDetector();

    $this->get('/suggest-feed')->assertOk()->assertSeeLivewire('suggest-feed-form');
});

it('validates the required fields', function () {
    $this->seedBasicData();
    suggestFakeDetector();

    Livewire::test('suggest-feed-form')
        ->call('submit')
        ->assertHasErrors(['title', 'feed_url', 'site_url']);
});

it('creates a pending feed from the suggestion', function () {
    [$feed, $category, $tag] = $this->seedBasicData();
    suggestFakeDetector('rss2');

    Livewire::test('suggest-feed-form')
        ->set('title', 'Blog Novo')
        ->set('feed_url', 'https://novo.example.com/feed')
        ->set('site_url', 'https://novo.example.com')
        ->set('language', 'pt_BR')
        ->set('category', $category->id)
        ->set('selectedTags', (string) $tag->id)
        ->call('submit')
        ->assertHasNoErrors();

    $created = Feed::where('feed_url', 'https://novo.example.com/feed')->first();

    expect($created)->not->toBeNull()
        ->and($created->status->value)->toBe('pending')
        ->and($created->feed_type->value)->toBe('rss2')
        ->and($created->slug)->toBe('novo-example-com')
        ->and($created->categories->contains($category->id))->toBeTrue()
        ->and($created->tags->contains($tag->id))->toBeTrue();
});

it('rejects duplicate feed', function () {
    [$feed, $category, $tag] = $this->seedBasicData();
    suggestFakeDetector();

    Livewire::test('suggest-feed-form')
        ->set('title', 'Duplicado')
        ->set('feed_url', $feed->feed_url)
        ->set('site_url', 'https://outro.example.com')
        ->set('category', $category->id)
        ->set('selectedTags', (string) $tag->id)
        ->call('submit')
        ->assertHasErrors(['feed_url']);
});

it('rejects invalid feed', function () {
    [$feed, $category, $tag] = $this->seedBasicData();
    suggestFakeDetector(false);

    Livewire::test('suggest-feed-form')
        ->set('title', 'Inválido')
        ->set('feed_url', 'https://invalido.example.com/feed')
        ->set('site_url', 'https://invalido.example.com')
        ->set('category', $category->id)
        ->set('selectedTags', (string) $tag->id)
        ->call('submit')
        ->assertHasErrors(['feed_url']);

    expect(Feed::count())->toBe(1);
});

function suggestFakeDetector(string|false|null $type = 'rss2'): void
{
    $fake = new class($type) extends FeedTypeDetector
    {
        public function __construct(private $type) {}

        public function detectType(string $url, ?int $feedId = null): ?string
        {
            return $this->type === false ? null : $this->type;
        }
    };

    app()->instance(FeedTypeDetector::class, $fake);
}
