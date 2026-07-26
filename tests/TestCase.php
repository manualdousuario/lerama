<?php

namespace Tests;

use App\Models\Category;
use App\Models\Feed;
use App\Models\FeedItem;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function seedBasicData(): array
    {
        $category = Category::create(['name' => 'Blogs', 'slug' => 'blogs']);
        $tag = Tag::create(['name' => 'Tecnologia', 'slug' => 'tecnologia']);

        $feed = Feed::create([
            'title' => 'Blog de Teste',
            'feed_url' => 'https://example.com/feed',
            'site_url' => 'https://example.com',
            'slug' => 'example-com',
            'feed_type' => 'rss2',
            'language' => 'pt_BR',
            'status' => 'online',
        ]);

        $feed->categories()->attach($category->id);
        $feed->tags()->attach($tag->id);

        FeedItem::create([
            'feed_id' => $feed->id,
            'title' => 'Artigo de Teste',
            'author' => 'Autor',
            'content' => '<p>Conteúdo do artigo de teste com mais de trinta caracteres.</p>',
            'url' => 'https://example.com/artigo-1',
            'guid' => 'guid-1',
            'published_at' => now(),
            'is_visible' => true,
        ]);

        return [$feed, $category, $tag];
    }
}
