<?php

namespace Tests\Feature;

it('keeps the legacy contract on the json feed', function () {
    $this->seedBasicData();

    $response = $this->get('/feed/json');
    $response->assertOk();
    $response->assertJsonStructure([
        'items' => [
            '*' => [
                'id', 'title', 'author', 'content', 'url', 'image_url', 'published_at',
                'feed' => ['title', 'site_url'],
            ],
        ],
        'pagination' => ['total_items', 'total_pages', 'current_page', 'per_page'],
    ]);

    $item = $response->json('items.0');

    expect($item['author'])->toBe('Autor em Blog de Teste')
        ->and($item['content'])->toContain('Leia no <a href=');
});

it('filters the json feed by category and tag', function () {
    $this->seedBasicData();

    $this->get('/feed/json?category=blogs')->assertJsonCount(1, 'items');
    $this->get('/feed/json?tag=tecnologia')->assertJsonCount(1, 'items');
    $this->get('/feed/json?category=nao-existe')->assertJsonCount(0, 'items');
});

it('serves the rss feed as valid xml with a channel', function () {
    $this->seedBasicData();

    $response = $this->get('/feed/rss');
    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/xml; charset=UTF-8');

    $xml = simplexml_load_string($response->getContent());

    expect($xml)->not->toBeFalse()
        ->and((string) $xml->channel->title)->toBe('Lerama')
        ->and($xml->channel->item)->toHaveCount(1)
        ->and((string) $xml->channel->item[0]->title)->toBe('Artigo de Teste');
});

it('serves the feed root alias', function () {
    $this->seedBasicData();

    $this->get('/feed')->assertOk();
});
