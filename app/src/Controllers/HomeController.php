<?php

declare(strict_types=1);

namespace Lerama\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Laminas\Diactoros\Response\HtmlResponse;
use Laminas\Diactoros\Response\RedirectResponse;
use League\Plates\Engine;
use DB;
use Lerama\Services\ThumbnailService;
use Lerama\Services\CacheService;
use Lerama\Services\CacheableQuery;
use Lerama\Services\UrlValidator;

class HomeController
{
    private Engine $templates;
    private ThumbnailService $thumbnailService;
    private CacheService $cache;

    public function __construct()
    {
        $this->templates = new Engine(__DIR__ . '/../../templates');
        $this->thumbnailService = new ThumbnailService();
        $this->cache = CacheService::getInstance();
    }

    public function index(ServerRequestInterface $request, array $args = []): ResponseInterface
    {
        $params = $request->getQueryParams();

        $categoryFromPath = $args['category'] ?? null;
        $tagFromPath = $args['tag'] ?? null;

        if ($categoryFromPath === null && $tagFromPath === null) {
            $redirectPath = null;
            $remainingParams = $params;
            if (!empty($params['category'])) {
                $redirectPath = '/category/' . rawurlencode($params['category']);
                unset($remainingParams['category']);
            } elseif (!empty($params['tag'])) {
                $redirectPath = '/tag/' . rawurlencode($params['tag']);
                unset($remainingParams['tag']);
            }
            if ($redirectPath !== null) {
                $url = $redirectPath;
                if (!empty($remainingParams)) {
                    $url .= '?' . http_build_query($remainingParams);
                }
                return new RedirectResponse($url, 301);
            }
        }

        $page = isset($args['page']) ? (int)$args['page'] : 1;
        if ($page < 1) {
            $page = 1;
        }

        $perPage = (int)($_ENV['ITEMS_PER_PAGE'] ?? 21);
        $offset = ($page - 1) * $perPage;

        $search = $params['search'] ?? '';
        $feedId = isset($params['feed']) ? (int)$params['feed'] : null;
        $categorySlug = $categoryFromPath ?? ($params['category'] ?? null);
        $tagSlug = $tagFromPath ?? ($params['tag'] ?? null);
        $latestPerFeed = !empty($params['latest']);

        if ($categoryFromPath !== null) {
            $paginationBaseUrl = '/category/' . $categoryFromPath . '/page/';
        } elseif ($tagFromPath !== null) {
            $paginationBaseUrl = '/tag/' . $tagFromPath . '/page/';
        } else {
            $paginationBaseUrl = '/page/';
        }


        $query = "SELECT fi.*, f.title as feed_title, f.site_url, f.language
                 FROM feed_items fi
                 JOIN feeds f ON fi.feed_id = f.id
                 WHERE fi.is_visible = 1";

        // Count only JOINs feeds when a feed-level filter requires it.
        $needsFeedJoinForCount = $latestPerFeed || !empty($categorySlug) || !empty($tagSlug);
        if ($needsFeedJoinForCount) {
            $countQuery = "SELECT COUNT(*) FROM feed_items fi
                          JOIN feeds f ON fi.feed_id = f.id
                          WHERE fi.is_visible = 1";
        } else {
            $countQuery = "SELECT COUNT(*) FROM feed_items fi WHERE fi.is_visible = 1";
        }

        if ($latestPerFeed) {
            $query .= " AND fi.id = f.last_feed_item_id";
            $countQuery .= " AND fi.id = f.last_feed_item_id";
        }

        $queryParams = [];
        $countQueryParams = [];

        if (!empty($search)) {
            $query .= " AND MATCH(fi.title, fi.content) AGAINST (%s IN BOOLEAN MODE)";
            $countQuery .= " AND MATCH(fi.title, fi.content) AGAINST (%s IN BOOLEAN MODE)";
            $queryParams[] = $search;
            $countQueryParams[] = $search;
        }

        if ($feedId) {
            $query .= " AND fi.feed_id = %i";
            $countQuery .= " AND fi.feed_id = %i";
            $queryParams[] = $feedId;
            $countQueryParams[] = $feedId;
        }

        if ($categorySlug) {
            $query .= " AND EXISTS (
                SELECT 1 FROM feed_categories fc
                JOIN categories c ON fc.category_id = c.id
                WHERE fc.feed_id = f.id AND c.slug = %s
            )";
            $countQuery .= " AND EXISTS (
                SELECT 1 FROM feed_categories fc
                JOIN categories c ON fc.category_id = c.id
                WHERE fc.feed_id = f.id AND c.slug = %s
            )";
            $queryParams[] = $categorySlug;
            $countQueryParams[] = $categorySlug;
        }

        if ($tagSlug) {
            $query .= " AND EXISTS (
                SELECT 1 FROM feed_tags ft
                JOIN tags t ON ft.tag_id = t.id
                WHERE ft.feed_id = f.id AND t.slug = %s
            )";
            $countQuery .= " AND EXISTS (
                SELECT 1 FROM feed_tags ft
                JOIN tags t ON ft.tag_id = t.id
                WHERE ft.feed_id = f.id AND t.slug = %s
            )";
            $queryParams[] = $tagSlug;
            $countQueryParams[] = $tagSlug;
        }

        $filterHash = $this->cache->hash([
            'search' => $search,
            'feed' => $feedId,
            'category' => $categorySlug,
            'tag' => $tagSlug,
            'page' => $page,
            'perPage' => $perPage,
            'latest' => $latestPerFeed ? 1 : 0
        ]);

        $hasFilters = !empty($search) || $feedId || $categorySlug || $tagSlug || $latestPerFeed;
        $countTtl = $hasFilters ? 60 : 300;

        $countCacheKey = $this->cache->key('items', 'count', $filterHash);
        $totalCount = $this->cache->remember($countCacheKey, $countTtl, ['items', 'feeds'], function() use ($countQuery, $countQueryParams) {
            return DB::queryFirstField($countQuery, ...$countQueryParams);
        });
        
        $totalPages = ceil($totalCount / $perPage);
        if ($page > $totalPages && $totalPages > 0) {
            return new RedirectResponse($paginationBaseUrl . $totalPages . $this->buildQueryString($params));
        }

        $query .= " ORDER BY fi.published_at DESC LIMIT %i, %i";
        $finalQueryParams = [...$queryParams, $offset, $perPage];

        $itemsCacheKey = $this->cache->key('items', 'home', $filterHash);
        $items = $this->cache->remember($itemsCacheKey, 60, ['items', 'feeds'], function() use ($query, $finalQueryParams) {
            return DB::query($query, ...$finalQueryParams) ?: [];
        });

        $feeds = CacheableQuery::query(
            'feeds', 'dropdown', ['feeds'], 300,
            "SELECT id, title FROM feeds ORDER BY title"
        );

        $categories = CacheableQuery::query(
            'categories', 'all', ['categories'], 300,
            "SELECT * FROM categories ORDER BY name"
        );

        $tags = CacheableQuery::query(
            'tags', 'all', ['tags'], 300,
            "SELECT * FROM tags ORDER BY name"
        );

        $html = $this->templates->render('home', [
            'items' => $items,
            'feeds' => $feeds,
            'categories' => $categories,
            'tags' => $tags,
            'search' => $search,
            'selectedFeed' => $feedId,
            'selectedCategory' => $categorySlug,
            'selectedTag' => $tagSlug,
            'latestPerFeed' => $latestPerFeed,
            'pagination' => [
                'current' => $page,
                'total' => $totalPages,
                'baseUrl' => $paginationBaseUrl
            ],
            'tagInPath' => $tagFromPath,
            'categoryInPath' => $categoryFromPath,
            'title' => 'Últimos Artigos',
            'thumbnailService' => $this->thumbnailService
        ]);

        return new HtmlResponse($html);
    }

    private function buildQueryString(array $params): string
    {
        if (empty($params)) {
            return '';
        }

        unset($params['page']);

        return '?' . http_build_query($params);
    }

    public function categories(ServerRequestInterface $request): ResponseInterface
    {
        $categories = CacheableQuery::query(
            'categories', 'all', ['categories'], 300,
            "SELECT * FROM categories ORDER BY name"
        );

        $html = $this->templates->render('categories-list', [
            'categories' => $categories,
            'title' => 'Categorias'
        ]);

        return new HtmlResponse($html);
    }

    public function tags(ServerRequestInterface $request): ResponseInterface
    {
        $tags = CacheableQuery::query(
            'tags', 'all', ['tags'], 300,
            "SELECT * FROM tags ORDER BY name"
        );

        $html = $this->templates->render('tags-list', [
            'tags' => $tags,
            'title' => 'Tópicos'
        ]);

        return new HtmlResponse($html);
    }

    public function random(ServerRequestInterface $request): ResponseInterface
    {
        $days = (int)($_ENV['RANDOM_POST_DAYS'] ?? 30);

        $poolKey = $this->cache->key('random', 'pool', (string)$days);
        $pool = $this->cache->remember($poolKey, 300, ['items'], function() use ($days) {
            $rows = DB::query(
                "SELECT fi.url
                 FROM feed_items fi
                 WHERE fi.is_visible = 1
                 AND fi.published_at >= DATE_SUB(NOW(), INTERVAL %i DAY)
                 ORDER BY fi.published_at DESC
                 LIMIT 500",
                $days
            ) ?: [];
            return array_column($rows, 'url');
        });

        if (!empty($pool)) {
            $url = $pool[array_rand($pool)];
            if (!empty($url) && UrlValidator::validateRedirectUrl($url)) {
                return new RedirectResponse($url);
            }
        }

        return new RedirectResponse('/');
    }

    public function shuffle(ServerRequestInterface $request): ResponseInterface
    {
        $params = $request->getQueryParams();
        $isAjax = isset($params['ajax']) && $params['ajax'] === '1';

        $poolKey = $this->cache->key('shuffle', 'pool');
        $pool = $this->cache->remember($poolKey, 300, ['feeds'], function() {
            $rows = DB::query(
                "SELECT f.site_url FROM feeds f WHERE f.status = 'online' AND f.shuffle = 1"
            ) ?: [];
            return array_column($rows, 'site_url');
        });

        $url = !empty($pool) ? ($pool[array_rand($pool)] ?? '') : '';

        if (!empty($url) && !UrlValidator::validateRedirectUrl($url)) {
            $url = '';
        }

        if ($isAjax) {
            $response = new \Laminas\Diactoros\Response\JsonResponse([
                'url' => $url
            ]);

            return $response
                ->withHeader('Access-Control-Allow-Origin', '*')
                ->withHeader('Access-Control-Allow-Methods', 'GET, OPTIONS')
                ->withHeader('Access-Control-Allow-Headers', 'Content-Type')
                ->withHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
                ->withHeader('Pragma', 'no-cache')
                ->withHeader('Expires', '0');
        }
        
        $html = $this->templates->render('shuffle', [
            'title' => 'Shuffle',
            'initialUrl' => $url
        ]);

        return new HtmlResponse($html);
    }
}
