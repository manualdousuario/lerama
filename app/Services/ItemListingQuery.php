<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class ItemListingQuery
{
    public const SELECT = [
        'feed_items.id',
        'feed_items.feed_id',
        'feed_items.title',
        'feed_items.author',
        'feed_items.url',
        'feed_items.image_url',
        'feed_items.published_at',
        'feed_items.excerpt',
        'f.title as feed_title',
        'f.site_url',
        'f.language',
    ];

    public static function base(): Builder
    {
        return DB::table('feed_items')
            ->join('feeds as f', 'feed_items.feed_id', '=', 'f.id')
            ->where('feed_items.is_visible', 1);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function page(Builder $query, int $offset, int $perPage): array
    {
        return $query
            ->select(self::SELECT)
            ->orderByDesc('feed_items.published_at')
            ->offset($offset)
            ->limit($perPage)
            ->get()
            ->map(fn ($item) => (array) $item)
            ->all();
    }
}
