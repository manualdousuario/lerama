<?php

declare(strict_types=1);

namespace Lerama\Services;

use DB;

class CacheWarmer
{
    public static function warmImportant(?int $topFeedsLimit = null, ?callable $log = null): array
    {
        $step = static function (string $label, callable $fn) use ($log) {
            if ($log) {
                $log("  → warming {$label}...");
            }
            $start = microtime(true);
            $result = $fn();
            if ($log) {
                $elapsed = round((microtime(true) - $start) * 1000);
                $log("  ✓ {$label} done ({$elapsed}ms)");
            }
            return $result;
        };

        $summary = [
            'categories' => $step('categories', static fn () => self::warmCategories()),
            'tags' => $step('tags', static fn () => self::warmTags()),
            'feeds_dropdown' => $step('feeds_dropdown', static fn () => self::warmFeedsDropdown()),
            'home' => $step('home', static fn () => self::warmHome()),
            'top_feeds' => $step('top_feeds', static fn () => self::warmTopFeeds($topFeedsLimit)),
        ];

        return $summary;
    }

    public static function warmCategories(): int
    {
        $categories = CacheableQuery::query(
            'categories', 'all', ['categories'], 300,
            "SELECT * FROM categories ORDER BY name"
        );
        return count($categories);
    }

    public static function warmTags(): int
    {
        $tags = CacheableQuery::query(
            'tags', 'all', ['tags'], 300,
            "SELECT * FROM tags ORDER BY name"
        );
        return count($tags);
    }

    public static function warmFeedsDropdown(): int
    {
        $feeds = CacheableQuery::query(
            'feeds', 'dropdown', ['feeds'], 300,
            "SELECT id, title FROM feeds ORDER BY title"
        );
        return count($feeds);
    }

    public static function warmHome(): array
    {
        $cache = CacheService::getInstance();
        $perPage = (int)($_ENV['ITEMS_PER_PAGE'] ?? 21);

        $filterHash = $cache->hash([
            'search' => '',
            'feed' => null,
            'category' => null,
            'tag' => null,
            'page' => 1,
            'perPage' => $perPage,
            'latest' => 0
        ]);

        $countCacheKey = $cache->key('items', 'count', $filterHash);
        $totalCount = $cache->remember($countCacheKey, 300, ['items', 'feeds'], function () {
            return DB::queryFirstField(
                "SELECT COUNT(*) FROM feed_items fi JOIN feeds f ON fi.feed_id = f.id WHERE fi.is_visible = 1"
            );
        });

        $itemsCacheKey = $cache->key('items', 'home', $filterHash);
        $items = $cache->remember($itemsCacheKey, 60, ['items', 'feeds'], function () use ($perPage) {
            return DB::query(
                "SELECT fi.*, f.title as feed_title, f.site_url, f.language
                 FROM feed_items fi
                 JOIN feeds f ON fi.feed_id = f.id
                 WHERE fi.is_visible = 1
                 ORDER BY fi.published_at DESC
                 LIMIT %i, %i",
                0,
                $perPage
            ) ?: [];
        });

        return [
            'total_count' => $totalCount,
            'items_count' => count($items),
        ];
    }

    public static function warmTopFeeds(?int $limit = null): int
    {
        $limit = $limit ?? (int)($_ENV['CACHE_WARM_FEEDS_LIMIT'] ?? 10);
        if ($limit <= 0) {
            return 0;
        }

        $cache = CacheService::getInstance();
        $perPage = (int)($_ENV['ITEMS_PER_PAGE'] ?? 21);

        $feeds = DB::query(
            "SELECT id, slug FROM feeds WHERE status = 'online' ORDER BY last_updated DESC LIMIT %i",
            $limit
        ) ?: [];

        foreach ($feeds as $feed) {
            $feedId = (int)$feed['id'];
            $feedSlug = $feed['slug'];

            $filterHash = $cache->hash([
                'feed' => $feedId,
                'page' => 1,
                'perPage' => $perPage
            ]);

            $countCacheKey = $cache->key('items', 'feed', 'count', $filterHash);
            $cache->remember($countCacheKey, 60, ['items', 'feeds'], function () use ($feedId) {
                return DB::queryFirstField(
                    "SELECT COUNT(*) FROM feed_items WHERE feed_id = %i AND is_visible = 1",
                    $feedId
                );
            });

            $itemsCacheKey = $cache->key('items', 'feed', $filterHash);
            $cache->remember($itemsCacheKey, 60, ['items', 'feeds'], function () use ($feedId, $perPage) {
                return DB::query(
                    "SELECT fi.*, f.title as feed_title, f.site_url, f.language
                     FROM feed_items fi
                     JOIN feeds f ON fi.feed_id = f.id
                     WHERE fi.feed_id = %i AND fi.is_visible = 1
                     ORDER BY fi.published_at DESC
                     LIMIT %i, %i",
                    $feedId,
                    0,
                    $perPage
                ) ?: [];
            });
        }

        return count($feeds);
    }
}
