<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Feed;
use App\Models\Tag;
use App\Support\CacheKeys;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

// Warms the hottest cache keys after a processing run.
class CacheWarmer
{
    /**
     * @return array{categories: int, tags: int, feeds_dropdown: int, home: array{items_count: int, total_count: int}, top_feeds: int}
     */
    public function warmImportant(): array
    {
        $perPage = (int) config('lerama.items_per_page', 21);

        $categories = Cache::remember('categories:all', 300, fn () => Category::orderBy('name')->get()->toArray());

        $tags = Cache::remember('tags:all', 300, fn () => Tag::orderBy('name')->get()->toArray());

        $feedsDropdown = Cache::remember('feeds:dropdown', 300, fn () => Feed::orderBy('title')->get(['id', 'title'])->toArray());

        // Home, page 1, no filters.
        $homeHash = CacheKeys::homeHash('', null, null, null, 1, $perPage, false);

        $homeTotal = (int) Cache::remember(
            "items:count:{$homeHash}",
            300,
            fn () => DB::table('feed_items')->join('feeds as f', 'feed_items.feed_id', '=', 'f.id')->where('feed_items.is_visible', 1)->count()
        );

        $homeItems = Cache::remember(
            "items:home:{$homeHash}",
            60,
            fn () => DB::table('feed_items')
                ->join('feeds as f', 'feed_items.feed_id', '=', 'f.id')
                ->where('feed_items.is_visible', 1)
                ->select('feed_items.*', 'f.title as feed_title', 'f.site_url', 'f.language')
                ->orderByDesc('feed_items.published_at')
                ->limit($perPage)
                ->get()
                ->map(fn ($item) => (array) $item)
                ->all()
        );

        // Top N online feeds by last_updated.
        $topFeeds = Feed::online()->orderByDesc('last_updated')->limit((int) config('lerama.cache.warm_feeds_limit', 10))->get(['id']);

        foreach ($topFeeds as $feed) {
            $hash = CacheKeys::feedItemsHash($feed->id, 1, $perPage);

            Cache::remember(
                "items:feed:count:{$hash}",
                60,
                fn () => DB::table('feed_items')->where('feed_id', $feed->id)->where('is_visible', 1)->count()
            );

            Cache::remember(
                "items:feed:{$hash}",
                60,
                fn () => DB::table('feed_items')
                    ->join('feeds as f', 'feed_items.feed_id', '=', 'f.id')
                    ->where('feed_items.feed_id', $feed->id)
                    ->where('feed_items.is_visible', 1)
                    ->select('feed_items.*', 'f.title as feed_title', 'f.site_url', 'f.language')
                    ->orderByDesc('feed_items.published_at')
                    ->limit($perPage)
                    ->get()
                    ->map(fn ($item) => (array) $item)
                    ->all()
            );
        }

        return [
            'categories' => count($categories),
            'tags' => count($tags),
            'feeds_dropdown' => count($feedsDropdown),
            'home' => ['items_count' => count($homeItems), 'total_count' => $homeTotal],
            'top_feeds' => $topFeeds->count(),
        ];
    }
}
