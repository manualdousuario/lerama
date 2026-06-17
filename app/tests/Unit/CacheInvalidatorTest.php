<?php

declare(strict_types=1);

namespace Tests\Unit;

use Lerama\Services\CacheInvalidator;
use Lerama\Services\CacheService;
use PHPUnit\Framework\TestCase;

class CacheInvalidatorTest extends TestCase
{
    private ?CacheService $cache = null;

    protected function setUp(): void
    {
        CacheInvalidator::reset();

        $cache = CacheService::getInstance();
        if ($cache->isAvailable()) {
            $this->cache = $cache;
            $this->cache->flush();
        }
    }

    protected function tearDown(): void
    {
        if ($this->cache !== null && $this->cache->isAvailable()) {
            $this->cache->flush();
        }
        CacheInvalidator::reset();
    }

    private function skipWithoutRedis(): void
    {
        if ($this->cache === null) {
            $this->markTestSkipped('Redis is not available');
        }
    }

    public function testExtractTableFromInsert(): void
    {
        $this->assertSame('feeds', CacheInvalidator::extractTableFromQuery("INSERT INTO `feeds` (`title`) VALUES (?)"));
        $this->assertSame('feed_items', CacheInvalidator::extractTableFromQuery("INSERT IGNORE INTO feed_items (title) VALUES (?)"));
        $this->assertSame('feeds', CacheInvalidator::extractTableFromQuery("REPLACE INTO feeds (title) VALUES (?)"));
    }

    public function testExtractTableFromUpdate(): void
    {
        $this->assertSame('feeds', CacheInvalidator::extractTableFromQuery("UPDATE `feeds` SET `status`=? WHERE id=?"));
        $this->assertSame('feed_items', CacheInvalidator::extractTableFromQuery("UPDATE feed_items SET image_url=? WHERE id=?"));
    }

    public function testExtractTableFromDelete(): void
    {
        $this->assertSame('feeds', CacheInvalidator::extractTableFromQuery("DELETE FROM `feeds` WHERE id=?"));
        $this->assertSame('feed_categories', CacheInvalidator::extractTableFromQuery("DELETE `feed_categories` FROM `feed_categories` WHERE feed_id=?"));
    }

    public function testExtractTableFromTruncate(): void
    {
        $this->assertSame('feed_items', CacheInvalidator::extractTableFromQuery("TRUNCATE TABLE `feed_items`"));
        $this->assertSame('feeds', CacheInvalidator::extractTableFromQuery("TRUNCATE feeds"));
    }

    public function testExtractTableReturnsNullForSelect(): void
    {
        $this->assertNull(CacheInvalidator::extractTableFromQuery("SELECT * FROM feeds"));
    }

    public function testInvalidateFromQueryDoesNothingWhenAffectedRowsIsZero(): void
    {
        $this->skipWithoutRedis();

        $key = $this->cache->key('test', 'feeds');
        $this->cache->set($key, 'value', 60, ['feeds']);

        $deleted = CacheInvalidator::invalidateFromQuery("UPDATE `feeds` SET `status`=? WHERE id=?", 0);

        $this->assertSame(0, $deleted);
        $this->assertSame('value', $this->cache->get($key));
    }

    public function testInvalidateFromQueryInvalidatesMappedTags(): void
    {
        $this->skipWithoutRedis();

        $feedsKey = $this->cache->key('test', 'feeds');
        $itemsKey = $this->cache->key('test', 'items');
        $this->cache->set($feedsKey, 'feeds-value', 60, ['feeds']);
        $this->cache->set($itemsKey, 'items-value', 60, ['items']);

        $deleted = CacheInvalidator::invalidateFromQuery("UPDATE `feeds` SET `status`=? WHERE id=?", 1);

        $this->assertGreaterThan(0, $deleted);
        $this->assertNull($this->cache->get($feedsKey));
        $this->assertSame('items-value', $this->cache->get($itemsKey));
    }

    public function testInvalidateFromQueryInvalidatesMultipleTagsForJoinTables(): void
    {
        $this->skipWithoutRedis();

        $categoriesKey = $this->cache->key('test', 'categories');
        $feedsKey = $this->cache->key('test', 'feeds2');
        $tagsKey = $this->cache->key('test', 'tags');

        $this->cache->set($categoriesKey, 'value', 60, ['categories']);
        $this->cache->set($feedsKey, 'value', 60, ['feeds']);
        $this->cache->set($tagsKey, 'value', 60, ['tags']);

        $deleted = CacheInvalidator::invalidateFromQuery("INSERT INTO `feed_categories` (`feed_id`, `category_id`) VALUES (?, ?)", 1);

        $this->assertGreaterThanOrEqual(2, $deleted);
        $this->assertNull($this->cache->get($categoriesKey));
        $this->assertNull($this->cache->get($feedsKey));
        $this->assertSame('value', $this->cache->get($tagsKey));
    }

    public function testExplicitInvalidateFeed(): void
    {
        $this->skipWithoutRedis();

        $feedKey = $this->cache->key('test', 'feed', '123');
        $feedsKey = $this->cache->key('test', 'feeds');
        $itemsKey = $this->cache->key('test', 'items');

        $this->cache->set($feedKey, 'value', 60, ['feed:123']);
        $this->cache->set($feedsKey, 'value', 60, ['feeds']);
        $this->cache->set($itemsKey, 'value', 60, ['items']);

        CacheInvalidator::invalidateFeed(123);

        $this->assertNull($this->cache->get($feedKey));
        $this->assertNull($this->cache->get($feedsKey));
        $this->assertNull($this->cache->get($itemsKey));
    }

    public function testDeduplicationWithinSameProcess(): void
    {
        $this->skipWithoutRedis();

        $key = $this->cache->key('test', 'feeds');
        $this->cache->set($key, 'value', 60, ['feeds']);

        CacheInvalidator::invalidateFeeds();
        $deleted = CacheInvalidator::invalidateFeeds();

        $this->assertNull($this->cache->get($key));
        $this->assertSame(0, $deleted);
    }

    public function testResetClearsDeduplication(): void
    {
        $this->skipWithoutRedis();

        $key = $this->cache->key('test', 'feeds');
        $this->cache->set($key, 'value', 60, ['feeds']);

        CacheInvalidator::invalidateFeeds();
        CacheInvalidator::reset();
        $deleted = CacheInvalidator::invalidateFeeds();

        $this->assertNull($this->cache->get($key));
        $this->assertGreaterThan(0, $deleted);
    }
}
