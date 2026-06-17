<?php

declare(strict_types=1);

namespace Lerama\Services;

use DB;

class FeedSlugService
{
    public static function fromUrl(string $url): string
    {
        $parsed = parse_url($url);
        if ($parsed === false) {
            return '';
        }

        if (empty($parsed['host'])) {
            return '';
        }

        $parts = [$parsed['host']];
        if (!empty($parsed['path']) && $parsed['path'] !== '/') {
            $parts[] = $parsed['path'];
        }
        if (!empty($parsed['query'])) {
            $parts[] = $parsed['query'];
        }

        $raw = implode('/', $parts);
        $raw = mb_strtolower($raw, 'UTF-8');

        $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT', $raw);
        if ($transliterated !== false) {
            $raw = $transliterated;
        }

        $slug = preg_replace('/[^a-z0-9]+/', '-', $raw);
        $slug = trim($slug, '-');

        return $slug;
    }

    public static function makeUnique(string $baseSlug, ?int $excludeFeedId = null): string
    {
        $slug = $baseSlug;
        $counter = 1;

        while (true) {
            $sql = "SELECT id FROM feeds WHERE slug = %s";
            $params = [$slug];

            if ($excludeFeedId !== null) {
                $sql .= " AND id != %i";
                $params[] = $excludeFeedId;
            }

            $existing = DB::queryFirstRow($sql, ...$params);
            if (!$existing) {
                return $slug;
            }

            $counter++;
            $slug = $baseSlug . '-' . $counter;
        }
    }

    public static function generateForFeed(string $siteUrl, ?int $excludeFeedId = null): string
    {
        $baseSlug = self::fromUrl($siteUrl);
        if ($baseSlug === '') {
            $baseSlug = 'feed';
        }

        return self::makeUnique($baseSlug, $excludeFeedId);
    }
}
