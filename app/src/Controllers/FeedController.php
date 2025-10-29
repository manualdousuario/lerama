<?php

declare(strict_types=1);

namespace Lerama\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Laminas\Diactoros\Response\HtmlResponse;
use Laminas\Diactoros\Response\JsonResponse;
use Laminas\Diactoros\Response\XmlResponse;
use League\Plates\Engine;
use DB;

class FeedController
{
    private Engine $templates;

    public function __construct()
    {
        $this->templates = new Engine(__DIR__ . '/../../templates');
    }

    public function index(ServerRequestInterface $request, array $args = []): ResponseInterface
    {
        $page = isset($args['page']) ? (int)$args['page'] : 1;
        if ($page < 1) {
            $page = 1;
        }

        $perPage = 50;
        $offset = ($page - 1) * $perPage;

        $params = $request->getQueryParams();
        $categorySlug = $params['category'] ?? null;
        $tagSlug = $params['tag'] ?? null;

        $query = "SELECT f.*,
                  (SELECT COUNT(*) FROM feed_items WHERE feed_id = f.id) as item_count,
                  (SELECT MAX(published_at) FROM feed_items WHERE feed_id = f.id) as latest_item_date
            FROM feeds f
            WHERE 1=1";
        $countQuery = "SELECT COUNT(*) FROM feeds f WHERE 1=1";
        $queryParams = [];

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

        $totalCount = DB::queryFirstField($countQuery, ...$queryParams);
        $totalPages = ceil($totalCount / $perPage);

        $query .= " ORDER BY f.title LIMIT %i, %i";
        $finalQueryParams = [...$queryParams, $offset, $perPage];

        $feeds = DB::query($query, ...$finalQueryParams);

        // Get categories and tags for each feed
        foreach ($feeds as &$feed) {
            $feed['categories'] = DB::query("
                SELECT c.* FROM categories c
                JOIN feed_categories fc ON c.id = fc.category_id
                WHERE fc.feed_id = %i
                ORDER BY c.name
            ", $feed['id']);

            $feed['tags'] = DB::query("
                SELECT t.* FROM tags t
                JOIN feed_tags ft ON t.id = ft.tag_id
                WHERE ft.feed_id = %i
                ORDER BY t.name
            ", $feed['id']);
        }

        $categories = DB::query("SELECT * FROM categories ORDER BY name");
        $tags = DB::query("SELECT * FROM tags ORDER BY name");

        $html = $this->templates->render('feeds', [
            'feeds' => $feeds,
            'categories' => $categories,
            'tags' => $tags,
            'selectedCategory' => $categorySlug,
            'selectedTag' => $tagSlug,
            'pagination' => [
                'current' => $page,
                'total' => $totalPages,
                'baseUrl' => '/feeds/page/'
            ],
            'title' => 'Feeds'
        ]);

        return new HtmlResponse($html);
    }

    public function json(ServerRequestInterface $request): ResponseInterface
    {
        $params = $request->getQueryParams();
        $page = isset($params['page']) ? max(1, (int)$params['page']) : 1;
        $perPage = isset($params['per_page']) ? min(100, max(1, (int)$params['per_page'])) : 20;
        $offset = ($page - 1) * $perPage;
        $categorySlug = $params['category'] ?? null;
        $tagSlug = $params['tag'] ?? null;

        $whereConditions = ["fi.is_visible = 1"];
        $queryParams = [];

        if ($categorySlug) {
            $whereConditions[] = "EXISTS (
                SELECT 1 FROM feed_categories fc
                JOIN categories c ON fc.category_id = c.id
                WHERE fc.feed_id = f.id AND c.slug = %s
            )";
            $queryParams[] = $categorySlug;
        }

        if ($tagSlug) {
            $whereConditions[] = "EXISTS (
                SELECT 1 FROM feed_tags ft
                JOIN tags t ON ft.tag_id = t.id
                WHERE ft.feed_id = f.id AND t.slug = %s
            )";
            $queryParams[] = $tagSlug;
        }

        $whereClause = implode(' AND ', $whereConditions);

        $totalCount = DB::queryFirstField(
            "SELECT COUNT(*) FROM feed_items fi JOIN feeds f ON fi.feed_id = f.id WHERE " . $whereClause,
            ...$queryParams
        );
        $totalPages = ceil($totalCount / $perPage);

        $items = DB::query("
            SELECT fi.id, fi.title, fi.author, fi.content, fi.url, fi.image_url, fi.published_at,
                   f.title as feed_title, f.site_url as feed_site_url
            FROM feed_items fi
            JOIN feeds f ON fi.feed_id = f.id
            WHERE " . $whereClause . "
            ORDER BY fi.published_at DESC
            LIMIT %i, %i
        ", ...array_merge($queryParams, [$offset, $perPage]));

        $formattedItems = [];
        foreach ($items as $item) {
            // Se o campo author estiver vazio, preencher com o nome do source
            $author = !empty($item['author']) ? $item['author'] : $item['feed_title'];
            
            $formattedItems[] = [
                'id' => $item['id'],
                'title' => $item['title'],
                'author' => $author,
                'content' => $item['content'],
                'url' => $item['url'],
                'image_url' => $item['image_url'],
                'published_at' => $item['published_at'],
                'feed' => [
                    'title' => $item['feed_title'],
                    'site_url' => $item['feed_site_url']
                ]
            ];
        }

        $response = [
            'items' => $formattedItems,
            'pagination' => [
                'total_items' => $totalCount,
                'total_pages' => $totalPages,
                'current_page' => $page,
                'per_page' => $perPage
            ]
        ];

        return new JsonResponse($response);
    }

    public function rss(ServerRequestInterface $request): ResponseInterface
    {
        $params = $request->getQueryParams();
        $page = isset($params['page']) ? max(1, (int)$params['page']) : 1;
        $perPage = isset($params['per_page']) ? min(100, max(1, (int)$params['per_page'])) : 20;
        $offset = ($page - 1) * $perPage;
        $categorySlug = $params['category'] ?? null;
        $tagSlug = $params['tag'] ?? null;

        $whereConditions = ["fi.is_visible = 1"];
        $queryParams = [];

        if ($categorySlug) {
            $whereConditions[] = "EXISTS (
                SELECT 1 FROM feed_categories fc
                JOIN categories c ON fc.category_id = c.id
                WHERE fc.feed_id = f.id AND c.slug = %s
            )";
            $queryParams[] = $categorySlug;
        }

        if ($tagSlug) {
            $whereConditions[] = "EXISTS (
                SELECT 1 FROM feed_tags ft
                JOIN tags t ON ft.tag_id = t.id
                WHERE ft.feed_id = f.id AND t.slug = %s
            )";
            $queryParams[] = $tagSlug;
        }

        $whereClause = implode(' AND ', $whereConditions);

        $items = DB::query("
            SELECT fi.id, fi.title, fi.author, fi.content, fi.url, fi.image_url, fi.published_at,
                   f.title as feed_title, f.site_url as feed_site_url
            FROM feed_items fi
            JOIN feeds f ON fi.feed_id = f.id
            WHERE " . $whereClause . "
            ORDER BY fi.published_at DESC
            LIMIT %i, %i
        ", ...array_merge($queryParams, [$offset, $perPage]));

        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><rss version="2.0"></rss>');

        $channel = $xml->addChild('channel');
        $channel->addChild('title', $_ENV['APP_NAME'] . ' Feed');
        $channel->addChild('link', $_ENV['APP_URL']);
        $channel->addChild('description', 'Feed agregado de múltiplas fontes');
        $channel->addChild('language', 'pt-br');
        $channel->addChild('pubDate', date('r'));

        foreach ($items as $item) {
            $xmlItem = $channel->addChild('item');
            $xmlItem->addChild('title', htmlspecialchars($item['title']));

            // Se o campo author estiver vazio, preencher com o nome do source
            $author = !empty($item['author']) ? $item['author'] : $item['feed_title'];
            $xmlItem->addChild('author', htmlspecialchars($author));

            $xmlItem->addChild('link', htmlspecialchars($item['url']));
            $xmlItem->addChild('guid', htmlspecialchars($item['url']));
            $xmlItem->addChild('pubDate', date('r', strtotime($item['published_at'])));

            if (!empty($item['image_url'])) {
                $enclosure = $xmlItem->addChild('enclosure');
                $enclosure->addAttribute('url', htmlspecialchars($item['image_url']));
                $enclosure->addAttribute('type', 'image/jpeg');
            }

            $description = $xmlItem->addChild('description');
            $node = dom_import_simplexml($description);
            $owner = $node->ownerDocument;
            $node->appendChild($owner->createCDATASection($item['content']));

            $source = $xmlItem->addChild('source', htmlspecialchars($item['feed_title']));
            $source->addAttribute('url', htmlspecialchars($item['feed_site_url']));
        }

        $xmlString = $xml->asXML();

        return new XmlResponse($xmlString);
    }
}
