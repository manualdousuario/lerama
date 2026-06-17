<?php

declare(strict_types=1);

namespace Lerama\Commands;

use League\CLImate\CLImate;
use Laminas\Feed\Reader\Reader;
use DB;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Lerama\Services\ProxyService;
use Lerama\Services\EmailService;
use Lerama\Services\BulkInserter;
use Lerama\Services\CacheInvalidator;
use Lerama\Services\CacheWarmer;
use Lerama\Config\HttpClientConfig;

class FeedProcessor
{
    private CLImate $climate;
    private \GuzzleHttp\Client $httpClient;
    private ProxyService $proxyService;
    private EmailService $emailService;
    private array $defaultClientConfig;
    private bool $subscriberTextShow;
    private int $maxFeedsPerRun;
    private int $errorThreshold;
    private bool $usingProxy = false;
    private array $itemBuffer = [];
    private int $itemsInBuffer = 0;

    private const FETCH_INTERVAL_SUCCESS = 86400;
    private const FETCH_INTERVAL_NOT_MODIFIED = 86400;
    private const FETCH_INTERVAL_ERROR = 3600;

    public function __construct(CLImate $climate)
    {
        $this->climate = $climate;
        $this->proxyService = new ProxyService();
        $this->emailService = new EmailService();

        $this->defaultClientConfig = HttpClientConfig::getDefaultConfig();

        $this->httpClient = new \GuzzleHttp\Client($this->defaultClientConfig);

        $this->subscriberTextShow = filter_var(
            $_ENV['SUBSCRIBER_SHOW_POST'] ?? 'false',
            FILTER_VALIDATE_BOOLEAN
        );

        $this->maxFeedsPerRun = (int)($_ENV['FEED_MAX_PER_RUN'] ?? 3);
        $this->errorThreshold = (int)($_ENV['FEED_ITEM_ERROR_THRESHOLD'] ?? 5);
    }

    public function process(?int $feedId = null): void
    {
        if ($feedId) {
            $this->climate->info("Processing feed ID: {$feedId}");
            $feeds = DB::query("SELECT * FROM feeds WHERE id = %i AND (status = 'online' OR status = 'paused')", $feedId);
        } else {
            $this->climate->info("Processing online feeds (max: {$this->maxFeedsPerRun}, scheduled via next_fetch_at)");

            $feeds = DB::query(
                "SELECT * FROM feeds
                WHERE status = 'online'
                AND next_fetch_at <= UNIX_TIMESTAMP()
                ORDER BY next_fetch_at ASC
                LIMIT %i",
                $this->maxFeedsPerRun
            );
        }

        if (empty($feeds)) {
            $this->climate->warning("No feeds due right now (all online feeds scheduled for later)");
            return;
        }

        $totalFeeds = count($feeds);
        $this->climate->info("Found {$totalFeeds} feed(s) due for processing:");
        
        foreach ($feeds as $index => $feed) {
            $lastChecked = $feed['last_checked'] ?? 'never';
            $this->climate->whisper("  " . ($index + 1) . ". [{$feed['id']}] {$feed['title']} - last checked: {$lastChecked}");
        }

        foreach ($feeds as $feed) {
            $this->processSingleFeed($feed);
            unset($feed);
        }

        $this->climate->info("Invalidating affected cache tags...");
        $deleted = CacheInvalidator::invalidate(['items', 'feeds']);
        $this->climate->green("✓ Invalidated {$deleted} cache tag reference(s)");

        $this->climate->info("Warming important caches...");
        $summary = CacheWarmer::warmImportant();
        $this->climate->green("✓ Warmed categories ({$summary['categories']}), tags ({$summary['tags']}), feeds ({$summary['feeds_dropdown']}), home items ({$summary['home']['items_count']})");

        unset($feeds);
        gc_collect_cycles();
    }

    private function bufferItem(array $item): void
    {
        $this->itemBuffer[] = $item;
        $this->itemsInBuffer++;
    }

    private function flushItems(int $feedId): int
    {
        if (empty($this->itemBuffer)) {
            return 0;
        }

        $count = BulkInserter::insert('feed_items', $this->itemBuffer, [
            'ignore' => true,
            'batchSize' => 100,
        ]);

        $this->itemBuffer = [];
        $this->itemsInBuffer = 0;

        return $count;
    }

    private function findItemIdByGuid(string $guid): ?int
    {
        $row = DB::queryFirstRow(
            "SELECT id FROM feed_items WHERE guid = %s ORDER BY id DESC LIMIT 1",
            $guid
        );
        return $row ? (int)$row['id'] : null;
    }

    private function processSingleFeed(array $feed): void
    {
        $lastChecked = $feed['last_checked'] ?? 'never';
        $this->climate->out("Processing: {$feed['title']} (last checked: {$lastChecked})");
        $this->climate->whisper("Feed URL: {$feed['feed_url']}");

        $proxyOnly = ($feed['proxy_only'] ?? 0) == 1;
        $useProxy = $proxyOnly || ($feed['retry_proxy'] ?? 0) == 1;

        if ($useProxy) {
            $this->setupProxyClient();
            if ($proxyOnly) {
                $this->climate->info("Using proxy (proxy_only) for feed: {$feed['title']}");
            } else {
                $this->climate->info("Using proxy (retry) for feed: {$feed['title']}");
            }
        } else {
            $this->httpClient = new \GuzzleHttp\Client($this->defaultClientConfig);
            $this->usingProxy = false;
        }

        DB::update('feeds', [
            'last_checked' => DB::sqleval("NOW()"),
            'next_fetch_at' => DB::sqleval("UNIX_TIMESTAMP() + " . self::FETCH_INTERVAL_ERROR)
        ], 'id=%i', $feed['id']);

        try {
            $this->processFeed($feed);


            $updateData = [
                'retry_count' => 0,
                'paused_at' => null,
                'next_fetch_at' => DB::sqleval("UNIX_TIMESTAMP() + " . self::FETCH_INTERVAL_SUCCESS)
            ];

            if (!$proxyOnly) {
                $updateData['retry_proxy'] = 0;
            }

            $updateData['status'] = 'online';

            DB::update('feeds', $updateData, 'id=%i', $feed['id']);

            $this->climate->green("✓ Feed processed successfully: {$feed['title']}");
        } catch (\Exception $e) {
            $this->climate->red("✗ Error processing feed {$feed['title']}: {$e->getMessage()}");

            $retryCount = ($feed['retry_count'] ?? 0) + 1;
            $this->climate->info("Attempt {$retryCount} for feed: {$feed['title']} (threshold: {$this->errorThreshold})");

            $isAutoManaged = in_array($feed['status'] ?? null, ['online', 'paused']);

            if ($retryCount >= $this->errorThreshold) {
                $this->climate->yellow("Feed {$feed['title']} marked as paused after {$retryCount} attempts (threshold: {$this->errorThreshold})");
                $errorData = [
                    'retry_count' => $retryCount,
                    'paused_at' => DB::sqleval("NOW()")
                ];
                if ($isAutoManaged) {
                    $errorData['status'] = 'paused';
                }
                DB::update('feeds', $errorData, 'id=%i', $feed['id']);
            } else if ($retryCount > 3 && !$proxyOnly) {
                $this->climate->yellow("Feed {$feed['title']} will use proxy in next attempts");
                $errorData = [
                    'retry_count' => $retryCount,
                    'retry_proxy' => 1
                ];
                if ($isAutoManaged) {
                    $errorData['status'] = 'online';
                }
                DB::update('feeds', $errorData, 'id=%i', $feed['id']);
            } else {
                $errorData = ['retry_count' => $retryCount];
                if ($isAutoManaged) {
                    $errorData['status'] = 'online';
                }
                DB::update('feeds', $errorData, 'id=%i', $feed['id']);
            }
        }
    }

    public function checkPausedFeeds(): void
    {
        $this->climate->info("Checking paused feeds...");

        $pausedFeeds = DB::query("SELECT * FROM feeds WHERE status = 'paused'");

        if (empty($pausedFeeds)) {
            $this->climate->info("No paused feeds found");
            return;
        }

        foreach ($pausedFeeds as $feed) {
            $pausedAt = strtotime($feed['paused_at']);
            $now = time();
            $hoursSincePaused = ($now - $pausedAt) / 3600;
            $proxyOnly = ($feed['proxy_only'] ?? 0) == 1;

            $this->climate->info("Feed {$feed['title']} has been paused for " . round($hoursSincePaused, 1) . " hours");

            if ($hoursSincePaused >= 72) {
                $this->climate->info("Trying to process feed {$feed['title']} after 72 hours paused");

                try {
                    // Use proxy if proxy_only is set, otherwise use direct connection
                    if ($proxyOnly) {
                        $this->setupProxyClient();
                        $this->climate->info("Using proxy (proxy_only) for feed: {$feed['title']}");
                    } else {
                        $this->httpClient = new \GuzzleHttp\Client($this->defaultClientConfig);
                        $this->usingProxy = false;
                    }

                    $this->processFeed($feed);

                    $feedTitle = $this->extractFeedTitle($feed);
                    if ($feedTitle && $feedTitle !== $feed['title']) {
                        $this->climate->info("Updating feed title from '{$feed['title']}' to '{$feedTitle}'");
                    }

                    $this->climate->green("✓ Feed {$feed['title']} is working again after 72 hours paused");
                    
                    $updateData = [
                        'title' => $feedTitle ?: $feed['title'],
                        'last_checked' => DB::sqleval("NOW()"),
                        'status' => 'online',
                        'retry_count' => 0,
                        'paused_at' => null
                    ];
                    
                    if (!$proxyOnly) {
                        $updateData['retry_proxy'] = 0;
                    }
                    
                    DB::update('feeds', $updateData, 'id=%i', $feed['id']);
                } catch (\Exception $e) {
                    $this->climate->red("✗ Feed {$feed['title']} remains inaccessible after 72 hours paused");
                    DB::update('feeds', [
                        'last_checked' => DB::sqleval("NOW()"),
                        'status' => 'offline'
                    ], 'id=%i', $feed['id']);

                    $this->emailService->sendFeedOfflineNotification($feed);
                    $this->climate->info("Notification sent to administrator about offline feed: {$feed['title']}");
                }
            } else if ($hoursSincePaused >= 24) {
                $this->climate->info("Trying to process feed {$feed['title']} after 24 hours paused");

                try {
                    $this->setupProxyClient();
                    if ($proxyOnly) {
                        $this->climate->info("Using proxy (proxy_only) for feed: {$feed['title']}");
                    }

                    $this->processFeed($feed);

                    $feedTitle = $this->extractFeedTitle($feed);
                    if ($feedTitle && $feedTitle !== $feed['title']) {
                        $this->climate->info("Updating feed title from '{$feed['title']}' to '{$feedTitle}'");
                    }

                    $this->climate->green("✓ Feed {$feed['title']} is working again after 24 hours paused");
                    
                    $updateData = [
                        'title' => $feedTitle ?: $feed['title'],
                        'last_checked' => DB::sqleval("NOW()"),
                        'status' => 'online',
                        'retry_count' => 0,
                        'paused_at' => null
                    ];
                    
                    if (!$proxyOnly) {
                        $updateData['retry_proxy'] = 0;
                    }
                    
                    DB::update('feeds', $updateData, 'id=%i', $feed['id']);
                } catch (\Exception $e) {
                    $this->climate->yellow("! Feed {$feed['title']} remains inaccessible after 24 hours paused");
                    DB::update('feeds', [
                        'last_checked' => DB::sqleval("NOW()")
                    ], 'id=%i', $feed['id']);
                }
            }
            
            unset($feed);
        }
        
        unset($pausedFeeds);
        gc_collect_cycles();

        $this->climate->info("Invalidating feed cache after status check...");
        $deleted = CacheInvalidator::invalidateFeeds();
        $this->climate->green("✓ Invalidated {$deleted} cache tag reference(s)");

        $this->climate->info("Warming important caches...");
        $summary = CacheWarmer::warmImportant();
        $this->climate->green("✓ Warmed categories ({$summary['categories']}), tags ({$summary['tags']}), feeds ({$summary['feeds_dropdown']}), home items ({$summary['home']['items_count']})");
    }

    private function setupProxyClient(): bool
    {
        $proxy = $this->proxyService->getRandomProxy();

        if (!$proxy) {
            $this->climate->warning("No proxy available, using direct connection");
            $this->httpClient = new \GuzzleHttp\Client($this->defaultClientConfig);
            $this->usingProxy = false;
            return false;
        }

        $config = $this->defaultClientConfig;

        $proxyUrl = '';
        if ($proxy['username'] && $proxy['password']) {
            $proxyUrl = "http://{$proxy['username']}:{$proxy['password']}@{$proxy['host']}:{$proxy['port']}";
        } else {
            $proxyUrl = "http://{$proxy['host']}:{$proxy['port']}";
        }

        $config['proxy'] = $proxyUrl;
        $this->httpClient = new \GuzzleHttp\Client($config);
        $this->usingProxy = true;

        $this->climate->info("Using proxy: {$proxy['host']}:{$proxy['port']}");
        return true;
    }

    private function isNotModified(array $feed): bool
    {
        $etag = $feed['etag'] ?? null;
        $lastModified = $feed['last_modified'] ?? null;

        $headers = [];
        if ($etag) {
            $headers['If-None-Match'] = $etag;
        }
        if ($lastModified) {
            $headers['If-Modified-Since'] = $lastModified;
        }

        try {
            $response = $this->httpClient->head($feed['feed_url'], ['headers' => $headers]);
            $status = $response->getStatusCode();

            if ($status === 304) {
                return true;
            }

            if ($status >= 200 && $status < 300) {
                $newEtag = $response->getHeaderLine('ETag') ?: null;
                $newLastModified = $response->getHeaderLine('Last-Modified') ?: null;

                if (($newEtag && $newEtag !== $etag) || ($newLastModified && $newLastModified !== $lastModified)) {
                    DB::update('feeds', [
                        'etag' => $newEtag,
                        'last_modified' => $newLastModified
                    ], 'id=%i', $feed['id']);
                }
            }

            return false;
        } catch (\Exception $e) {
            $this->climate->whisper("Conditional request failed for {$feed['title']}: {$e->getMessage()}");
            return false;
        }
    }

    private function processFeed(array $feed): void
    {
        $feedType = $feed['feed_type'];
        $feedUrl = $feed['feed_url'];

        if ($this->isNotModified($feed)) {
            $this->climate->out("Feed not modified (304): {$feed['title']}");
            DB::update('feeds', [
                'next_fetch_at' => DB::sqleval("UNIX_TIMESTAMP() + " . self::FETCH_INTERVAL_NOT_MODIFIED)
            ], 'id=%i', $feed['id']);
            return;
        }

        switch ($feedType) {
            case 'rss1':
            case 'rss2':
            case 'atom':
            case 'rdf':
                $this->processRssFeed($feed);
                break;
            case 'csv':
                $this->processCsvFeed($feed);
                break;
            case 'json':
                $this->processJsonFeed($feed);
                break;
            case 'xml':
                $this->processXmlFeed($feed);
                break;
            default:
                throw new \Exception("Unsupported feed type: {$feedType}");
        }
    }

    private function processRssFeed(array $feed): void
    {
        $reader = null;

        try {
            $feedContent = $this->fetchFeedContent($feed['feed_url']);
            $reader = Reader::importString($feedContent);

            $count = 0;
            $updated = false;
            $lastGuid = null;
            $processedItems = 0;
            $maxItemsToProcess = 100;

            foreach ($reader as $entry) {
                $guid = $entry->getId();

                if ($feed['last_post_id'] === $guid) {
                    break;
                }

                if ($lastGuid === null) {
                    $lastGuid = $guid;
                }

                $title = $this->resolveTitle($entry->getTitle(), $feed);
                $content = $entry->getContent();
                $authors = $entry->getAuthors();
                $author = null;
                foreach ($authors as $authorData) {
                    $author = $authorData['name'] ?? null;
                    break;
                }
                $url = $entry->getLink();
                $dateObj = $entry->getDateCreated() ?? $entry->getDateModified();
                $date = $dateObj ? $dateObj->format('Y-m-d H:i:s') : date('Y-m-d H:i:s');

                $contentCheck = $this->checkRealContent($content, $url);
                $isVisible = $this->subscriberTextShow ? true : ($contentCheck['status'] === 'visible');

                $this->bufferItem([
                    'feed_id' => $feed['id'],
                    'title' => $title,
                    'author' => $author,
                    'content' => $content,
                    'url' => $url,
                    'image_url' => null,
                    'guid' => $guid,
                    'published_at' => $date,
                    'is_visible' => $isVisible ? 1 : 0
                ]);
                $count++;
                $updated = true;

                $processedItems++;
                if ($processedItems >= $maxItemsToProcess) {
                    break;
                }
            }

            $this->processPaginatedRssFeed($feed, $feedContent, $count, $updated, $lastGuid, $processedItems, $maxItemsToProcess);

            $flushed = $this->flushItems($feed['id']);

            if ($updated && $lastGuid) {
                $lastFeedItemId = $this->findItemIdByGuid($lastGuid);
                DB::update('feeds', [
                    'last_feed_item_id' => $lastFeedItemId,
                    'last_post_id' => $lastGuid,
                    'last_updated' => DB::sqleval("NOW()")
                ], 'id=%i', $feed['id']);
            }

            $this->climate->out("Added {$count} new items from feed: {$feed['title']}");
        } finally {
            unset($reader);
            gc_collect_cycles();
        }
    }

    private function processPaginatedRssFeed(array $feed, string $feedContent, &$count, &$updated, &$lastGuid, &$processedItems, $maxItemsToProcess): void
    {
        $nextPageUrl = $this->extractNextLink($feedContent, $feed['feed_url']);

        $maxPages = 5;
        $currentPage = 1;

        while ($nextPageUrl && $currentPage < $maxPages && $processedItems < $maxItemsToProcess) {
            $this->climate->out("Processing next page: {$nextPageUrl}");

            $nextReader = null;
            $nextContent = null;

            try {
                $nextContent = $this->fetchFeedContent($nextPageUrl);
                $nextReader = Reader::importString($nextContent);

                foreach ($nextReader as $entry) {
                    $guid = $entry->getId();

                    if ($feed['last_post_id'] === $guid) {
                        break 2;
                    }

                    if ($lastGuid === null) {
                        $lastGuid = $guid;
                    }

                    $title = $this->resolveTitle($entry->getTitle(), $feed);
                    $content = $entry->getContent();
                    $authors = $entry->getAuthors();
                    $author = null;
                    foreach ($authors as $authorData) {
                        $author = $authorData['name'] ?? null;
                        break;
                    }
                    $url = $entry->getLink();
                    $dateObj = $entry->getDateCreated() ?? $entry->getDateModified();
                    $date = $dateObj ? $dateObj->format('Y-m-d H:i:s') : date('Y-m-d H:i:s');

                    $contentCheck = $this->checkRealContent($content, $url);
                    $isVisible = $this->subscriberTextShow ? true : ($contentCheck['status'] === 'visible');

                    $this->bufferItem([
                        'feed_id' => $feed['id'],
                        'title' => $title,
                        'author' => $author,
                        'content' => $content,
                        'url' => $url,
                        'image_url' => null,
                        'guid' => $guid,
                        'published_at' => $date,
                        'is_visible' => $isVisible ? 1 : 0
                    ]);
                    $count++;
                    $updated = true;

                    $processedItems++;
                    if ($processedItems >= $maxItemsToProcess) {
                        break 2;
                    }
                }

                $nextPageUrl = $this->extractNextLink($nextContent, $nextPageUrl);
                if (!$nextPageUrl) {
                    break;
                }

                $currentPage++;
            } catch (\Exception $e) {
                $this->climate->yellow("Erro ao processar próxima página: {$e->getMessage()}");
                break;
            } finally {
                unset($nextReader);
                unset($nextContent);
            }
        }

        gc_collect_cycles();
    }

    private function fetchFeedContent(string $url): string
    {
        $response = $this->httpClient->get($url);
        $statusCode = $response->getStatusCode();
        $body = (string) $response->getBody();

        if ($statusCode === 200 && !$this->isCdnBlocked($statusCode, $body, $response)) {
            return $body;
        }

        $blocked = $this->isCdnBlocked($statusCode, $body, $response);

        if ($blocked && !$this->usingProxy && $this->proxyService->getRandomProxy() !== null) {
            $this->climate->yellow("CDN block detected for {$url}, retrying via proxy...");
            $this->setupProxyClient();

            $response = $this->httpClient->get($url);
            $statusCode = $response->getStatusCode();
            $body = (string) $response->getBody();

            if ($statusCode === 200 && !$this->isCdnBlocked($statusCode, $body, $response)) {
                return $body;
            }

            throw new \Exception("CDN block (Cloudflare) persists after proxy retry on {$url} (HTTP {$statusCode})");
        }

        if ($blocked) {
            throw new \Exception("CDN block (Cloudflare) on {$url} (HTTP {$statusCode})");
        }

        throw new \Exception("Failed to fetch feed: HTTP Status {$statusCode}");
    }

    /**
     * Detect CDN/anti-bot blocks (Cloudflare challenge, captcha, rate limit).
     * Catches both explicit block status codes and HTTP 200 challenge pages.
     */
    private function isCdnBlocked(int $status, string $body, $response = null): bool
    {
        if (in_array($status, [403, 429, 503], true)) {
            return true;
        }

        if ($response !== null) {
            $cfMitigated = $response->getHeaderLine('cf-mitigated');
            if (stripos($cfMitigated, 'challenge') !== false) {
                return true;
            }
        }

        $sample = substr($body, 0, 4096);
        $markers = [
            'Just a moment',
            'Attention Required',
            'cf-browser-verification',
            'Checking your browser',
            'challenge-platform',
            '_cf_chl',
            'cf_chl_opt',
            'Cloudflare Ray ID',
            'DDoS protection by',
            'enable JavaScript and cookies',
            'Please enable Cookies',
            'cf-turnstile',
            'hcaptcha',
        ];

        foreach ($markers as $marker) {
            if (stripos($sample, $marker) !== false) {
                return true;
            }
        }

        return false;
    }

    private function extractNextLink(string $feedXml, string $feedUrl): ?string
    {
        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadXML($feedXml);
        libxml_clear_errors();

        if ($dom->documentElement !== null) {
            $xpath = new \DOMXPath($dom);
            $links = $xpath->query('//*[local-name()="link"][@rel="next"]');
            if ($links && $links->length > 0) {
                $href = $links->item(0)->getAttribute('href');
                if ($href) {
                    return $href;
                }
            }
        }

        if (strpos($feedUrl, 'page=') !== false) {
            $urlParts = parse_url($feedUrl);
            parse_str($urlParts['query'] ?? '', $queryParams);
            if (isset($queryParams['page'])) {
                $queryParams['page'] = (int)$queryParams['page'] + 1;
                $urlParts['query'] = http_build_query($queryParams);
                return $this->buildUrl($urlParts);
            }
        }

        return null;
    }

    private function buildUrl(array $parts): string
    {
        $url = '';

        if (isset($parts['scheme'])) {
            $url .= $parts['scheme'] . '://';
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            if (isset($parts['user'])) {
                $url .= $parts['user'];
            }
            if (isset($parts['pass'])) {
                $url .= ':' . $parts['pass'];
            }
            $url .= '@';
        }

        if (isset($parts['host'])) {
            $url .= $parts['host'];
        }

        if (isset($parts['port'])) {
            $url .= ':' . $parts['port'];
        }

        if (isset($parts['path'])) {
            $url .= $parts['path'];
        }

        if (isset($parts['query'])) {
            $url .= '?' . $parts['query'];
        }

        if (isset($parts['fragment'])) {
            $url .= '#' . $parts['fragment'];
        }

        return $url;
    }

    private function processCsvFeed(array $feed): void
    {
        $this->climate->info("Processing CSV feed: {$feed['feed_url']}");
        
        $content = null;
        $lines = null;

        try {
            $content = $this->fetchFeedContent($feed['feed_url']);
        } catch (\Exception $e) {
            throw new \Exception("Failed to fetch CSV feed: " . $e->getMessage());
        }

        try {
            $lines = explode("\n", $content);
            unset($content);
            
            $headers = str_getcsv(array_shift($lines));

            $titleIndex = array_search('title', $headers);
            $authorIndex = array_search('author', $headers);
            $contentIndex = array_search('content', $headers);
            $urlIndex = array_search('url', $headers);
            $guidIndex = array_search('guid', $headers);
            $dateIndex = array_search('date', $headers);

            if ($titleIndex === false || $urlIndex === false || $guidIndex === false) {
                throw new \Exception("CSV feed missing required columns (title, url, guid)");
            }

            $count = 0;
            $updated = false;
            $lastGuid = null;

            foreach ($lines as $line) {
                if (empty(trim($line))) continue;

                $data = str_getcsv($line);
                if (count($data) <= $guidIndex) continue;

                $guid = $data[$guidIndex];

                if ($feed['last_post_id'] === $guid) {
                    break;
                }

                if ($lastGuid === null) {
                    $lastGuid = $guid;
                }

                $url = $data[$urlIndex];
                $title = $this->resolveTitle($data[$titleIndex] ?? null, $feed);

                $this->climate->whisper("Processing item: {$title} ({$url})");

                $itemContent = $contentIndex !== false && isset($data[$contentIndex]) ? $data[$contentIndex] : null;
                $contentCheck = $this->checkRealContent($itemContent, $url);
                $isVisible = $this->subscriberTextShow ? true : ($contentCheck['status'] === 'visible');

                $this->bufferItem([
                    'feed_id' => $feed['id'],
                    'title' => $title,
                    'author' => $authorIndex !== false && isset($data[$authorIndex]) ? $data[$authorIndex] : null,
                    'content' => $itemContent,
                    'url' => $url,
                    'image_url' => null,
                    'guid' => $guid,
                    'published_at' => $dateIndex !== false && isset($data[$dateIndex]) ? $data[$dateIndex] : date('Y-m-d H:i:s'),
                    'is_visible' => $isVisible ? 1 : 0
                ]);
                $count++;
                $updated = true;
                $this->climate->whisper("Item queued successfully: {$title}");
                
                unset($data);
            }

            $this->processPaginatedCsvFeed($feed, $count, $updated, $lastGuid);

            $flushed = $this->flushItems($feed['id']);

            if ($updated && $lastGuid) {
                $lastFeedItemId = $this->findItemIdByGuid($lastGuid);
                DB::update('feeds', [
                    'last_feed_item_id' => $lastFeedItemId,
                    'last_post_id' => $lastGuid,
                    'last_updated' => DB::sqleval("NOW()")
                ], 'id=%i', $feed['id']);
            }

            $this->climate->out("Added {$count} new items from CSV feed: {$feed['title']}");
        } finally {
            unset($lines);
            gc_collect_cycles();
        }
    }

    private function processPaginatedCsvFeed(array $feed, &$count, &$updated, &$lastGuid): void
    {
        if (strpos($feed['feed_url'], 'page=') === false && strpos($feed['feed_url'], 'offset=') === false) {
            return;
        }

        $urlParts = parse_url($feed['feed_url']);
        parse_str($urlParts['query'] ?? '', $queryParams);

        $pageParam = null;
        $currentValue = null;

        if (isset($queryParams['page'])) {
            $pageParam = 'page';
            $currentValue = (int)$queryParams['page'];
            $nextValue = $currentValue + 1;
        } elseif (isset($queryParams['offset'])) {
            $pageParam = 'offset';
            $currentValue = (int)$queryParams['offset'];
            $limit = $queryParams['limit'] ?? 10;
            $nextValue = $currentValue + $limit;
        } else {
            return;
        }

        $maxPages = 5;
        $currentPage = 1;
        $maxItemsToProcess = 100;
        $processedItems = $count;

        while ($currentPage < $maxPages && $processedItems < $maxItemsToProcess) {
            $queryParams[$pageParam] = $nextValue;
            $urlParts['query'] = http_build_query($queryParams);
            $nextPageUrl = $this->buildUrl($urlParts);

            $this->climate->out("Processing next CSV page: {$nextPageUrl}");

            try {
                $this->climate->whisper("Fetching next CSV page: {$nextPageUrl}");
                $content = $this->fetchFeedContent($nextPageUrl);

                $lines = explode("\n", $content);
                $headers = str_getcsv(array_shift($lines));

                $titleIndex = array_search('title', $headers);
                $authorIndex = array_search('author', $headers);
                $contentIndex = array_search('content', $headers);
                $urlIndex = array_search('url', $headers);
                $guidIndex = array_search('guid', $headers);
                $dateIndex = array_search('date', $headers);

                if ($titleIndex === false || $urlIndex === false || $guidIndex === false) {
                    $this->climate->yellow("CSV feed missing required columns (title, url, guid)");
                    break;
                }

                $pageItemCount = 0;

                foreach ($lines as $line) {
                    if (empty(trim($line))) continue;

                    $data = str_getcsv($line);
                    if (count($data) <= $guidIndex) continue;

                    $guid = $data[$guidIndex];

                    if ($feed['last_post_id'] === $guid) {
                        break 2;
                    }

                    if ($lastGuid === null) {
                        $lastGuid = $guid;
                    }

                    $url = $data[$urlIndex];
                    $title = $this->resolveTitle($data[$titleIndex] ?? null, $feed);

                    $this->climate->whisper("Processing item from page {$currentPage}: {$title} ({$url})");

                    $content = $contentIndex !== false && isset($data[$contentIndex]) ? $data[$contentIndex] : null;
                    $contentCheck = $this->checkRealContent($content, $url);
                    $isVisible = $this->subscriberTextShow ? true : ($contentCheck['status'] === 'visible');

                    $this->bufferItem([
                        'feed_id' => $feed['id'],
                        'title' => $title,
                        'author' => $authorIndex !== false && isset($data[$authorIndex]) ? $data[$authorIndex] : null,
                        'content' => $content,
                        'url' => $url,
                        'image_url' => null,
                        'guid' => $guid,
                        'published_at' => $dateIndex !== false && isset($data[$dateIndex]) ? $data[$dateIndex] : date('Y-m-d H:i:s'),
                        'is_visible' => $isVisible ? 1 : 0
                    ]);
                    $count++;
                    $pageItemCount++;
                    $updated = true;
                    $this->climate->whisper("Item queued successfully: {$title}");

                    $processedItems++;
                    if ($processedItems >= $maxItemsToProcess) {
                        break 2;
                    }
                }

                if ($pageItemCount === 0) {
                    break;
                }

                if ($pageParam === 'page') {
                    $nextValue++;
                } else {
                    $nextValue += $limit;
                }

                $currentPage++;
            } catch (\Exception $e) {
                $this->climate->yellow("Error processing next CSV page: {$e->getMessage()}");
                break;
            }
        }
    }

    private function processJsonFeed(array $feed): void
    {
        $this->climate->info("Processing JSON feed: {$feed['feed_url']}");
        
        $content = null;
        $data = null;
        $items = null;

        try {
            $content = $this->fetchFeedContent($feed['feed_url']);

            $data = json_decode($content, true);
            unset($content);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception("Invalid JSON feed: " . json_last_error_msg());
            }

            $items = $data['items'] ?? $data['entries'] ?? $data['feed'] ?? $data;

            if (!is_array($items)) {
                throw new \Exception("Could not find items in JSON feed");
            }
            $nextPageUrl = $data['next'] ?? $data['next_page'] ?? $data['nextPage'] ?? null;
            
            unset($data);

            $count = 0;
            $updated = false;
            $lastGuid = null;
            $processedItems = 0;
            $maxItemsToProcess = 100;

            foreach ($items as $item) {
                $guid = $item['id'] ?? $item['guid'] ?? $item['url'] ?? null;
                if (!$guid) {
                    continue;
                }

                if ($feed['last_post_id'] === $guid) {
                    break;
                }

                if ($lastGuid === null) {
                    $lastGuid = $guid;
                }

                $title = $this->resolveTitle($item['title'] ?? null, $feed);
                $itemContent = $item['content'] ?? $item['content_html'] ?? $item['summary'] ?? '';
                $author = $item['author']['name'] ?? $item['author'] ?? null;
                $url = $item['url'] ?? $item['link'] ?? '';
                $date = $item['date_published'] ?? $item['published'] ?? $item['date'] ?? date('Y-m-d H:i:s');

                $this->climate->whisper("Processing JSON item: {$title} ({$url})");

                $contentCheck = $this->checkRealContent($itemContent, $url);
                $isVisible = $this->subscriberTextShow ? true : ($contentCheck['status'] === 'visible');

                $this->bufferItem([
                    'feed_id' => $feed['id'],
                    'title' => $title,
                    'author' => $author,
                    'content' => $itemContent,
                    'url' => $url,
                    'image_url' => null,
                    'guid' => $guid,
                    'published_at' => $date,
                    'is_visible' => $isVisible ? 1 : 0
                ]);
                $count++;
                $updated = true;
                $this->climate->whisper("JSON item queued successfully: {$title}");

                $processedItems++;
                if ($processedItems >= $maxItemsToProcess) {
                    break;
                }
            }

            if ($nextPageUrl) {
                $this->processPaginatedJsonFeed($feed, $nextPageUrl, $count, $updated, $lastGuid, $processedItems, $maxItemsToProcess);
            }

            $flushed = $this->flushItems($feed['id']);

            if ($updated && $lastGuid) {
                $lastFeedItemId = $this->findItemIdByGuid($lastGuid);
                DB::update('feeds', [
                    'last_feed_item_id' => $lastFeedItemId,
                    'last_post_id' => $lastGuid,
                    'last_updated' => DB::sqleval("NOW()")
                ], 'id=%i', $feed['id']);
            }

            $this->climate->out("Added {$count} new items from JSON feed: {$feed['title']}");
        } catch (\Exception $e) {
            throw $e;
        } finally {
            unset($items);
            unset($data);
            unset($content);
            gc_collect_cycles();
        }
    }

    private function processPaginatedJsonFeed(array $feed, string $nextPageUrl, &$count, &$updated, &$lastGuid, &$processedItems, $maxItemsToProcess): void
    {
        $maxPages = 5;
        $currentPage = 1;

        while ($nextPageUrl && $currentPage < $maxPages && $processedItems < $maxItemsToProcess) {
            $this->climate->out("Processing next JSON page: {$nextPageUrl}");

            try {
                $this->climate->whisper("Fetching next JSON page: {$nextPageUrl}");
                $content = $this->fetchFeedContent($nextPageUrl);

                $data = json_decode($content, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $this->climate->yellow("Invalid JSON in next page: " . json_last_error_msg());
                    break;
                }

                $items = $data['items'] ?? $data['entries'] ?? $data['feed'] ?? $data;

                if (!is_array($items)) {
                    $this->climate->yellow("Could not find items in next JSON page");
                    break;
                }

                $pageItemCount = 0;

                foreach ($items as $item) {
                    $guid = $item['id'] ?? $item['guid'] ?? $item['url'] ?? null;
                    if (!$guid) {
                        continue;
                    }

                    if ($feed['last_post_id'] === $guid) {
                        break 2;
                    }

                    if ($lastGuid === null) {
                        $lastGuid = $guid;
                    }

                    $title = $this->resolveTitle($item['title'] ?? null, $feed);
                    $content = $item['content'] ?? $item['content_html'] ?? $item['summary'] ?? '';
                    $author = $item['author']['name'] ?? $item['author'] ?? null;
                    $url = $item['url'] ?? $item['link'] ?? '';
                    $date = $item['date_published'] ?? $item['published'] ?? $item['date'] ?? date('Y-m-d H:i:s');

                    $this->climate->whisper("Processing JSON item from page {$currentPage}: {$title} ({$url})");

                    $contentCheck = $this->checkRealContent($content, $url);
                    $isVisible = $this->subscriberTextShow ? true : ($contentCheck['status'] === 'visible');

                    $this->bufferItem([
                        'feed_id' => $feed['id'],
                        'title' => $title,
                        'author' => $author,
                        'content' => $content,
                        'url' => $url,
                        'image_url' => null,
                        'guid' => $guid,
                        'published_at' => $date,
                        'is_visible' => $isVisible ? 1 : 0
                    ]);
                    $count++;
                    $pageItemCount++;
                    $updated = true;
                    $this->climate->whisper("JSON item from page {$currentPage} queued successfully: {$title}");

                    $processedItems++;
                    if ($processedItems >= $maxItemsToProcess) {
                        break 2;
                    }
                }

                if ($pageItemCount === 0) {
                    break;
                }

                $nextPageUrl = $data['next'] ?? $data['next_page'] ?? $data['nextPage'] ?? null;
                if (!$nextPageUrl) {
                    break;
                }

                $currentPage++;
            } catch (\Exception $e) {
                $this->climate->yellow("Error processing next JSON page: {$e->getMessage()}");
                break;
            }
        }
    }

    private function processXmlFeed(array $feed): void
    {
        $this->climate->info("Processing XML feed: {$feed['feed_url']}");
        
        $content = null;
        $xml = null;
        $items = null;

        try {
            $content = $this->fetchFeedContent($feed['feed_url']);

            $xml = simplexml_load_string($content);
            unset($content);
            
            if ($xml === false) {
                throw new \Exception("Invalid XML feed");
            }

            $items = $xml->xpath('//item') ?: $xml->xpath('//entry') ?: [];

            $count = 0;
            $updated = false;
            $lastGuid = null;
            $processedItems = 0;
            $maxItemsToProcess = 100;

            foreach ($items as $item) {
                $guid = (string)($item->guid ?? $item->id ?? $item->link ?? '');
                if (empty($guid)) {
                    continue;
                }

                if ($feed['last_post_id'] === $guid) {
                    break;
                }

                if ($lastGuid === null) {
                    $lastGuid = $guid;
                }

                $title = $this->resolveTitle((string)($item->title ?? ''), $feed);
                $itemContent = (string)($item->description ?? $item->content ?? $item->summary ?? '');
                $author = (string)($item->author ?? $item->creator ?? '');
                $url = (string)($item->link ?? $item->url ?? '');
                $date = (string)($item->pubDate ?? $item->published ?? $item->date ?? date('Y-m-d H:i:s'));

                $this->climate->whisper("Processing XML item: {$title} ({$url})");

                $contentCheck = $this->checkRealContent($itemContent, $url);
                $isVisible = $this->subscriberTextShow ? true : ($contentCheck['status'] === 'visible');

                $this->bufferItem([
                    'feed_id' => $feed['id'],
                    'title' => $title,
                    'author' => $author,
                    'content' => $itemContent,
                    'url' => $url,
                    'image_url' => null,
                    'guid' => $guid,
                    'published_at' => $date,
                    'is_visible' => $isVisible ? 1 : 0
                ]);
                $count++;
                $updated = true;
                $this->climate->whisper("XML item queued successfully: {$title}");

                $processedItems++;
                if ($processedItems >= $maxItemsToProcess) {
                    break;
                }
            }

            $this->processPaginatedXmlFeed($feed, $xml, $count, $updated, $lastGuid, $processedItems, $maxItemsToProcess);

            $flushed = $this->flushItems($feed['id']);

            if ($updated && $lastGuid) {
                $lastFeedItemId = $this->findItemIdByGuid($lastGuid);
                DB::update('feeds', [
                    'last_feed_item_id' => $lastFeedItemId,
                    'last_post_id' => $lastGuid,
                    'last_updated' => DB::sqleval("NOW()")
                ], 'id=%i', $feed['id']);
            }
            $this->climate->out("Added {$count} new items from XML feed: {$feed['title']}");
        } catch (\Exception $e) {
            throw $e;
        } finally {
            unset($items);
            unset($xml);
            unset($content);
            gc_collect_cycles();
        }
    }

    private function processPaginatedXmlFeed(array $feed, \SimpleXMLElement $xml, &$count, &$updated, &$lastGuid, &$processedItems, $maxItemsToProcess): void
    {
        $nextPageUrl = null;

        $links = $xml->xpath('//link[@rel="next"]') ?: $xml->xpath('//atom:link[@rel="next"]') ?: [];
        if (!empty($links)) {
            foreach ($links as $link) {
                $attributes = $link->attributes();
                if (isset($attributes['href'])) {
                    $nextPageUrl = (string)$attributes['href'];
                    break;
                }
            }
        }

        if (!$nextPageUrl && strpos($feed['feed_url'], 'page=') !== false) {
            $urlParts = parse_url($feed['feed_url']);
            parse_str($urlParts['query'] ?? '', $queryParams);

            if (isset($queryParams['page'])) {
                $queryParams['page'] = (int)$queryParams['page'] + 1;
                $urlParts['query'] = http_build_query($queryParams);

                $nextPageUrl = $this->buildUrl($urlParts);
            }
        }

        $maxPages = 5;
        $currentPage = 1;

        while ($nextPageUrl && $currentPage < $maxPages && $processedItems < $maxItemsToProcess) {
            $this->climate->out("Processing next XML page: {$nextPageUrl}");

            try {
                $this->climate->whisper("Fetching next XML page: {$nextPageUrl}");
                $content = $this->fetchFeedContent($nextPageUrl);

                $nextXml = simplexml_load_string($content);
                if ($nextXml === false) {
                    $this->climate->yellow("Invalid XML in next page");
                    break;
                }

                $items = $nextXml->xpath('//item') ?: $nextXml->xpath('//entry') ?: [];

                $pageItemCount = 0;

                foreach ($items as $item) {
                    $guid = (string)($item->guid ?? $item->id ?? $item->link ?? '');
                    if (empty($guid)) {
                        continue;
                    }

                    if ($feed['last_post_id'] === $guid) {
                        break 2;
                    }

                    if ($lastGuid === null) {
                        $lastGuid = $guid;
                    }

                    $title = $this->resolveTitle((string)($item->title ?? ''), $feed);
                    $content = (string)($item->description ?? $item->content ?? $item->summary ?? '');
                    $author = (string)($item->author ?? $item->creator ?? '');
                    $url = (string)($item->link ?? $item->url ?? '');
                    $date = (string)($item->pubDate ?? $item->published ?? $item->date ?? date('Y-m-d H:i:s'));

                    $this->climate->whisper("Processing XML item from page {$currentPage}: {$title} ({$url})");

                    $contentCheck = $this->checkRealContent($content, $url);
                    $isVisible = $this->subscriberTextShow ? true : ($contentCheck['status'] === 'visible');

                    $this->bufferItem([
                        'feed_id' => $feed['id'],
                        'title' => $title,
                        'author' => $author,
                        'content' => $content,
                        'url' => $url,
                        'image_url' => null,
                        'guid' => $guid,
                        'published_at' => $date,
                        'is_visible' => $isVisible ? 1 : 0
                    ]);
                    $count++;
                    $pageItemCount++;
                    $updated = true;
                    $this->climate->whisper("XML item from page {$currentPage} queued successfully: {$title}");

                    $processedItems++;
                    if ($processedItems >= $maxItemsToProcess) {
                        break 2;
                    }
                }

                if ($pageItemCount === 0) {
                    break;
                }

                $links = $nextXml->xpath('//link[@rel="next"]') ?: $nextXml->xpath('//atom:link[@rel="next"]') ?: [];
                $nextPageUrl = null;

                if (!empty($links)) {
                    foreach ($links as $link) {
                        $attributes = $link->attributes();
                        if (isset($attributes['href'])) {
                            $nextPageUrl = (string)$attributes['href'];
                            break;
                        }
                    }
                }

                if (!$nextPageUrl) {
                    break;
                }

                $currentPage++;
            } catch (\Exception $e) {
                $this->climate->yellow("Error processing next XML page: {$e->getMessage()}");
                break;
            }
        }
        $this->climate->out("Added {$count} new items from XML feed: {$feed['title']}");
    }
    
    private function resolveTitle(?string $title, array $feed): string
    {
        $title = trim((string)$title);
        if ($title !== '') {
            return $title;
        }
        $language = $feed['language'] ?? 'en';
        return \App\Services\Translator::getInstance()->translateFor('feed_item.no_title', $language);
    }

    private function checkRealContent(?string $content, ?string $url = ''): array
    {
        if (empty($content)) {
            return ['status' => 'visible', 'reason' => 'empty_content'];
        }

        // WordPress Password Protected Post
        $passwordPatterns = [
            'wp-login.php?action=postpass',
            'Este conteúdo está protegido por senha',
            'This content is password protected.'
        ];

        foreach ($passwordPatterns as $pattern) {
            if (strpos($content, $pattern) !== false) {
                $this->climate->whisper("Detected WordPress password protected post in: {$url}");
                return ['status' => 'invisible', 'reason' => 'wordpress_password_protected'];
            }
        }

        $trimmedContent = trim($content);
        $endContent = substr($trimmedContent, -500);

        // Substack "Read more" patterns
        $readMorePatterns = [
            '/<p>\s*<a\s+href=["\']https?:\/\/[^"\']*\.?substack\.com[^"\']*["\']>\s*Read more\s*<\/a>\s*<\/p>\s*$/i',
            '/<p[^>]*>\s*<a[^>]+href=["\']https?:\/\/[^"\']*\.?substack\.com[^"\']*["\'][^>]*>\s*Read\s+more\s*<\/a>\s*<\/p>\s*$/i'
        ];

        foreach ($readMorePatterns as $pattern) {
            if (preg_match($pattern, $endContent)) {
                $this->climate->whisper("Detected Substack subscriber-only content in: {$url}");
                return ['status' => 'invisible', 'reason' => 'substack_read_more'];
            }
        }

        // Generic subscriber-only text indicators
        $subscriberIndicators = [
            '/Este (?:é um )?conteúdo exclusivo para (?:os )?assinantes/i',
            '/This is (?:a |an )?(?:exclusive )?content for (?:paid )?subscribers/i',
            '/Este(?:s)? (?:es|é) contenido exclusivo para suscriptores/i',
            '/Subscribe (?:now )?to (?:keep |continue )?reading/i',
            '/Assine (?:agora )?para (?:continuar |seguir )?lendo/i',
            '/Suscr[ií]bete (?:ahora )?para (?:seguir |continuar )?leyendo/i'
        ];

        foreach ($subscriberIndicators as $pattern) {
            if (preg_match($pattern, $content)) {
                $this->climate->whisper("Detected subscriber-only content (text indicator) in: {$url}");
                return ['status' => 'invisible', 'reason' => 'subscriber_text'];
            }
        }

        return ['status' => 'visible', 'reason' => 'no_patterns_matched'];
    }

    public function checkItemsContent(): void
    {
        $this->climate->info("Checking all feed items for special content...");

        $batchSize = (int)($_ENV['CONTENT_CHECK_BATCH_SIZE'] ?? 500);
        $lastId = 0;
        $markedInvisible = 0;
        $processed = 0;

        do {
            $items = DB::query(
                "SELECT id, url, content FROM feed_items WHERE is_visible = 1 AND id > %i ORDER BY id ASC LIMIT %i",
                $lastId,
                $batchSize
            ) ?: [];

            $batchCount = count($items);
            if ($batchCount === 0) {
                break;
            }

            foreach ($items as $item) {
                $processed++;
                $lastId = (int)$item['id'];

                $contentCheck = $this->checkRealContent($item['content'], $item['url']);

                if ($contentCheck['status'] === 'invisible') {
                    DB::update('feed_items', [
                        'is_visible' => 0
                    ], 'id=%i', $item['id']);

                    $markedInvisible++;
                    $this->climate->whisper("Marked as invisible (ID: {$item['id']}, Reason: {$contentCheck['reason']}): {$item['url']}");
                }

                if ($processed % 100 === 0) {
                    $this->climate->info("Progress: {$processed} items checked...");
                }

                unset($item);
            }

            unset($items);
            gc_collect_cycles();
        } while ($batchCount === $batchSize);

        if ($markedInvisible > 0) {
            $this->climate->info("Invalidating item cache due to visibility changes...");
            $deleted = CacheInvalidator::invalidateItems();
            $this->climate->green("✓ Invalidated {$deleted} cache tag reference(s)");
        }

        $this->climate->green("✓ Process complete!");
        $this->climate->info("Total items checked: {$processed}");
        $this->climate->info("Items marked as invisible: {$markedInvisible}");
    }

    private function extractImageFromUrl(string $url): ?string
    {
        if (empty($url)) {
            return null;
        }

        try {
            $this->climate->whisper("Extracting image from: {$url}");

            $imageClient = new \GuzzleHttp\Client(HttpClientConfig::getExtractedImageConfig());

            $response = $imageClient->get($url);
            $statusCode = $response->getStatusCode();

            sleep(1);

            if ($statusCode !== 200) {
                $this->climate->whisper("Failed to extract image: Status {$statusCode}");
                return null;
            }

            $html = (string) $response->getBody();
            $parsedUrl = parse_url($url);
            $baseUrl = $parsedUrl['scheme'] . '://' . $parsedUrl['host'];

            if (preg_match('/<meta[^>]*property=["\']og:image["\'][^>]*content=["\'](.*?)["\'][^>]*>/i', $html, $matches)) {
                $imageUrl = $matches[1];
                if (substr($imageUrl, 0, 1) === '/') {
                    $imageUrl = $baseUrl . $imageUrl;
                    $this->climate->whisper("Converted relative URL to absolute: {$imageUrl}");
                }
                
                $this->climate->whisper("Image extracted (og:image): {$imageUrl}");
                return $imageUrl;
            }

            if (preg_match('/<meta[^>]*content=["\'](.*?)["\'][^>]*property=["\']og:image["\'][^>]*>/i', $html, $matches)) {
                $imageUrl = $matches[1];
                if (substr($imageUrl, 0, 1) === '/') {
                    $imageUrl = $baseUrl . $imageUrl;
                    $this->climate->whisper("Converted relative URL to absolute: {$imageUrl}");
                }
                
                $this->climate->whisper("Image extracted (og:image alt): {$imageUrl}");
                return $imageUrl;
            }

            $this->climate->whisper("No image found");
            return null;
        } catch (GuzzleException $e) {
            $this->climate->whisper("Error extracting image: {$e->getMessage()}");
            return null;
        } catch (\Exception $e) {
            $this->climate->whisper("Error extracting image: {$e->getMessage()}");
            return null;
        }
    }

    private function extractFeedTitle(array $feed): ?string
    {
        try {
            $feedUrl = $feed['feed_url'];
            $feedType = $feed['feed_type'];

            $this->climate->whisper("Extracting feed title from: {$feedUrl}");

            if (in_array($feedType, ['rss1', 'rss2', 'atom', 'rdf'])) {
                $feedContent = $this->fetchFeedContent($feedUrl);
                $reader = Reader::importString($feedContent);
                $title = $reader->getTitle();
                unset($reader);

                if ($title) {
                    $this->climate->whisper("Feed title extracted: {$title}");
                    return $title;
                }
            }

            $this->climate->whisper("Could not extract feed title");
            return null;

        } catch (\Exception $e) {
            $this->climate->whisper("Error extracting feed title: {$e->getMessage()}");
            return null;
        } finally {
            gc_collect_cycles();
        }
    }
}
