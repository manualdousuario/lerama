<?php

declare(strict_types=1);

namespace Lerama\Services;

use DB;

class CacheableQuery
{
    private static ?CacheService $cache = null;

    private static function getCache(): CacheService
    {
        if (self::$cache === null) {
            self::$cache = CacheService::getInstance();
        }
        return self::$cache;
    }

    public static function query(
        string $entity,
        string $operation,
        array $tags,
        ?int $ttl,
        string $sql,
        mixed ...$params
    ): array {
        $cache = self::getCache();
        
        $paramsHash = $cache->hash($params);
        $cacheKey = $cache->key($entity, $operation, $paramsHash);
        
        return $cache->remember($cacheKey, $ttl, $tags, function() use ($sql, $params) {
            return DB::query($sql, ...$params) ?: [];
        });
    }

    public static function queryFirstRow(
        string $entity,
        string $operation,
        array $tags,
        ?int $ttl,
        string $sql,
        mixed ...$params
    ): ?array {
        $cache = self::getCache();
        
        $paramsHash = $cache->hash($params);
        $cacheKey = $cache->key($entity, $operation, 'row', $paramsHash);
        
        return $cache->remember($cacheKey, $ttl, $tags, function() use ($sql, $params) {
            return DB::queryFirstRow($sql, ...$params);
        });
    }

    public static function queryFirstField(
        string $entity,
        string $operation,
        array $tags,
        ?int $ttl,
        string $sql,
        mixed ...$params
    ): mixed {
        $cache = self::getCache();
        
        $paramsHash = $cache->hash($params);
        $cacheKey = $cache->key($entity, $operation, 'field', $paramsHash);
        
        return $cache->remember($cacheKey, $ttl, $tags, function() use ($sql, $params) {
            return DB::queryFirstField($sql, ...$params);
        });
    }

    public static function queryWithKey(
        string $cacheKey,
        array $tags,
        ?int $ttl,
        string $sql,
        mixed ...$params
    ): array {
        $cache = self::getCache();
        
        return $cache->remember($cacheKey, $ttl, $tags, function() use ($sql, $params) {
            return DB::query($sql, ...$params) ?: [];
        });
    }

    public static function invalidate(string|array $tags): int
    {
        $cache = self::getCache();
        
        if (is_string($tags)) {
            return $cache->deleteByTag($tags);
        }
        
        return $cache->deleteByTags($tags);
    }

    public static function invalidateCategories(): int
    {
        return self::invalidate('categories');
    }

    public static function invalidateTags(): int
    {
        return self::invalidate('tags');
    }

    public static function invalidateFeeds(): int
    {
        return self::invalidate('feeds');
    }

    public static function invalidateItems(): int
    {
        return self::invalidate('items');
    }

    public static function invalidateFeed(int $feedId): int
    {
        return self::invalidate(['feeds', 'items', "feed:{$feedId}"]);
    }

    public static function invalidateAll(): bool
    {
        return self::getCache()->flush();
    }
}
