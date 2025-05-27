<?php
declare(strict_types=1);

namespace Lerama\Commands;

use League\CLImate\CLImate;
use SimplePie\SimplePie;
use DB;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Lerama\Services\ProxyService;
use Lerama\Services\EmailService;

class FeedProcessor
{
    private CLImate $climate;
    private \GuzzleHttp\Client $httpClient;
    private ProxyService $proxyService;
    private EmailService $emailService;
    private array $defaultClientConfig;

    public function __construct(CLImate $climate)
    {
        $this->climate = $climate;
        $this->proxyService = new ProxyService();
        $this->emailService = new EmailService();
        
        $this->defaultClientConfig = [
            'timeout' => 10,
            'connect_timeout' => 5,
            'http_errors' => false,
            'allow_redirects' => [
                'max' => 2,
                'strict' => true,
                'referer' => true,
                'protocols' => ['http', 'https'],
                'track_redirects' => true
            ],
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Linux; Android 6.0.1; Nexus 5X Build/MMB29P) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/W.X.Y.Z Mobile Safari/537.36 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.5',
                'Cache-Control' => 'no-cache',
                'Pragma' => 'no-cache',
                'DNT' => '1',
                'X-Forwarded-For' => '66.249.' . rand(64, 95) . '.' . rand(1, 254),
                'From' => 'googlebot(at)googlebot.com'
            ],
            'curl' => [
                CURLOPT_DNS_SERVERS => '8.8.8.8'
            ]
        ];
        
        $this->httpClient = new \GuzzleHttp\Client($this->defaultClientConfig);
    }

    public function process(?int $feedId = null): void
    {
        if ($feedId) {
            $this->climate->info("Processing feed ID: {$feedId}");
            $feeds = DB::query("SELECT * FROM feeds WHERE id = %i AND (status = 'online' OR status = 'paused')", $feedId);
        } else {
            $this->climate->info("Processing all online feeds");
            $feeds = DB::query("SELECT * FROM feeds WHERE status = 'online'");
        }

        if (empty($feeds)) {
            $this->climate->warning("No feeds found to process");
            return;
        }

        foreach ($feeds as $feed) {
            $this->climate->out("Processando: {$feed['title']} ({$feed['feed_url']})");
            
            $useProxy = ($feed['retry_proxy'] ?? 0) == 1;
            
            if ($useProxy) {
                $this->setupProxyClient();
                $this->climate->info("Using proxy for feed: {$feed['title']}");
            } else {
                $this->httpClient = new \GuzzleHttp\Client($this->defaultClientConfig);
            }
            
            try {
                $this->processFeed($feed);
                
                DB::update('feeds', [
                    'last_checked' => DB::sqleval("NOW()"),
                    'status' => 'online',
                    'retry_count' => 0,
                    'retry_proxy' => 0,
                    'paused_at' => null
                ], 'id=%i', $feed['id']);
                
                $this->climate->green("✓ Feed processed successfully: {$feed['title']}");
            } catch (\Exception $e) {
                $this->climate->red("✗ Error processing feed {$feed['title']}: {$e->getMessage()}");
                
                $retryCount = ($feed['retry_count'] ?? 0) + 1;
                $this->climate->info("Attempt {$retryCount} for feed: {$feed['title']}");
                
                if ($retryCount > 10) {
                    $this->climate->yellow("Feed {$feed['title']} marked as paused after {$retryCount} attempts");
                    DB::update('feeds', [
                        'last_checked' => DB::sqleval("NOW()"),
                        'status' => 'paused',
                        'retry_count' => $retryCount,
                        'paused_at' => DB::sqleval("NOW()")
                    ], 'id=%i', $feed['id']);
                } else if ($retryCount > 3) {
                    $this->climate->yellow("Feed {$feed['title']} will use proxy in next attempts");
                    DB::update('feeds', [
                        'last_checked' => DB::sqleval("NOW()"),
                        'status' => 'online',
                        'retry_count' => $retryCount,
                        'retry_proxy' => 1
                    ], 'id=%i', $feed['id']);
                } else {
                    DB::update('feeds', [
                        'last_checked' => DB::sqleval("NOW()"),
                        'status' => 'online',
                        'retry_count' => $retryCount
                    ], 'id=%i', $feed['id']);
                }
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
            
            $this->climate->info("Feed {$feed['title']} has been paused for " . round($hoursSincePaused, 1) . " hours");
            
            if ($hoursSincePaused >= 72) {
                $this->climate->info("Trying to process feed {$feed['title']} after 72 hours paused");
                
                try {
                    $this->httpClient = new \GuzzleHttp\Client($this->defaultClientConfig);
                    
                    $this->processFeed($feed);
                    
                    $this->climate->green("✓ Feed {$feed['title']} is working again after 72 hours paused");
                    DB::update('feeds', [
                        'last_checked' => DB::sqleval("NOW()"),
                        'status' => 'online',
                        'retry_count' => 0,
                        'retry_proxy' => 0,
                        'paused_at' => null
                    ], 'id=%i', $feed['id']);
                } catch (\Exception $e) {
                    $this->climate->red("✗ Feed {$feed['title']} remains inaccessible after 72 hours paused");
                    DB::update('feeds', [
                        'last_checked' => DB::sqleval("NOW()"),
                        'status' => 'offline'
                    ], 'id=%i', $feed['id']);
                    
                    $this->emailService->sendFeedOfflineNotification($feed);
                    $this->climate->info("Notification sent to administrator about offline feed: {$feed['title']}");
                }
            }
            
            else if ($hoursSincePaused >= 24) {
                $this->climate->info("Trying to process feed {$feed['title']} after 24 hours paused");
                
                try {
                    $this->setupProxyClient();
                    
                    $this->processFeed($feed);
                    
                    $this->climate->green("✓ Feed {$feed['title']} is working again after 24 hours paused");
                    DB::update('feeds', [
                        'last_checked' => DB::sqleval("NOW()"),
                        'status' => 'online',
                        'retry_count' => 0,
                        'retry_proxy' => 0,
                        'paused_at' => null
                    ], 'id=%i', $feed['id']);
                } catch (\Exception $e) {
                    $this->climate->yellow("! Feed {$feed['title']} remains inaccessible after 24 hours paused");
                    DB::update('feeds', [
                        'last_checked' => DB::sqleval("NOW()")
                    ], 'id=%i', $feed['id']);
                }
            }
        }
    }
    
    private function setupProxyClient(): bool
    {
        $proxy = $this->proxyService->getRandomProxy();
        
        if (!$proxy) {
            $this->climate->warning("No proxy available, using direct connection");
            $this->httpClient = new \GuzzleHttp\Client($this->defaultClientConfig);
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
        
        $this->climate->info("Using proxy: {$proxy['host']}:{$proxy['port']}");
        return true;
    }

    private function processFeed(array $feed): void
    {
        $feedType = $feed['feed_type'];
        $feedUrl = $feed['feed_url'];
        
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
        $simplePie = new SimplePie();
        $simplePie->set_feed_url($feed['feed_url']);
        $simplePie->enable_cache(false);
        $simplePie->init();

        if ($simplePie->error()) {
            throw new \Exception($simplePie->error());
        }

        $items = $simplePie->get_items();
        $count = 0;
        $updated = false;
        $lastGuid = null;
        $processedItems = 0;
        $maxItemsToProcess = 100;

        foreach ($items as $item) {
            $guid = $item->get_id();
            
            if ($feed['last_post_id'] === $guid) {
                break;
            }
            
            if ($lastGuid === null) {
                $lastGuid = $guid;
            }
            
            $title = $item->get_title();
            $content = $item->get_content();
            $author = $item->get_author() ? $item->get_author()->get_name() : null;
            $url = $item->get_permalink();
            $date = $item->get_date('Y-m-d H:i:s') ?: date('Y-m-d H:i:s');

            $imageUrl = $this->extractImageFromUrl($url);
            
            try {
                DB::insert('feed_items', [
                    'feed_id' => $feed['id'],
                    'title' => $title,
                    'author' => $author,
                    'content' => $content,
                    'url' => $url,
                    'image_url' => $imageUrl,
                    'guid' => $guid,
                    'published_at' => $date
                ]);
                $count++;
                $updated = true;
            } catch (\Exception $e) {
                // Erro ao processar item
                continue;
            }
            
            $processedItems++;
            if ($processedItems >= $maxItemsToProcess) {
                break;
            }
        }

        $this->processPaginatedRssFeed($feed, $simplePie, $count, $updated, $lastGuid, $processedItems, $maxItemsToProcess);

        if ($updated && $lastGuid) {
            DB::update('feeds', [
                'last_post_id' => $lastGuid,
                'last_updated' => DB::sqleval("NOW()")
            ], 'id=%i', $feed['id']);
        }

        $this->climate->out("Added {$count} new items from feed: {$feed['title']}");
    }

    private function processPaginatedRssFeed(array $feed, SimplePie $simplePie, &$count, &$updated, &$lastGuid, &$processedItems, $maxItemsToProcess): void
    {
        $links = $simplePie->get_links();
        $nextPageUrl = null;

        if ($links) {
            foreach ($links as $link) {
                if (isset($link['rel']) && ($link['rel'] === 'next' || $link['rel'] === 'self' && strpos($link['href'], 'page=') !== false)) {
                    $nextPageUrl = $link['href'];
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
            $this->climate->out("Processing next page: {$nextPageUrl}");
            
            try {
                $nextSimplePie = new SimplePie();
                $nextSimplePie->set_feed_url($nextPageUrl);
                $nextSimplePie->enable_cache(false);
                $nextSimplePie->init();
                
                if ($nextSimplePie->error()) {
                    $this->climate->yellow("Error loading next page: {$nextSimplePie->error()}");
                    break;
                }
                
                $nextItems = $nextSimplePie->get_items();
                
                foreach ($nextItems as $item) {
                    $guid = $item->get_id();

                    if ($feed['last_post_id'] === $guid) {
                        break 2;
                    }
                    
                    if ($lastGuid === null) {
                        $lastGuid = $guid;
                    }
                    
                    $title = $item->get_title();
                    $content = $item->get_content();
                    $author = $item->get_author() ? $item->get_author()->get_name() : null;
                    $url = $item->get_permalink();
                    $date = $item->get_date('Y-m-d H:i:s') ?: date('Y-m-d H:i:s');

                    $imageUrl = $this->extractImageFromUrl($url);
                    
                    try {
                        DB::insert('feed_items', [
                            'feed_id' => $feed['id'],
                            'title' => $title,
                            'author' => $author,
                            'content' => $content,
                            'url' => $url,
                            'image_url' => $imageUrl,
                            'guid' => $guid,
                            'published_at' => $date
                        ]);
                        $count++;
                        $updated = true;
                    } catch (\Exception $e) {
                        // Erro ao processar item
                        continue;
                    }
                    
                    $processedItems++;
                    if ($processedItems >= $maxItemsToProcess) {
                        break 2;
                    }
                }

                $links = $nextSimplePie->get_links();
                $nextPageUrl = null;
                
                if ($links) {
                    foreach ($links as $link) {
                        if (isset($link['rel']) && ($link['rel'] === 'next' || $link['rel'] === 'self' && strpos($link['href'], 'page=') !== false)) {
                            $nextPageUrl = $link['href'];
                            break;
                        }
                    }
                }

                if (!$nextPageUrl) {
                    break;
                }
                
                $currentPage++;
                
            } catch (\Exception $e) {
                $this->climate->yellow("Erro ao processar próxima página: {$e->getMessage()}");
                break;
            }
        }
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
        
        try {
            $response = $this->httpClient->get($feed['feed_url']);
            $statusCode = $response->getStatusCode();
            
            if ($statusCode !== 200) {
                throw new \Exception("Failed to fetch CSV feed: HTTP Status {$statusCode}");
            }
            
            $content = (string) $response->getBody();
        } catch (\Exception $e) {
            throw new \Exception("Failed to fetch CSV feed: " . $e->getMessage());
        }

        $lines = explode("\n", $content);
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
            $title = $data[$titleIndex];
            
            $this->climate->whisper("Processing item: {$title} ({$url})");
            
            $imageUrl = $this->extractImageFromUrl($url);
            
            try {
                DB::insert('feed_items', [
                    'feed_id' => $feed['id'],
                    'title' => $title,
                    'author' => $authorIndex !== false && isset($data[$authorIndex]) ? $data[$authorIndex] : null,
                    'content' => $contentIndex !== false && isset($data[$contentIndex]) ? $data[$contentIndex] : null,
                    'url' => $url,
                    'image_url' => $imageUrl,
                    'guid' => $guid,
                    'published_at' => $dateIndex !== false && isset($data[$dateIndex]) ? $data[$dateIndex] : date('Y-m-d H:i:s')
                ]);
                $count++;
                $updated = true;
                $this->climate->whisper("Item added successfully: {$title}");
            } catch (\Exception $e) {
                $this->climate->whisper("Error adding item {$title}: {$e->getMessage()}");
                continue;
            }
        }

        $this->processPaginatedCsvFeed($feed, $count, $updated, $lastGuid);
        
        if ($updated && $lastGuid) {
            DB::update('feeds', [
                'last_post_id' => $lastGuid,
                'last_updated' => DB::sqleval("NOW()")
            ], 'id=%i', $feed['id']);
        }
        
        $this->climate->out("Added {$count} new items from CSV feed: {$feed['title']}");
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
                $response = $this->httpClient->get($nextPageUrl);
                $statusCode = $response->getStatusCode();
                
                if ($statusCode !== 200) {
                    $this->climate->yellow("Failed to fetch next CSV page: HTTP Status {$statusCode}");
                    break;
                }
                
                $content = (string) $response->getBody();
                
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
                    $title = $data[$titleIndex];
                    
                    $this->climate->whisper("Processing item from page {$currentPage}: {$title} ({$url})");
                    
                    $imageUrl = $this->extractImageFromUrl($url);
                    
                    try {
                        DB::insert('feed_items', [
                            'feed_id' => $feed['id'],
                            'title' => $title,
                            'author' => $authorIndex !== false && isset($data[$authorIndex]) ? $data[$authorIndex] : null,
                            'content' => $contentIndex !== false && isset($data[$contentIndex]) ? $data[$contentIndex] : null,
                            'url' => $url,
                            'image_url' => $imageUrl,
                            'guid' => $guid,
                            'published_at' => $dateIndex !== false && isset($data[$dateIndex]) ? $data[$dateIndex] : date('Y-m-d H:i:s')
                        ]);
                        $count++;
                        $pageItemCount++;
                        $updated = true;
                        $this->climate->whisper("Item added successfully: {$title}");
                    } catch (\Exception $e) {
                        $this->climate->whisper("Error adding item {$title}: {$e->getMessage()}");
                        continue;
                    }
                    
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
        
        try {
            $response = $this->httpClient->get($feed['feed_url']);
            $statusCode = $response->getStatusCode();
            
            if ($statusCode !== 200) {
                throw new \Exception("Failed to fetch JSON feed: HTTP Status {$statusCode}");
            }
            
            $content = (string) $response->getBody();
        } catch (\Exception $e) {
            throw new \Exception("Failed to fetch JSON feed: " . $e->getMessage());
        }
        
        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("Invalid JSON feed: " . json_last_error_msg());
        }

        $items = $data['items'] ?? $data['entries'] ?? $data['feed'] ?? $data;
        
        if (!is_array($items)) {
            throw new \Exception("Could not find items in JSON feed");
        }
        $nextPageUrl = $data['next'] ?? $data['next_page'] ?? $data['nextPage'] ?? null;
        
        $count = 0;
        $updated = false;
        $lastGuid = null;
        $processedItems = 0;
        $maxItemsToProcess = 100;
        $lastGuid = null;
        
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
            
            $title = $item['title'] ?? 'Sem título';
            $content = $item['content'] ?? $item['content_html'] ?? $item['summary'] ?? '';
            $author = $item['author']['name'] ?? $item['author'] ?? null;
            $url = $item['url'] ?? $item['link'] ?? '';
            $date = $item['date_published'] ?? $item['published'] ?? $item['date'] ?? date('Y-m-d H:i:s');
            
            $this->climate->whisper("Processing JSON item: {$title} ({$url})");

            $imageUrl = $this->extractImageFromUrl($url);
            
            try {
                DB::insert('feed_items', [
                    'feed_id' => $feed['id'],
                    'title' => $title,
                    'author' => $author,
                    'content' => $content,
                    'url' => $url,
                    'image_url' => $imageUrl,
                    'guid' => $guid,
                    'published_at' => $date
                ]);
                $count++;
                $updated = true;
                $this->climate->whisper("JSON item added successfully: {$title}");
            } catch (\Exception $e) {
                $this->climate->whisper("Error adding JSON item {$title}: {$e->getMessage()}");
                continue;
            }
            
            $processedItems++;
            if ($processedItems >= $maxItemsToProcess) {
                break;
            }
        }

        if ($nextPageUrl) {
            $this->processPaginatedJsonFeed($feed, $nextPageUrl, $count, $updated, $lastGuid, $processedItems, $maxItemsToProcess);
        }
        
        if ($updated && $lastGuid) {
            DB::update('feeds', [
                'last_post_id' => $lastGuid,
                'last_updated' => DB::sqleval("NOW()")
            ], 'id=%i', $feed['id']);
        }
        
        $this->climate->out("Added {$count} new items from JSON feed: {$feed['title']}");
    }

    private function processPaginatedJsonFeed(array $feed, string $nextPageUrl, &$count, &$updated, &$lastGuid, &$processedItems, $maxItemsToProcess): void
    {
        $maxPages = 5;
        $currentPage = 1;
        
        while ($nextPageUrl && $currentPage < $maxPages && $processedItems < $maxItemsToProcess) {
            $this->climate->out("Processing next JSON page: {$nextPageUrl}");
            
            try {
                $this->climate->whisper("Fetching next JSON page: {$nextPageUrl}");
                $response = $this->httpClient->get($nextPageUrl);
                $statusCode = $response->getStatusCode();
                
                if ($statusCode !== 200) {
                    $this->climate->yellow("Failed to fetch next JSON page: HTTP Status {$statusCode}");
                    break;
                }
                
                $content = (string) $response->getBody();
                
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
                    
                    $title = $item['title'] ?? 'Sem título';
                    $content = $item['content'] ?? $item['content_html'] ?? $item['summary'] ?? '';
                    $author = $item['author']['name'] ?? $item['author'] ?? null;
                    $url = $item['url'] ?? $item['link'] ?? '';
                    $date = $item['date_published'] ?? $item['published'] ?? $item['date'] ?? date('Y-m-d H:i:s');
                    
                    $this->climate->whisper("Processing JSON item from page {$currentPage}: {$title} ({$url})");

                    $imageUrl = $this->extractImageFromUrl($url);
                    
                    try {
                        DB::insert('feed_items', [
                            'feed_id' => $feed['id'],
                            'title' => $title,
                            'author' => $author,
                            'content' => $content,
                            'url' => $url,
                            'image_url' => $imageUrl,
                            'guid' => $guid,
                            'published_at' => $date
                        ]);
                        $count++;
                        $pageItemCount++;
                        $updated = true;
                        $this->climate->whisper("JSON item from page {$currentPage} added successfully: {$title}");
                    } catch (\Exception $e) {
                        $this->climate->whisper("Error adding JSON item from page {$currentPage} {$title}: {$e->getMessage()}");
                        continue;
                    }
                    
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
        
        try {
            $response = $this->httpClient->get($feed['feed_url']);
            $statusCode = $response->getStatusCode();
            
            if ($statusCode !== 200) {
                throw new \Exception("Failed to fetch XML feed: HTTP Status {$statusCode}");
            }
            
            $content = (string) $response->getBody();
        } catch (\Exception $e) {
            throw new \Exception("Failed to fetch XML feed: " . $e->getMessage());
        }
        
        $xml = simplexml_load_string($content);
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
            
            $title = (string)($item->title ?? 'Sem título');
            $content = (string)($item->description ?? $item->content ?? $item->summary ?? '');
            $author = (string)($item->author ?? $item->creator ?? '');
            $url = (string)($item->link ?? $item->url ?? '');
            $date = (string)($item->pubDate ?? $item->published ?? $item->date ?? date('Y-m-d H:i:s'));
            
            $this->climate->whisper("Processing XML item: {$title} ({$url})");

            $imageUrl = $this->extractImageFromUrl($url);
            
            try {
                DB::insert('feed_items', [
                    'feed_id' => $feed['id'],
                    'title' => $title,
                    'author' => $author,
                    'content' => $content,
                    'url' => $url,
                    'image_url' => $imageUrl,
                    'guid' => $guid,
                    'published_at' => $date
                ]);
                $count++;
                $updated = true;
                $this->climate->whisper("XML item added successfully: {$title}");
            } catch (\Exception $e) {
                
                $this->climate->whisper("Error adding XML item {$title}: {$e->getMessage()}");
                continue;
            }
            
            $processedItems++;
            if ($processedItems >= $maxItemsToProcess) {
                break;
            }
            
        }
    
        $this->processPaginatedXmlFeed($feed, $xml, $count, $updated, $lastGuid, $processedItems, $maxItemsToProcess);
        
        if ($updated && $lastGuid) {
            DB::update('feeds', [
                'last_post_id' => $lastGuid,
                'last_updated' => DB::sqleval("NOW()")
            ], 'id=%i', $feed['id']);
        }
        $this->climate->out("Added {$count} new items from XML feed: {$feed['title']}");
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
                $response = $this->httpClient->get($nextPageUrl);
                $statusCode = $response->getStatusCode();
                
                if ($statusCode !== 200) {
                    $this->climate->yellow("Failed to fetch next XML page: HTTP Status {$statusCode}");
                    break;
                }
                
                $content = (string) $response->getBody();
                
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
                    
                    $title = (string)($item->title ?? 'Sem título');
                    $content = (string)($item->description ?? $item->content ?? $item->summary ?? '');
                    $author = (string)($item->author ?? $item->creator ?? '');
                    $url = (string)($item->link ?? $item->url ?? '');
                    $date = (string)($item->pubDate ?? $item->published ?? $item->date ?? date('Y-m-d H:i:s'));
                    
                    $this->climate->whisper("Processing XML item from page {$currentPage}: {$title} ({$url})");
    
                    $imageUrl = $this->extractImageFromUrl($url);
                    
                    try {
                        DB::insert('feed_items', [
                            'feed_id' => $feed['id'],
                            'title' => $title,
                            'author' => $author,
                            'content' => $content,
                            'url' => $url,
                            'image_url' => $imageUrl,
                            'guid' => $guid,
                            'published_at' => $date
                        ]);
                        $count++;
                        $pageItemCount++;
                        $updated = true;
                        $this->climate->whisper("XML item from page {$currentPage} added successfully: {$title}");
                    } catch (\Exception $e) {
                        $this->climate->whisper("Error adding XML item from page {$currentPage} {$title}: {$e->getMessage()}");
                        continue;
                    }
                    
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
    private function extractImageFromUrl(string $url): ?string
    {
        if (empty($url)) {
            return null;
        }
        
        try {
            $this->climate->whisper("Extracting image from: {$url}");
            $response = $this->httpClient->get($url);
            $statusCode = $response->getStatusCode();

            sleep(1);
            
            if ($statusCode !== 200) {
                $this->climate->whisper("Failed to extract image: Status {$statusCode}");
                return null;
            }
            
            $html = (string) $response->getBody();

            if (preg_match('/<meta[^>]*property=["\']og:image["\'][^>]*content=["\'](.*?)["\'][^>]*>/i', $html, $matches)) {
                $this->climate->whisper("Image extracted (og:image): {$matches[1]}");
                return $matches[1];
            }
            

            if (preg_match('/<meta[^>]*content=["\'](.*?)["\'][^>]*property=["\']og:image["\'][^>]*>/i', $html, $matches)) {
                $this->climate->whisper("Image extracted (og:image alt): {$matches[1]}");
                return $matches[1];
            }
            
            $this->climate->whisper("No image found");
            return null;
        } catch (\Exception $e) {
            $this->climate->whisper("Error extracting image: {$e->getMessage()}");
            return null;
        }
    }
    
    
}
