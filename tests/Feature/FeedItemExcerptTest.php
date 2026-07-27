<?php

namespace Tests\Feature;

use App\Models\FeedItem;
use App\Support\Excerpt;
use Tests\TestCase;

class FeedItemExcerptTest extends TestCase
{
    public function test_excerpt_is_generated_on_create_and_updated_with_content(): void
    {
        [$feed] = $this->seedBasicData();

        $item = FeedItem::query()->where('feed_id', $feed->id)->first();

        $this->assertSame('Conteúdo do artigo de teste com mais de trinta caracteres.', $item->excerpt);

        $item->content = '<p>Outro texto <strong>com tags</strong> e entidades &amp; decodificadas.</p>';
        $item->save();

        $this->assertSame('Outro texto com tags e entidades & decodificadas.', $item->fresh()->excerpt);
    }

    public function test_excerpt_is_null_when_content_has_no_text(): void
    {
        [$feed] = $this->seedBasicData();

        $item = FeedItem::create([
            'feed_id' => $feed->id,
            'title' => 'Sem texto',
            'url' => 'https://example.com/vazio',
            'guid' => 'guid-vazio',
            'content' => '<div><img src="x.jpg"></div>',
            'is_visible' => true,
        ]);

        $this->assertNull($item->fresh()->excerpt);
    }

    public function test_excerpt_is_capped_at_storage_length(): void
    {
        $long = '<p>'.str_repeat('a', 1000).'</p>';

        $excerpt = Excerpt::forStorage($long);

        $this->assertSame(Excerpt::STORAGE_LENGTH, mb_strlen($excerpt));
        $this->assertGreaterThanOrEqual(300, Excerpt::STORAGE_LENGTH);
    }

    public function test_home_listing_shows_the_stored_excerpt(): void
    {
        $this->seedBasicData();

        $this->get('/')
            ->assertOk()
            ->assertSee('Conteúdo do artigo de teste', false);
    }
}
