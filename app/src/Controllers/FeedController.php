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
use Lerama\Services\CacheService;

class FeedController
{
    private Engine $templates;
    private CacheService $cacheService;

    public function __construct()
    {
        $this->templates = new Engine(__DIR__ . '/../../templates');
        $this->cacheService = new CacheService();
    }

    public function index(ServerRequestInterface $request): ResponseInterface
    {
        $cacheKey = 'feeds_index';
        $feeds = $this->cacheService->get($cacheKey);
        
        if ($feeds === null) {
            $feeds = DB::query("
                SELECT f.*,
                      (SELECT COUNT(*) FROM feed_items WHERE feed_id = f.id) as item_count,
                      (SELECT MAX(published_at) FROM feed_items WHERE feed_id = f.id) as latest_item_date
                FROM feeds f
                ORDER BY f.title
            ");
            
            $this->cacheService->set($cacheKey, $feeds);
        }

        $html = $this->templates->render('feeds', [
            'feeds' => $feeds,
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

        $countCacheKey = $this->cacheService->generateKey('feed_json_count');
        $itemsCacheKey = $this->cacheService->generateKey('feed_json_items', ['page' => $page, 'perPage' => $perPage]);
        
        $totalCount = $this->cacheService->get($countCacheKey);
        if ($totalCount === null) {
            $totalCount = DB::queryFirstField("SELECT COUNT(*) FROM feed_items WHERE is_visible = 1");
            $this->cacheService->set($countCacheKey, $totalCount);
        }
        
        $totalPages = ceil($totalCount / $perPage);
        
        $items = $this->cacheService->get($itemsCacheKey);
        if ($items === null) {
            $items = DB::query("
                SELECT fi.id, fi.title, fi.author, fi.content, fi.url, fi.image_url, fi.published_at,
                       f.title as feed_title, f.site_url as feed_site_url
                FROM feed_items fi
                JOIN feeds f ON fi.feed_id = f.id
                WHERE fi.is_visible = 1
                ORDER BY fi.published_at DESC
                LIMIT %i, %i
            ", $offset, $perPage);
            
            $this->cacheService->set($itemsCacheKey, $items);
        }

        $formattedItems = [];
        foreach ($items as $item) {
            $formattedItems[] = [
                'id' => $item['id'],
                'title' => $item['title'],
                'author' => $item['author'],
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

        $itemsCacheKey = $this->cacheService->generateKey('feed_rss_items', ['page' => $page, 'perPage' => $perPage]);
        
        $items = $this->cacheService->get($itemsCacheKey);
        if ($items === null) {
            $items = DB::query("
                SELECT fi.id, fi.title, fi.author, fi.content, fi.url, fi.image_url, fi.published_at,
                       f.title as feed_title, f.site_url as feed_site_url
                FROM feed_items fi
                JOIN feeds f ON fi.feed_id = f.id
                WHERE fi.is_visible = 1
                ORDER BY fi.published_at DESC
                LIMIT %i, %i
            ", $offset, $perPage);
            
            $this->cacheService->set($itemsCacheKey, $items);
        }

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

            if (!empty($item['author'])) {
                $xmlItem->addChild('author', htmlspecialchars($item['author']));
            }

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
