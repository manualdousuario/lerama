<?php
declare(strict_types=1);

namespace Lerama\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Laminas\Diactoros\Response\HtmlResponse;
use Laminas\Diactoros\Response\RedirectResponse;
use League\Plates\Engine;
use DB;

class HomeController
{
    private Engine $templates;

    public function __construct()
    {
        $this->templates = new Engine(__DIR__ . '/../../templates');
    }

    public function index(ServerRequestInterface $request, array $args = []): ResponseInterface
    {
        // Get the page number from the route or default to 1
        $page = isset($args['page']) ? (int)$args['page'] : 1;
        if ($page < 1) {
            $page = 1;
        }
        
        // Items per page
        $perPage = 20;
        $offset = ($page - 1) * $perPage;
        
        // Get query parameters for search and filtering
        $params = $request->getQueryParams();
        $search = $params['search'] ?? '';
        $feedId = isset($params['feed']) ? (int)$params['feed'] : null;
        
        // Build the query
        $query = "SELECT fi.*, f.title as feed_title, f.site_url, f.language
                 FROM feed_items fi
                 JOIN feeds f ON fi.feed_id = f.id
                 WHERE fi.is_visible = 1";
        $countQuery = "SELECT COUNT(*) FROM feed_items fi
                      JOIN feeds f ON fi.feed_id = f.id
                      WHERE fi.is_visible = 1";
        $queryParams = [];
        
        // Add search condition if provided
        if (!empty($search)) {
            $query .= " AND MATCH(fi.title, fi.content) AGAINST (%s IN BOOLEAN MODE)";
            $countQuery .= " AND MATCH(fi.title, fi.content) AGAINST (%s IN BOOLEAN MODE)";
            $queryParams[] = $search;
        }
        
        // Add feed filter if provided
        if ($feedId) {
            $query .= " AND fi.feed_id = %i";
            $countQuery .= " AND fi.feed_id = %i";
            $queryParams[] = $feedId;
        }
        
        // Get total count for pagination
        $totalCount = DB::queryFirstField($countQuery, ...$queryParams);
        $totalPages = ceil($totalCount / $perPage);
        // Ensure page is within valid range
        if ($page > $totalPages && $totalPages > 0) {
            return new RedirectResponse('/page/' . $totalPages . $this->buildQueryString($params));
        }
        
        
        // Finalize query with order and limit
        $query .= " ORDER BY fi.published_at DESC LIMIT %i, %i";
        $finalQueryParams = [...$queryParams, $offset, $perPage];
        
        // Execute the query
        $items = DB::query($query, ...$finalQueryParams);
        
        // Get all feeds for the filter dropdown
        $feeds = DB::query("SELECT id, title FROM feeds ORDER BY title");
        
        // Render the template
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
            'title' => 'Latest Feed Items'
        ]);
        
        return new HtmlResponse($html);
    }
    
    /**
     * Build a query string from parameters
     *
     * @param array $params Query parameters
     * @return string The query string
     */
    private function buildQueryString(array $params): string
    {
        if (empty($params)) {
            return '';
        }
        
        // Remove page parameter if it exists
        unset($params['page']);
        
        // Build query string
        return '?' . http_build_query($params);
    }
}