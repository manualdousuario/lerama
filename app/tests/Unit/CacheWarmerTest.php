<?php

declare(strict_types=1);

namespace Tests\Unit;

use DB;
use Lerama\Services\CacheService;
use Lerama\Services\CacheWarmer;
use PHPUnit\Framework\TestCase;

class CacheWarmerTest extends TestCase
{
    private CacheService $cache;

    protected function setUp(): void
    {
        $this->cache = CacheService::getInstance();
        if (!$this->cache->isAvailable()) {
            $this->markTestSkipped('Redis is not available');
        }

        if (!$this->databaseIsAvailable()) {
            $this->markTestSkipped('Database is not available');
        }

        $this->cache->flush();
    }

    protected function tearDown(): void
    {
        if ($this->cache->isAvailable()) {
            $this->cache->flush();
        }
    }

    private function databaseIsAvailable(): bool
    {
        $required = ['LERAMA_DB_HOST', 'LERAMA_DB_NAME', 'LERAMA_DB_USER', 'LERAMA_DB_PASS'];
        foreach ($required as $key) {
            if (empty($_ENV[$key])) {
                return false;
            }
        }

        try {
            DB::$host = $_ENV['LERAMA_DB_HOST'];
            DB::$user = $_ENV['LERAMA_DB_USER'];
            DB::$password = $_ENV['LERAMA_DB_PASS'];
            DB::$dbName = $_ENV['LERAMA_DB_NAME'];
            DB::$port = (int)($_ENV['LERAMA_DB_PORT'] ?? 3306);
            DB::$encoding = 'utf8mb4';
            DB::query('SELECT 1');
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function testWarmCategoriesPopulatesCache(): void
    {
        CacheWarmer::warmCategories();

        $categories = $this->cache->get($this->cache->key('categories', 'all', $this->cache->hash([])));
        $this->assertIsArray($categories);
    }

    public function testWarmTagsPopulatesCache(): void
    {
        CacheWarmer::warmTags();

        $tags = $this->cache->get($this->cache->key('tags', 'all', $this->cache->hash([])));
        $this->assertIsArray($tags);
    }

    public function testWarmFeedsDropdownPopulatesCache(): void
    {
        CacheWarmer::warmFeedsDropdown();

        $feeds = $this->cache->get($this->cache->key('feeds', 'dropdown', $this->cache->hash([])));
        $this->assertIsArray($feeds);
    }

    public function testWarmHomePopulatesCache(): void
    {
        CacheWarmer::warmHome();

        $perPage = (int)($_ENV['ITEMS_PER_PAGE'] ?? 21);
        $filterHash = $this->cache->hash([
            'search' => '',
            'feed' => null,
            'category' => null,
            'tag' => null,
            'page' => 1,
            'perPage' => $perPage,
            'latest' => 0
        ]);

        $count = $this->cache->get($this->cache->key('items', 'count', $filterHash));
        $items = $this->cache->get($this->cache->key('items', 'home', $filterHash));

        $this->assertNotNull($count);
        $this->assertIsArray($items);
    }

    public function testWarmImportantReturnsSummary(): void
    {
        $_ENV['CACHE_WARM_FEEDS_LIMIT'] = '0';

        $summary = CacheWarmer::warmImportant();

        $this->assertArrayHasKey('categories', $summary);
        $this->assertArrayHasKey('tags', $summary);
        $this->assertArrayHasKey('feeds_dropdown', $summary);
        $this->assertArrayHasKey('home', $summary);
        $this->assertArrayHasKey('top_feeds', $summary);
        $this->assertSame(0, $summary['top_feeds']);
    }
}
