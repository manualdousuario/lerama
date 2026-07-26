<?php

namespace App\Services;

use App\Models\Feed;
use App\Support\Slugger;

// Builds unique feed slugs from site_url (host + path + query, transliterated).
class FeedSlugService
{
    public static function fromUrl(string $url): string
    {
        $parsed = parse_url($url);
        if ($parsed === false || empty($parsed['host'])) {
            return '';
        }

        $parts = [$parsed['host']];
        if (! empty($parsed['path']) && $parsed['path'] !== '/') {
            $parts[] = $parsed['path'];
        }
        if (! empty($parsed['query'])) {
            $parts[] = $parsed['query'];
        }

        return Slugger::slug(implode('/', $parts));
    }

    public static function makeUnique(string $baseSlug, ?int $excludeFeedId = null): string
    {
        $slug = $baseSlug;
        $counter = 1;

        while (true) {
            $exists = Feed::query()
                ->where('slug', $slug)
                ->when($excludeFeedId !== null, fn ($q) => $q->where('id', '!=', $excludeFeedId))
                ->exists();

            if (! $exists) {
                return $slug;
            }

            $counter++;
            $slug = $baseSlug.'-'.$counter;
        }
    }

    public static function generateForFeed(string $siteUrl, ?int $excludeFeedId = null): string
    {
        $baseSlug = self::fromUrl($siteUrl);

        return self::makeUnique($baseSlug === '' ? 'feed' : $baseSlug, $excludeFeedId);
    }
}
