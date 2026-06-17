<?php

declare(strict_types=1);

namespace Tests\Unit;

use Lerama\Services\CacheService;
use PHPUnit\Framework\TestCase;

class CacheServiceTest extends TestCase
{
    private CacheService $cache;

    protected function setUp(): void
    {
        $this->cache = CacheService::getInstance();
        if (!$this->cache->isAvailable()) {
            $this->markTestSkipped('Redis is not available');
        }
    }

    protected function tearDown(): void
    {
        if ($this->cache->isAvailable()) {
            $this->cache->flush();
        }
    }

    public function testRememberStoresCallbackResult(): void
    {
        $key = $this->cache->key('test', 'remember');
        $calls = 0;

        $value = $this->cache->remember($key, 60, ['test'], function () use (&$calls) {
            $calls++;
            return 'computed';
        });

        $this->assertSame('computed', $value);
        $this->assertSame(1, $calls);

        // Second call should reuse cached value without executing callback
        $cached = $this->cache->remember($key, 60, ['test'], function () use (&$calls) {
            $calls++;
            return 'recomputed';
        });

        $this->assertSame('computed', $cached);
        $this->assertSame(1, $calls);
    }

    public function testLockCanBeAcquiredAndReleased(): void
    {
        $key = $this->cache->key('test', 'lock');

        $token = $this->cache->lock($key, 10);
        $this->assertNotNull($token);

        // Same lock cannot be acquired by another caller while held
        $secondToken = $this->cache->lock($key, 10);
        $this->assertNull($secondToken);

        // Only the owner can unlock
        $this->assertTrue($this->cache->unlock($key, $token));

        // After release, lock can be acquired again
        $newToken = $this->cache->lock($key, 10);
        $this->assertNotNull($newToken);
        $this->cache->unlock($key, $newToken);
    }

    public function testForeignTokenCannotUnlock(): void
    {
        $key = $this->cache->key('test', 'foreign');
        $token = $this->cache->lock($key, 10);
        $this->assertNotNull($token);

        $this->assertFalse($this->cache->unlock($key, 'invalid-token'));

        $this->cache->unlock($key, $token);
    }

    public function testRememberExecutesCallbackOnlyOnceUnderConcurrency(): void
    {
        $key = $this->cache->key('test', 'concurrent');
        $calls = 0;
        $callLock = fopen('php://temp', 'r+');

        $callback = function () use (&$calls, $callLock) {
            flock($callLock, LOCK_EX);
            $calls++;
            flock($callLock, LOCK_UN);
            usleep(200000); // simulate slow computation
            return 'shared-value';
        };

        $results = [];
        for ($i = 0; $i < 3; $i++) {
            $results[] = $this->cache->remember($key, 60, ['test'], $callback);
        }

        $this->assertSame(1, $calls);
        $this->assertSame(['shared-value', 'shared-value', 'shared-value'], $results);
    }
}
