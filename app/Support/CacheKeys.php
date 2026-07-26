<?php

namespace App\Support;

/**
 * Centralized cache keys. Controllers and CacheWarmer share these so their
 * hashes can never drift apart.
 */
class CacheKeys
{
    public static function hash(array $data): string
    {
        return substr(md5(serialize($data)), 0, 12);
    }

    public static function homeHash(string $search, ?int $feedId, ?string $categorySlug, ?string $tagSlug, int $page, int $perPage, bool $latest): string
    {
        return self::hash([
            'search' => $search,
            'feed' => $feedId,
            'category' => $categorySlug,
            'tag' => $tagSlug,
            'page' => $page,
            'perPage' => $perPage,
            'latest' => $latest ? 1 : 0,
        ]);
    }

    public static function feedItemsHash(int $feedId, int $page, int $perPage): string
    {
        return self::hash([
            'feed' => $feedId,
            'page' => $page,
            'perPage' => $perPage,
        ]);
    }

    public static function feedsListHash(?string $categorySlug, ?string $tagSlug, int $page, int $perPage): string
    {
        return self::hash([
            'category' => $categorySlug,
            'tag' => $tagSlug,
            'page' => $page,
            'perPage' => $perPage,
        ]);
    }

    public static function outputHash(array $categorySlugs, array $tagSlugs, int $page, int $perPage): string
    {
        return self::hash([
            'categories' => array_values($categorySlugs),
            'tags' => array_values($tagSlugs),
            'page' => $page,
            'perPage' => $perPage,
        ]);
    }
}
