<?php

namespace Tests\Feature;

use App\Models\Feed;
use App\Services\FeedTypeDetector;
use Livewire\Livewire;
use Tests\TestCase;

class SuggestFeedTest extends TestCase
{
    private function fakeDetector(string|false|null $type = 'rss2'): void
    {
        $fake = new class($type) extends FeedTypeDetector
        {
            public function __construct(private $type) {}

            public function detectType(string $url, ?int $feedId = null): ?string
            {
                return $this->type === false ? null : $this->type;
            }
        };

        $this->app->instance(FeedTypeDetector::class, $fake);
    }

    public function test_page_renders_form(): void
    {
        $this->seedBasicData();
        $this->fakeDetector();

        $this->get('/suggest-feed')->assertOk()->assertSeeLivewire('suggest-feed-form');
    }

    public function test_required_field_validation(): void
    {
        $this->seedBasicData();
        $this->fakeDetector();

        Livewire::test('suggest-feed-form')
            ->call('submit')
            ->assertHasErrors(['title', 'feed_url', 'site_url']);
    }

    public function test_suggestion_creates_pending_feed(): void
    {
        [$feed, $category, $tag] = $this->seedBasicData();
        $this->fakeDetector('rss2');

        Livewire::test('suggest-feed-form')
            ->set('title', 'Blog Novo')
            ->set('feed_url', 'https://novo.example.com/feed')
            ->set('site_url', 'https://novo.example.com')
            ->set('language', 'pt-BR')
            ->set('category', $category->id)
            ->set('selectedTags', (string) $tag->id)
            ->call('submit')
            ->assertHasNoErrors();

        $created = Feed::where('feed_url', 'https://novo.example.com/feed')->first();

        $this->assertNotNull($created);
        $this->assertSame('pending', $created->status->value);
        $this->assertSame('rss2', $created->feed_type->value);
        $this->assertSame('novo-example-com', $created->slug);
        $this->assertTrue($created->categories->contains($category->id));
        $this->assertTrue($created->tags->contains($tag->id));
    }

    public function test_rejects_duplicate_feed(): void
    {
        [$feed, $category, $tag] = $this->seedBasicData();
        $this->fakeDetector();

        Livewire::test('suggest-feed-form')
            ->set('title', 'Duplicado')
            ->set('feed_url', $feed->feed_url)
            ->set('site_url', 'https://outro.example.com')
            ->set('category', $category->id)
            ->set('selectedTags', (string) $tag->id)
            ->call('submit')
            ->assertHasErrors(['feed_url']);
    }

    public function test_rejects_invalid_feed(): void
    {
        [$feed, $category, $tag] = $this->seedBasicData();
        $this->fakeDetector(false); // detecção falha

        Livewire::test('suggest-feed-form')
            ->set('title', 'Inválido')
            ->set('feed_url', 'https://invalido.example.com/feed')
            ->set('site_url', 'https://invalido.example.com')
            ->set('category', $category->id)
            ->set('selectedTags', (string) $tag->id)
            ->call('submit')
            ->assertHasErrors(['feed_url']);

        $this->assertSame(1, Feed::count());
    }
}
