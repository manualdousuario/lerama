<?php

namespace Tests\Unit;

use App\Services\FeedSlugService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\UnitTestCase as TestCase;

class FeedSlugServiceTest extends TestCase
{
    #[DataProvider('urlProvider')]
    public function test_from_url(string $url, string $expected): void
    {
        $this->assertSame($expected, FeedSlugService::fromUrl($url));
    }

    public static function urlProvider(): array
    {
        return [
            ['https://example.com', 'example-com'],
            ['https://example.com/', 'example-com'],
            ['https://blog.example.com/posts', 'blog-example-com-posts'],
            ['https://example.com/feed.xml', 'example-com-feed-xml'],
            ['https://example.com/path?q=1', 'example-com-path-q-1'],
            ['https://Exemplo-Café.com.br/Ação', 'exemplo-cafe-com-br-acao'],
            ['', ''],
            ['notaurl', ''],
        ];
    }
}
