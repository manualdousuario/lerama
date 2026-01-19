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
        $page = isset($args['page']) ? (int)$args['page'] : 1;
        if ($page < 1) {
            $page = 1;
        }

        $perPage = (int)($_ENV['ITEMS_PER_PAGE'] ?? 21);
        $offset = ($page - 1) * $perPage;

        $params = $request->getQueryParams();
        $search = $params['search'] ?? '';
        $feedId = isset($params['feed']) ? (int)$params['feed'] : null;
        $categorySlug = $params['category'] ?? null;
        $tagSlug = $params['tag'] ?? null;

        $query = "SELECT fi.*, f.title as feed_title, f.site_url, f.language
                 FROM feed_items fi
                 JOIN feeds f ON fi.feed_id = f.id
                 WHERE fi.is_visible = 1";
        $countQuery = "SELECT COUNT(*) FROM feed_items fi
                      JOIN feeds f ON fi.feed_id = f.id
                      WHERE fi.is_visible = 1";
        $queryParams = [];

        if (!empty($search)) {
            $query .= " AND MATCH(fi.title, fi.content) AGAINST (%s IN BOOLEAN MODE)";
            $countQuery .= " AND MATCH(fi.title, fi.content) AGAINST (%s IN BOOLEAN MODE)";
            $queryParams[] = $search;
        }

        if ($feedId) {
            $query .= " AND fi.feed_id = %i";
            $countQuery .= " AND fi.feed_id = %i";
            $queryParams[] = $feedId;
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
        }

        $filterHash = $this->cache->hash([
            'search' => $search,
            'feed' => $feedId,
            'category' => $categorySlug,
            'tag' => $tagSlug,
            'page' => $page,
            'perPage' => $perPage
        ]);

        $countCacheKey = $this->cache->key('items', 'count', $filterHash);
        $totalCount = $this->cache->remember($countCacheKey, 60, ['items', 'feeds'], function() use ($countQuery, $queryParams) {
            return DB::queryFirstField($countQuery, ...$queryParams);
        });
        
        $totalPages = ceil($totalCount / $perPage);
        if ($page > $totalPages && $totalPages > 0) {
            return new RedirectResponse('/page/' . $totalPages . $this->buildQueryString($params));
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
            'pagination' => [
                'current' => $page,
                'total' => $totalPages,
                'baseUrl' => '/page/'
            ],
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
        
        $item = DB::queryFirstRow(
            "SELECT fi.url
             FROM feed_items fi
             JOIN feeds f ON fi.feed_id = f.id
             WHERE fi.is_visible = 1
             AND fi.published_at >= DATE_SUB(NOW(), INTERVAL %i DAY)
             ORDER BY RAND()
             LIMIT 1",
            $days
        );

        if ($item && !empty($item['url'])) {
            return new RedirectResponse($item['url']);
        }

        return new RedirectResponse('/');
    }
}
