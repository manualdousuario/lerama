<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Feed;
use App\Models\Tag;
use App\Support\CacheKeys;
use App\Support\UrlValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class HomeController extends Controller
{
    public function index(Request $request, ?string $category = null, ?string $tag = null, ?int $page = null)
    {
        $params = $request->query();

        // Redirect 301: ?category=X / ?tag=X -> /category/X / /tag/X
        if ($category === null && $tag === null) {
            $redirectPath = null;
            $remaining = $params;

            if (! empty($params['category'])) {
                $redirectPath = '/category/'.rawurlencode($params['category']);
                unset($remaining['category']);
            } elseif (! empty($params['tag'])) {
                $redirectPath = '/tag/'.rawurlencode($params['tag']);
                unset($remaining['tag']);
            }

            if ($redirectPath !== null) {
                $url = $redirectPath.($remaining ? '?'.http_build_query($remaining) : '');

                return redirect($url, 301);
            }
        }

        $page = max(1, (int) ($page ?? 1));
        $perPage = (int) config('lerama.items_per_page', 21);
        $offset = ($page - 1) * $perPage;

        $search = (string) ($params['search'] ?? '');
        $feedId = isset($params['feed']) ? (int) $params['feed'] : null;
        $categorySlug = $category ?? ($params['category'] ?? null);
        $tagSlug = $tag ?? ($params['tag'] ?? null);
        $latestPerFeed = ! empty($params['latest']);

        $paginationBaseUrl = $category !== null
            ? '/category/'.$category.'/page/'
            : ($tag !== null ? '/tag/'.$tag.'/page/' : '/page/');

        $buildQuery = function () use ($search, $feedId, $categorySlug, $tagSlug, $latestPerFeed) {
            $query = DB::table('feed_items')
                ->join('feeds as f', 'feed_items.feed_id', '=', 'f.id')
                ->where('feed_items.is_visible', 1);

            if ($latestPerFeed) {
                $query->whereColumn('feed_items.id', 'f.last_feed_item_id');
            }

            if ($search !== '') {
                $query->whereRaw('MATCH(feed_items.title, feed_items.content) AGAINST (? IN BOOLEAN MODE)', [$search]);
            }

            if ($feedId) {
                $query->where('feed_items.feed_id', $feedId);
            }

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
        };

        $filterHash = CacheKeys::homeHash($search, $feedId, $categorySlug, $tagSlug, $page, $perPage, $latestPerFeed);

        $hasFilters = $search !== '' || $feedId || $categorySlug || $tagSlug || $latestPerFeed;

        $totalCount = (int) Cache::remember(
            "items:count:{$filterHash}",
            $hasFilters ? 60 : 300,
            fn () => $buildQuery()->count()
        );

        $totalPages = (int) ceil($totalCount / $perPage);

        if ($page > $totalPages && $totalPages > 0) {
            unset($params['page']);

            return redirect($paginationBaseUrl.$totalPages.($params ? '?'.http_build_query($params) : ''));
        }

        $items = Cache::remember(
            "items:home:{$filterHash}",
            60,
            fn () => $buildQuery()
                ->select('feed_items.*', 'f.title as feed_title', 'f.site_url', 'f.language')
                ->orderByDesc('feed_items.published_at')
                ->offset($offset)
                ->limit($perPage)
                ->get()
                ->map(fn ($item) => (array) $item)
                ->all()
        );

        return view('home', [
            'items' => $items,
            'feeds' => $this->cachedFeedsDropdown(),
            'categories' => $this->cachedCategories(),
            'tags' => $this->cachedTags(),
            'search' => $search,
            'selectedFeed' => $feedId,
            'selectedCategory' => $categorySlug,
            'selectedTag' => $tagSlug,
            'latestPerFeed' => $latestPerFeed,
            'pagination' => [
                'current' => $page,
                'total' => $totalPages,
                'baseUrl' => $paginationBaseUrl,
            ],
            'tagInPath' => $tag,
            'categoryInPath' => $category,
            'title' => __('home.title'),
        ]);
    }

    public function categories()
    {
        return view('categories-list', [
            'categories' => $this->cachedCategories(),
            'title' => __('categories.title'),
        ]);
    }

    public function tags()
    {
        return view('tags-list', [
            'tags' => $this->cachedTags(),
            'title' => __('tags.title'),
        ]);
    }

    public function random()
    {
        $days = (int) config('lerama.random_post_days', 30);

        $pool = Cache::remember("random:pool:{$days}", 300, function () use ($days) {
            return DB::table('feed_items')
                ->where('is_visible', 1)
                ->where('published_at', '>=', now()->subDays($days))
                ->orderByDesc('published_at')
                ->limit(500)
                ->pluck('url')
                ->all();
        });

        if (! empty($pool)) {
            $url = $pool[array_rand($pool)];

            if (! empty($url) && UrlValidator::validateRedirectUrl($url)) {
                return redirect($url);
            }
        }

        return redirect('/');
    }

    public function shuffle(Request $request)
    {
        $url = $this->shuffleUrl();

        if ($request->query('ajax') === '1') {
            return response()->json(['url' => $url])
                ->header('Access-Control-Allow-Origin', '*')
                ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type')
                ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
                ->header('Pragma', 'no-cache')
                ->header('Expires', '0');
        }

        return view('shuffle', [
            'title' => __('nav.shuffle'),
            'initialUrl' => $url,
        ]);
    }

    private function shuffleUrl(): string
    {
        $pool = Cache::remember('shuffle:pool', 300, fn () => Feed::shuffleable()->pluck('site_url')->all());

        $url = ! empty($pool) ? ($pool[array_rand($pool)] ?? '') : '';

        if (! empty($url) && ! UrlValidator::validateRedirectUrl($url)) {
            return '';
        }

        return (string) $url;
    }

    private function cachedCategories(): array
    {
        return Cache::remember('categories:all', 300, fn () => Category::orderBy('name')->get()->toArray());
    }

    private function cachedTags(): array
    {
        return Cache::remember('tags:all', 300, fn () => Tag::orderBy('name')->get()->toArray());
    }

    private function cachedFeedsDropdown(): array
    {
        return Cache::remember('feeds:dropdown', 300, fn () => Feed::orderBy('title')->get(['id', 'title'])->toArray());
    }
}
