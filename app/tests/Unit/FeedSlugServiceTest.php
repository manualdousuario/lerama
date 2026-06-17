<?php

declare(strict_types=1);

namespace Tests\Unit;

use Lerama\Services\FeedSlugService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class FeedSlugServiceTest extends TestCase
{
    #[DataProvider('urlProvider')]
    public function testGeneratesSlugFromUrl(string $url, string $expectedSlug): void
    {
        $this->assertSame($expectedSlug, FeedSlugService::fromUrl($url));
    }

    public static function urlProvider(): array
    {
        return [
            'simple https domain' => [
                'https://blog.wordpress.com',
                'blog-wordpress-com',
            ],
            'http scheme' => [
                'http://blog.wordpress.com',
                'blog-wordpress-com',
            ],
            'domain with path' => [
                'https://example.com/blog/posts',
                'example-com-blog-posts',
            ],
            'domain with query' => [
                'https://example.com/?q=test',
                'example-com-q-test',
            ],
            'trailing slash' => [
                'https://example.com/',
                'example-com',
            ],
            'subdomain' => [
                'https://www.example.co.uk',
                'www-example-co-uk',
            ],
            'uppercase letters' => [
                'https://BLOG.WordPress.COM',
                'blog-wordpress-com',
            ],
        ];
    }

    public function testFromUrlReturnsEmptyStringForInvalidUrl(): void
    {
        $this->assertSame('', FeedSlugService::fromUrl('not-a-url'));
    }
}
