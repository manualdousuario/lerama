<?php

declare(strict_types=1);

namespace Lerama\Services;

class CacheInvalidator
{
    private const TABLE_TAG_MAP = [
        'feed_items' => ['items'],
        'feeds' => ['feeds'],
        'categories' => ['categories'],
        'tags' => ['tags'],
        'feed_categories' => ['categories', 'feeds'],
        'feed_tags' => ['tags', 'feeds'],
    ];

    private static array $invalidatedTags = [];

    public static function invalidateFromQuery(string $sql, ?int $affectedRows = null): int
    {
        if ($affectedRows !== null && $affectedRows <= 0) {
            return 0;
        }

        $table = self::extractTableFromQuery($sql);
        if ($table === null) {
            return 0;
        }

        $tags = self::TABLE_TAG_MAP[$table] ?? null;
        if ($tags === null) {
            return 0;
        }

        return self::invalidateTagsInternal($tags);
    }

    public static function extractTableFromQuery(string $sql): ?string
    {
        $sql = ltrim($sql);
        $upperSql = strtoupper(substr($sql, 0, 120));

        if (preg_match('/^(?:INSERT\s+(?:IGNORE\s+)?INTO|REPLACE\s+INTO)\s+`?(\w+)`?/i', $sql, $matches)) {
            return $matches[1];
        }

        if (preg_match('/^UPDATE\s+`?(\w+)`?(?:\s+SET\s+|\s+,)/i', $sql, $matches)) {
            return $matches[1];
        }

        if (preg_match('/^DELETE\s+FROM\s+`?(\w+)`?/i', $sql, $matches)) {
            return $matches[1];
        }

        if (preg_match('/^DELETE\s+`?(\w+)`?\s+FROM/i', $sql, $matches)) {
            return $matches[1];
        }

        if (preg_match('/^TRUNCATE(?:\s+TABLE)?\s+`?(\w+)`?/i', $sql, $matches)) {
            return $matches[1];
        }

        return null;
    }

    public static function invalidate(string|array $tags): int
    {
        if (is_string($tags)) {
            $tags = [$tags];
        }

        return self::invalidateTagsInternal($tags);
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
        self::$invalidatedTags = [];
        return CacheService::getInstance()->flush();
    }

    public static function reset(): void
    {
        self::$invalidatedTags = [];
    }

    private static function invalidateTagsInternal(array $tags): int
    {
        $tagsToInvalidate = [];
        foreach ($tags as $tag) {
            if (isset(self::$invalidatedTags[$tag])) {
                continue;
            }
            self::$invalidatedTags[$tag] = true;
            $tagsToInvalidate[] = $tag;
        }

        if (empty($tagsToInvalidate)) {
            return 0;
        }

        return CacheableQuery::invalidate($tagsToInvalidate);
    }
}
