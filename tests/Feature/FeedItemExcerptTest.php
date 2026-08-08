<?php

namespace Tests\Feature;

use App\Models\FeedItem;
use App\Support\Excerpt;

it('generates the excerpt on create and updates it with the content', function () {
    [$feed] = $this->seedBasicData();

    $item = FeedItem::query()->where('feed_id', $feed->id)->first();

    expect($item->excerpt)->toBe('Conteúdo do artigo de teste com mais de trinta caracteres.');

    $item->content = '<p>Outro texto <strong>com tags</strong> e entidades &amp; decodificadas.</p>';
    $item->save();

    expect($item->fresh()->excerpt)->toBe('Outro texto com tags e entidades & decodificadas.');
});

it('leaves the excerpt null when the content has no text', function () {
    [$feed] = $this->seedBasicData();

    $item = FeedItem::create([
        'feed_id' => $feed->id,
        'title' => 'Sem texto',
        'url' => 'https://example.com/vazio',
        'guid' => 'guid-vazio',
        'content' => '<div><img src="x.jpg"></div>',
        'is_visible' => true,
    ]);

    expect($item->fresh()->excerpt)->toBeNull();
});

it('caps the excerpt at the storage length', function () {
    $long = '<p>'.str_repeat('a', 1000).'</p>';

    $excerpt = Excerpt::forStorage($long);

    expect(mb_strlen($excerpt))->toBe(Excerpt::STORAGE_LENGTH)
        ->and(Excerpt::STORAGE_LENGTH)->toBeGreaterThanOrEqual(300);
});

it('shows the stored excerpt on the home listing', function () {
    $this->seedBasicData();

    $this->get('/')
        ->assertOk()
        ->assertSee('Conteúdo do artigo de teste', false);
});
