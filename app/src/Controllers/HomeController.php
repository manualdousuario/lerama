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

class HomeController
{
    private Engine $templates;
    private ThumbnailService $thumbnailService;
    private CacheService $cacheService;

    public function __construct()
    {
        $this->templates = new Engine(__DIR__ . '/../../templates');
        $this->thumbnailService = new ThumbnailService();
        $this->cacheService = new CacheService();
    }

    public function index(ServerRequestInterface $request, array $args = []): ResponseInterface
    {
        $page = isset($args['page']) ? (int)$args['page'] : 1;
        if ($page < 1) {
            $page = 1;
        }

        $perPage = 20;
        $offset = ($page - 1) * $perPage;

        $params = $request->getQueryParams();
        $search = $params['search'] ?? '';
        $feedId = isset($params['feed']) ? (int)$params['feed'] : null;

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

        $countCacheKey = $this->cacheService->generateKey('home_count', $queryParams);
        $itemsCacheKey = $this->cacheService->generateKey('home_items', [...$queryParams, 'page' => $page, 'perPage' => $perPage]);
        $feedsCacheKey = 'home_feeds_list';

        $totalCount = $this->cacheService->get($countCacheKey);
        if ($totalCount === null) {
            $totalCount = DB::queryFirstField($countQuery, ...$queryParams);
            $this->cacheService->set($countCacheKey, $totalCount);
        }

        $totalPages = ceil($totalCount / $perPage);
        if ($page > $totalPages && $totalPages > 0) {
            return new RedirectResponse('/page/' . $totalPages . $this->buildQueryString($params));
        }

        $items = $this->cacheService->get($itemsCacheKey);
        if ($items === null) {
            $query .= " ORDER BY fi.published_at DESC LIMIT %i, %i";
            $finalQueryParams = [...$queryParams, $offset, $perPage];
            $items = DB::query($query, ...$finalQueryParams);
            $this->cacheService->set($itemsCacheKey, $items);
        }

        $feeds = $this->cacheService->get($feedsCacheKey);
        if ($feeds === null) {
            $feeds = DB::query("SELECT id, title FROM feeds ORDER BY title");
            $this->cacheService->set($feedsCacheKey, $feeds);
        }

        $html = $this->templates->render('home', [
            'items' => $items,
            'feeds' => $feeds,
            'search' => $search,
            'selectedFeed' => $feedId,
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
}
