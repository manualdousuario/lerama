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

class HomeController
{
    private Engine $templates;
    private ThumbnailService $thumbnailService;

    public function __construct()
    {
        $this->templates = new Engine(__DIR__ . '/../../templates');
        $this->thumbnailService = new ThumbnailService();
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

        $totalCount = DB::queryFirstField($countQuery, ...$queryParams);
        $totalPages = ceil($totalCount / $perPage);
        if ($page > $totalPages && $totalPages > 0) {
            return new RedirectResponse('/page/' . $totalPages . $this->buildQueryString($params));
        }

        $query .= " ORDER BY fi.published_at DESC LIMIT %i, %i";
        $finalQueryParams = [...$queryParams, $offset, $perPage];

        $items = DB::query($query, ...$finalQueryParams);

        $feeds = DB::query("SELECT id, title FROM feeds ORDER BY title");

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
