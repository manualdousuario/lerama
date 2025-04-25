<?php
declare(strict_types=1);

namespace Lerama\Commands;

use League\CLImate\CLImate;
use SimplePie\SimplePie;
use DB;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;

class FeedProcessor
{
    private CLImate $climate;
    private \GuzzleHttp\Client $httpClient;

    public function __construct(CLImate $climate)
    {
        $this->climate = $climate;
        $this->httpClient = new \GuzzleHttp\Client([
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
        ]);
    }

    public function process(?int $feedId = null): void
    {
        if ($feedId) {
            $this->climate->info("Processando feed ID: {$feedId}");
            $feeds = DB::query("SELECT * FROM feeds WHERE id = %i AND status = 'online'", $feedId);
        } else {
            $this->climate->info("Processando todos os feeds online");
            $feeds = DB::query("SELECT * FROM feeds WHERE status = 'online'");
        }

        if (empty($feeds)) {
            $this->climate->warning("Nenhum feed encontrado para processar");
            return;
        }

        foreach ($feeds as $feed) {
            $this->climate->out("Processando: {$feed['title']} ({$feed['feed_url']})");
            
            try {
                $this->processFeed($feed);
                DB::update('feeds', [
                    'last_checked' => DB::sqleval("NOW()"),
                    'status' => 'online'
                ], 'id=%i', $feed['id']);
                
                $this->climate->green("✓ Feed processado com sucesso: {$feed['title']}");
            } catch (\Exception $e) {
                $this->climate->red("✗ Erro ao processar feed {$feed['title']}: {$e->getMessage()}");
                DB::update('feeds', [
                    'last_checked' => DB::sqleval("NOW()"),
                    'status' => 'offline'
                ], 'id=%i', $feed['id']);
            }
        }
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
                throw new \Exception("Tipo de feed não suportado: {$feedType}");
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

        $this->climate->out("Adicionados {$count} novos itens do feed: {$feed['title']}");
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
            $this->climate->out("Processando próxima página: {$nextPageUrl}");
            
            try {
                $nextSimplePie = new SimplePie();
                $nextSimplePie->set_feed_url($nextPageUrl);
                $nextSimplePie->enable_cache(false);
                $nextSimplePie->init();
                
                if ($nextSimplePie->error()) {
                    $this->climate->yellow("Erro ao carregar próxima página: {$nextSimplePie->error()}");
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
        $this->climate->info("Processando feed CSV: {$feed['feed_url']}");
        
        try {
            $response = $this->httpClient->get($feed['feed_url']);
            $statusCode = $response->getStatusCode();
            
            if ($statusCode !== 200) {
                throw new \Exception("Falha ao buscar feed CSV: Status HTTP {$statusCode}");
            }
            
            $content = (string) $response->getBody();
        } catch (\Exception $e) {
            throw new \Exception("Falha ao buscar feed CSV: " . $e->getMessage());
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
            throw new \Exception("Feed CSV sem as colunas obrigatórias (title, url, guid)");
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
            
            $this->climate->whisper("Processando item: {$title} ({$url})");
            
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
                $this->climate->whisper("Item adicionado com sucesso: {$title}");
            } catch (\Exception $e) {
                $this->climate->whisper("Erro ao adicionar item {$title}: {$e->getMessage()}");
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
        
        $this->climate->out("Adicionados {$count} novos itens do feed CSV: {$feed['title']}");
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
            
            $this->climate->out("Processando próxima página CSV: {$nextPageUrl}");
            
            try {
                $this->climate->whisper("Buscando próxima página CSV: {$nextPageUrl}");
                $response = $this->httpClient->get($nextPageUrl);
                $statusCode = $response->getStatusCode();
                
                if ($statusCode !== 200) {
                    $this->climate->yellow("Falha ao buscar próxima página CSV: Status HTTP {$statusCode}");
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
                    $this->climate->yellow("Feed CSV sem as colunas obrigatórias (title, url, guid)");
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
                    
                    $this->climate->whisper("Processando item da página {$currentPage}: {$title} ({$url})");
                    
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
                        $this->climate->whisper("Item adicionado com sucesso: {$title}");
                    } catch (\Exception $e) {
                        $this->climate->whisper("Erro ao adicionar item {$title}: {$e->getMessage()}");
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
        $this->climate->info("Processando feed JSON: {$feed['feed_url']}");
        
        try {
            $response = $this->httpClient->get($feed['feed_url']);
            $statusCode = $response->getStatusCode();
            
            if ($statusCode !== 200) {
                throw new \Exception("Falha ao buscar feed JSON: Status HTTP {$statusCode}");
            }
            
            $content = (string) $response->getBody();
        } catch (\Exception $e) {
            throw new \Exception("Falha ao buscar feed JSON: " . $e->getMessage());
        }
        
        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("Feed JSON inválido: " . json_last_error_msg());
        }

        $items = $data['items'] ?? $data['entries'] ?? $data['feed'] ?? $data;
        
        if (!is_array($items)) {
            throw new \Exception("Não foi possível encontrar itens no feed JSON");
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
            
            $this->climate->whisper("Processando item JSON: {$title} ({$url})");

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
                $this->climate->whisper("Item JSON adicionado com sucesso: {$title}");
            } catch (\Exception $e) {
                $this->climate->whisper("Erro ao adicionar item JSON {$title}: {$e->getMessage()}");
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
        
        $this->climate->out("Adicionados {$count} novos itens do feed JSON: {$feed['title']}");
    }

    private function processPaginatedJsonFeed(array $feed, string $nextPageUrl, &$count, &$updated, &$lastGuid, &$processedItems, $maxItemsToProcess): void
    {
        $maxPages = 5;
        $currentPage = 1;
        
        while ($nextPageUrl && $currentPage < $maxPages && $processedItems < $maxItemsToProcess) {
            $this->climate->out("Processando próxima página JSON: {$nextPageUrl}");
            
            try {
                $this->climate->whisper("Buscando próxima página JSON: {$nextPageUrl}");
                $response = $this->httpClient->get($nextPageUrl);
                $statusCode = $response->getStatusCode();
                
                if ($statusCode !== 200) {
                    $this->climate->yellow("Falha ao buscar próxima página JSON: Status HTTP {$statusCode}");
                    break;
                }
                
                $content = (string) $response->getBody();
                
                $data = json_decode($content, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $this->climate->yellow("JSON inválido na próxima página: " . json_last_error_msg());
                    break;
                }

                $items = $data['items'] ?? $data['entries'] ?? $data['feed'] ?? $data;
                
                if (!is_array($items)) {
                    $this->climate->yellow("Não foi possível encontrar itens na próxima página JSON");
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
                    
                    $this->climate->whisper("Processando item JSON da página {$currentPage}: {$title} ({$url})");

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
                        $this->climate->whisper("Item JSON da página {$currentPage} adicionado com sucesso: {$title}");
                    } catch (\Exception $e) {
                        $this->climate->whisper("Erro ao adicionar item JSON da página {$currentPage} {$title}: {$e->getMessage()}");
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
                $this->climate->yellow("Erro ao processar próxima página JSON: {$e->getMessage()}");
                break;
            }
        }
    }

    private function processXmlFeed(array $feed): void
    {
        $this->climate->info("Processando feed XML: {$feed['feed_url']}");
        
        try {
            $response = $this->httpClient->get($feed['feed_url']);
            $statusCode = $response->getStatusCode();
            
            if ($statusCode !== 200) {
                throw new \Exception("Falha ao buscar feed XML: Status HTTP {$statusCode}");
            }
            
            $content = (string) $response->getBody();
        } catch (\Exception $e) {
            throw new \Exception("Falha ao buscar feed XML: " . $e->getMessage());
        }
        
        $xml = simplexml_load_string($content);
        if ($xml === false) {
            throw new \Exception("Feed XML inválido");
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
            
            $this->climate->whisper("Processando item XML: {$title} ({$url})");

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
                $this->climate->whisper("Item XML adicionado com sucesso: {$title}");
            } catch (\Exception $e) {
                
                $this->climate->whisper("Erro ao adicionar item XML {$title}: {$e->getMessage()}");
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
        $this->climate->out("Adicionados {$count} novos itens do feed XML: {$feed['title']}");
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
            $this->climate->out("Processando próxima página XML: {$nextPageUrl}");
            
            try {
                $this->climate->whisper("Buscando próxima página XML: {$nextPageUrl}");
                $response = $this->httpClient->get($nextPageUrl);
                $statusCode = $response->getStatusCode();
                
                if ($statusCode !== 200) {
                    $this->climate->yellow("Falha ao buscar próxima página XML: Status HTTP {$statusCode}");
                    break;
                }
                
                $content = (string) $response->getBody();
                
                $nextXml = simplexml_load_string($content);
                if ($nextXml === false) {
                    $this->climate->yellow("XML inválido na próxima página");
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
                    
                    $this->climate->whisper("Processando item XML da página {$currentPage}: {$title} ({$url})");
    
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
                        $this->climate->whisper("Item XML da página {$currentPage} adicionado com sucesso: {$title}");
                    } catch (\Exception $e) {
                        $this->climate->whisper("Erro ao adicionar item XML da página {$currentPage} {$title}: {$e->getMessage()}");
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
                $this->climate->yellow("Erro ao processar próxima página XML: {$e->getMessage()}");
                break;
            }
        }
        $this->climate->out("Adicionados {$count} novos itens do feed XML: {$feed['title']}");
    }
    private function extractImageFromUrl(string $url): ?string
    {
        if (empty($url)) {
            return null;
        }
        
        try {
            $this->climate->whisper("Extraindo imagem de: {$url}");
            $response = $this->httpClient->get($url);
            $statusCode = $response->getStatusCode();

            sleep(1);
            
            if ($statusCode !== 200) {
                $this->climate->whisper("Falha ao extrair imagem: Status {$statusCode}");
                return null;
            }
            
            $html = (string) $response->getBody();

            if (preg_match('/<meta[^>]*property=["\']og:image["\'][^>]*content=["\'](.*?)["\'][^>]*>/i', $html, $matches)) {
                $this->climate->whisper("Imagem extraída (og:image): {$matches[1]}");
                return $matches[1];
            }
            

            if (preg_match('/<meta[^>]*content=["\'](.*?)["\'][^>]*property=["\']og:image["\'][^>]*>/i', $html, $matches)) {
                $this->climate->whisper("Imagem extraída (og:image alt): {$matches[1]}");
                return $matches[1];
            }
            
            $this->climate->whisper("Nenhuma imagem encontrada");
            return null;
        } catch (\Exception $e) {
            $this->climate->whisper("Erro ao extrair imagem: {$e->getMessage()}");
            return null;
        }
    }
    
    
}
