<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class FeedListingQuery
{
    public const PER_PAGE = 50;

    public static function base(?string $categorySlug, ?string $tagSlug): Builder
    {
        $query = DB::table('feeds as f')->select('f.*')->distinct();

        if ($categorySlug) {
            $query->join('feed_categories as fc', 'fc.feed_id', '=', 'f.id')
                ->join('categories as c', function ($join) use ($categorySlug): void {
                    $join->on('c.id', '=', 'fc.category_id')
                        ->where('c.slug', '=', $categorySlug);
                });
        }

        if ($tagSlug) {
            $query->join('feed_tags as ft', 'ft.feed_id', '=', 'f.id')
                ->join('tags as t', function ($join) use ($tagSlug): void {
                    $join->on('t.id', '=', 'ft.tag_id')
                        ->where('t.slug', '=', $tagSlug);
                });
        }

        return $query;
    }

    public static function withTaxonomy(array $feeds): array
    {
        if (empty($feeds)) {
            return $feeds;
        }

        $feedIds = array_column($feeds, 'id');

        $categoryRows = DB::table('feed_categories as fc')
            ->join('categories as c', 'c.id', '=', 'fc.category_id')
            ->whereIn('fc.feed_id', $feedIds)
            ->orderBy('c.name')
            ->get(['fc.feed_id', 'c.id', 'c.name', 'c.slug']);

        $tagRows = DB::table('feed_tags as ft')
            ->join('tags as t', 't.id', '=', 'ft.tag_id')
            ->whereIn('ft.feed_id', $feedIds)
            ->orderBy('t.name')
            ->get(['ft.feed_id', 't.id', 't.name', 't.slug']);

        $categoriesByFeed = [];
        foreach ($categoryRows as $row) {
            $categoriesByFeed[$row->feed_id][] = (array) $row;
        }
        $tagsByFeed = [];
        foreach ($tagRows as $row) {
            $tagsByFeed[$row->feed_id][] = (array) $row;
        }

        foreach ($feeds as &$feed) {
            $feed['categories'] = $categoriesByFeed[$feed['id']] ?? [];
            $feed['tags'] = $tagsByFeed[$feed['id']] ?? [];
        }

        return $feeds;
    }
}
