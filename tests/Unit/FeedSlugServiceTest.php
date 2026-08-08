<?php

namespace Tests\Unit;

use App\Services\FeedSlugService;

it('builds the slug from the url', function (string $url, string $expected) {
    expect(FeedSlugService::fromUrl($url))->toBe($expected);
})->with([
    ['https://example.com', 'example-com'],
    ['https://example.com/', 'example-com'],
    ['https://blog.example.com/posts', 'blog-example-com-posts'],
    ['https://example.com/feed.xml', 'example-com-feed-xml'],
    ['https://example.com/path?q=1', 'example-com-path-q-1'],
    ['https://Exemplo-Café.com.br/Ação', 'exemplo-cafe-com-br-acao'],
    ['', ''],
    ['notaurl', ''],
]);
