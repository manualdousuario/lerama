<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Feed;
use App\Models\Tag;
use App\Services\FeedListingQuery;
use App\Services\ItemListingQuery;
use App\Support\CacheKeys;
use App\Support\HtmlSanitizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class FeedController extends Controller
{
    public function index(Request $request, ?int $page = null)
    {
        $page = max(1, (int) ($page ?? 1));
        $perPage = FeedListingQuery::PER_PAGE;
        $offset = ($page - 1) * $perPage;

        $categorySlug = $request->query('category');
        $tagSlug = $request->query('tag');

        $filterHash = CacheKeys::feedsListHash($categorySlug, $tagSlug, $page, $perPage);

        $totalCount = (int) Cache::flexible(
            "feeds:count:{$filterHash}",
            CacheKeys::TTL_LISTS,
            fn () => DB::query()->fromSub(FeedListingQuery::base($categorySlug, $tagSlug), 'f')->count()
        );

        $totalPages = (int) ceil($totalCount / $perPage);

        $feeds = Cache::flexible(
            "feeds:list:{$filterHash}",
            CacheKeys::TTL_LISTS,
            fn () => FeedListingQuery::withTaxonomy(
                FeedListingQuery::base($categorySlug, $tagSlug)
                    ->orderBy('f.title')
                    ->offset($offset)
                    ->limit($perPage)
                    ->get()
                    ->map(fn ($feed) => (array) $feed)
                    ->all()
            )
        );

        return view('feeds', [
            'feeds' => $feeds,
            'categories' => $this->cachedCategories(),
            'tags' => $this->cachedTags(),
            'selectedCategory' => $categorySlug,
            'selectedTag' => $tagSlug,
            'pagination' => [
                'current' => $page,
                'total' => $totalPages,
                'baseUrl' => '/feeds/page/',
            ],
            'title' => __('feeds.title'),
        ]);
    }

    public function show(Request $request, string $slug, ?int $page = null)
    {
        $page = max(1, (int) ($page ?? 1));
        $perPage = (int) config('lerama.items_per_page', 21);
        $offset = ($page - 1) * $perPage;

        $feed = Feed::where('slug', $slug)->first();
        if (! $feed) {
            return redirect('/feeds');
        }

        $feedId = $feed->id;

        $filterHash = CacheKeys::feedItemsHash($feedId, $page, $perPage);

        // Denormalised counter kept by ItemCountService; no COUNT(*) scan.
        $totalCount = (int) Cache::flexible(
            "items:feed:count:{$filterHash}",
            CacheKeys::TTL_ITEMS,
            fn () => (int) $feed->visible_item_count
        );

        $totalPages = (int) ceil($totalCount / $perPage);

        if ($page > $totalPages && $totalPages > 0) {
            return redirect('/feeds/'.urlencode($slug).'/page/'.$totalPages);
        }

        $items = Cache::flexible(
            "items:feed:{$filterHash}",
            CacheKeys::TTL_ITEMS,
            fn () => ItemListingQuery::page(
                ItemListingQuery::base()->where('feed_items.feed_id', $feedId),
                $offset,
                $perPage
            )
        );

        return view('feed-items', [
            'feed' => $feed->toArray(),
            'items' => $items,
            'categories' => $this->cachedCategories(),
            'tags' => $this->cachedTags(),
            'pagination' => [
                'current' => $page,
                'total' => $totalPages,
                'baseUrl' => '/feeds/'.urlencode($slug).'/page/',
            ],
            'title' => $feed->title,
        ]);
    }

    public function json(Request $request)
    {
        [$query, $page, $perPage, $offset, $filterHash] = $this->feedOutputQuery($request);

        $totalCount = (int) Cache::flexible(
            "items:json:count:{$filterHash}",
            CacheKeys::TTL_ITEMS,
            fn () => $query()->count()
        );

        $items = Cache::flexible(
            "items:json:{$filterHash}",
            CacheKeys::TTL_ITEMS,
            fn () => $query()
                ->select(
                    'feed_items.id', 'feed_items.title', 'feed_items.author', 'feed_items.content',
                    'feed_items.url', 'feed_items.image_url', 'feed_items.published_at',
                    'f.title as feed_title', 'f.site_url as feed_site_url'
                )
                ->orderByDesc('feed_items.published_at')
                ->offset($offset)
                ->limit($perPage)
                ->get()
                ->map(fn ($item) => (array) $item)
                ->all()
        );

        $formattedItems = array_map(function (array $item): array {
            $author = ! empty($item['author'])
                ? $item['author'].' em '.$item['feed_title']
                : $item['feed_title'];

            $safeUrl = htmlspecialchars((string) $item['url']);
            $safeFeedTitle = htmlspecialchars((string) $item['feed_title']);
            $safeContent = HtmlSanitizer::sanitize($item['content']);

            return [
                'id' => $item['id'],
                'title' => $item['title'],
                'author' => $author,
                'content' => '<p>Leia no <a href="'.$safeUrl.'">'.$safeFeedTitle.'</a></p>'.$safeContent,
                'url' => $item['url'],
                'image_url' => $item['image_url'],
                'published_at' => $item['published_at'],
                'feed' => [
                    'title' => $item['feed_title'],
                    'site_url' => $item['feed_site_url'],
                ],
            ];
        }, $items);

        return response()->json([
            'items' => $formattedItems,
            'pagination' => [
                'total_items' => $totalCount,
                'total_pages' => (int) ceil($totalCount / $perPage),
                'current_page' => $page,
                'per_page' => $perPage,
            ],
        ]);
    }

    public function rss(Request $request)
    {
        [$query, $page, $perPage, $offset, $filterHash] = $this->feedOutputQuery($request);

        $items = Cache::flexible(
            "items:rss:{$filterHash}",
            CacheKeys::TTL_ITEMS,
            fn () => $query()
                ->select(
                    'feed_items.id', 'feed_items.title', 'feed_items.author', 'feed_items.content',
                    'feed_items.url', 'feed_items.image_url', 'feed_items.published_at',
                    'f.title as feed_title', 'f.site_url as feed_site_url'
                )
                ->orderByDesc('feed_items.published_at')
                ->offset($offset)
                ->limit($perPage)
                ->get()
                ->map(fn ($item) => (array) $item)
                ->all()
        );

        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><rss version="2.0"></rss>');

        $channel = $xml->addChild('channel');
        $channel->addChild('title', htmlspecialchars((string) config('app.name')));
        $channel->addChild('link', htmlspecialchars((string) config('app.url')));
        $channel->addChild('description', 'Diretório e buscador de blogs pessoais atualizado em tempo real.');
        $channel->addChild('language', 'pt-br');
        $channel->addChild('pubDate', date('r'));

        foreach ($items as $item) {
            $xmlItem = $channel->addChild('item');
            $xmlItem->addChild('title', htmlspecialchars((string) $item['title']));

            $author = ! empty($item['author'])
                ? $item['author'].' em '.$item['feed_title']
                : $item['feed_title'];
            $xmlItem->addChild('author', htmlspecialchars((string) $author));

            $xmlItem->addChild('link', htmlspecialchars((string) $item['url']));
            $xmlItem->addChild('guid', htmlspecialchars((string) $item['url']));
            $xmlItem->addChild('pubDate', date('r', strtotime((string) $item['published_at'])));

            if (! empty($item['image_url'])) {
                $enclosure = $xmlItem->addChild('enclosure');
                $enclosure->addAttribute('url', htmlspecialchars((string) $item['image_url']));
                $enclosure->addAttribute('type', 'image/jpeg');
            }

            $safeUrl = htmlspecialchars((string) $item['url']);
            $safeFeedTitle = htmlspecialchars((string) $item['feed_title']);
            $safeContent = HtmlSanitizer::sanitize($item['content']);

            $contentWithLink = '<p>Leia no <a href="'.$safeUrl.'">'.$safeFeedTitle.'</a></p>'.$safeContent;

            $description = $xmlItem->addChild('description');
            $node = dom_import_simplexml($description);
            $node->appendChild($node->ownerDocument->createCDATASection($contentWithLink));

            $source = $xmlItem->addChild('source', htmlspecialchars((string) $item['feed_title']));
            $source->addAttribute('url', htmlspecialchars((string) $item['feed_site_url']));
        }

        return response($xml->asXML(), 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function feedBuilder()
    {
        return view('feed-builder', [
            'title' => __('feed_builder.title'),
        ]);
    }

    /**
     * Shared query behind /feed/json and /feed/rss, filtering by category/tag
     * slugs through EXISTS subqueries.
     *
     * @return array{0: \Closure, 1: int, 2: int, 3: int, 4: string}
     */
    private function feedOutputQuery(Request $request): array
    {
        $params = $request->query();
        $page = max(1, (int) ($params['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($params['per_page'] ?? 20)));
        $offset = ($page - 1) * $perPage;

        $categorySlugs = [];
        if (isset($params['category'])) {
            $categorySlugs = [(string) $params['category']];
        } elseif (isset($params['categories'])) {
            $categorySlugs = array_filter(explode(',', (string) $params['categories']));
        }

        $tagSlugs = [];
        if (isset($params['tag'])) {
            $tagSlugs = [(string) $params['tag']];
        } elseif (isset($params['tags'])) {
            $tagSlugs = array_filter(explode(',', (string) $params['tags']));
        }

        $query = function () use ($categorySlugs, $tagSlugs) {
            $q = DB::table('feed_items')
                ->join('feeds as f', 'feed_items.feed_id', '=', 'f.id')
                ->where('feed_items.is_visible', 1);

            if (! empty($categorySlugs)) {
                $q->whereExists(function ($sub) use ($categorySlugs): void {
                    $sub->select(DB::raw(1))
                        ->from('feed_categories as fc')
                        ->join('categories as c', 'fc.category_id', '=', 'c.id')
                        ->whereColumn('fc.feed_id', 'f.id')
                        ->whereIn('c.slug', array_values($categorySlugs));
                });
            }

            if (! empty($tagSlugs)) {
                $q->whereExists(function ($sub) use ($tagSlugs): void {
                    $sub->select(DB::raw(1))
                        ->from('feed_tags as ft')
                        ->join('tags as t', 'ft.tag_id', '=', 't.id')
                        ->whereColumn('ft.feed_id', 'f.id')
                        ->whereIn('t.slug', array_values($tagSlugs));
                });
            }

            return $q;
        };

        $filterHash = CacheKeys::outputHash(array_values($categorySlugs), array_values($tagSlugs), $page, $perPage);

        return [$query, $page, $perPage, $offset, $filterHash];
    }

    private function cachedCategories(): array
    {
        return Cache::flexible('categories:all', CacheKeys::TTL_LISTS, fn () => Category::orderBy('name')->get()->toArray());
    }

    private function cachedTags(): array
    {
        return Cache::flexible('tags:all', CacheKeys::TTL_LISTS, fn () => Tag::orderBy('name')->get()->toArray());
    }
}
