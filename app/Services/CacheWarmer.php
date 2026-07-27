<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Feed;
use App\Models\Tag;
use App\Support\CacheKeys;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

// Warms the hottest cache keys after a processing run (or via cache:warm).
class CacheWarmer
{
    /**
     * @return array{categories: int, tags: int, feeds_dropdown: int, home: array{items_count: int, total_count: int}, feeds_list: int, top_feeds: int, thumbnails: int}
     */
    public function warmImportant(): array
    {
        $perPage = (int) config('lerama.items_per_page', 21);

        $categories = Cache::flexible('categories:all', CacheKeys::TTL_LISTS, fn () => Category::orderBy('name')->get()->toArray());

        $tags = Cache::flexible('tags:all', CacheKeys::TTL_LISTS, fn () => Tag::orderBy('name')->get()->toArray());

        $feedsDropdown = Cache::flexible('feeds:dropdown', CacheKeys::TTL_LISTS, fn () => Feed::orderBy('title')->get(['id', 'title'])->toArray());

        $thumbnails = 0;
        $homeItems = [];

        foreach ([1, 2] as $page) {
            $homeHash = CacheKeys::homeHash('', null, null, null, $page, $perPage, false);

            $homeTotal = (int) Cache::flexible(
                "items:count:{$homeHash}",
                CacheKeys::TTL_LISTS,
                fn () => (int) DB::table('feeds')->sum('visible_item_count')
            );

            $items = Cache::flexible(
                "items:home:{$homeHash}",
                CacheKeys::TTL_ITEMS,
                fn () => ItemListingQuery::page(ItemListingQuery::base(), ($page - 1) * $perPage, $perPage)
            );

            $thumbnails += $this->warmThumbnails($items);

            if ($page === 1) {
                $homeItems = $items;
            }
        }

        // /feeds, page 1, no filters.
        $feedsHash = CacheKeys::feedsListHash(null, null, 1, FeedListingQuery::PER_PAGE);

        Cache::flexible(
            "feeds:count:{$feedsHash}",
            CacheKeys::TTL_LISTS,
            fn () => DB::query()->fromSub(FeedListingQuery::base(null, null), 'f')->count()
        );

        $feedsList = Cache::flexible(
            "feeds:list:{$feedsHash}",
            CacheKeys::TTL_LISTS,
            fn () => FeedListingQuery::withTaxonomy(
                FeedListingQuery::base(null, null)
                    ->orderBy('f.title')
                    ->limit(FeedListingQuery::PER_PAGE)
                    ->get()
                    ->map(fn ($feed) => (array) $feed)
                    ->all()
            )
        );

        // Top N online feeds by last_updated.
        $topFeeds = Feed::online()->orderByDesc('last_updated')->limit((int) config('lerama.cache.warm_feeds_limit', 10))->get(['id', 'visible_item_count']);

        foreach ($topFeeds as $feed) {
            $hash = CacheKeys::feedItemsHash($feed->id, 1, $perPage);

            Cache::flexible(
                "items:feed:count:{$hash}",
                CacheKeys::TTL_ITEMS,
                fn () => (int) $feed->visible_item_count
            );

            $items = Cache::flexible(
                "items:feed:{$hash}",
                CacheKeys::TTL_ITEMS,
                fn () => ItemListingQuery::page(
                    ItemListingQuery::base()->where('feed_items.feed_id', $feed->id),
                    0,
                    $perPage
                )
            );

            $thumbnails += $this->warmThumbnails($items);
        }

        return [
            'categories' => count($categories),
            'tags' => count($tags),
            'feeds_dropdown' => count($feedsDropdown),
            'home' => ['items_count' => count($homeItems), 'total_count' => $homeTotal ?? 0],
            'feeds_list' => count($feedsList),
            'top_feeds' => $topFeeds->count(),
            'thumbnails' => $thumbnails,
        ];
    }

    private function warmThumbnails(array $items): int
    {
        $service = app(ThumbnailService::class);
        $generated = 0;

        foreach ($items as $item) {
            $url = $item['image_url'] ?? null;

            if (empty($url)) {
                continue;
            }

            foreach ([[180, 100], [360, 200]] as [$width, $height]) {
                if (! $service->hasThumbnail($url, $width, $height)) {
                    $service->getThumbnail($url, $width, $height);
                    $generated++;
                }
            }
        }

        return $generated;
    }
}
