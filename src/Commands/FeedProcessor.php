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
                'User-Agent' => 'Meta-ExternalFetcher'
            ]
        ]);
    }

    public function process(?int $feedId = null): void
    {
        if ($feedId) {
            $this->climate->info("Processing feed ID: {$feedId}");
            $feeds = DB::query("SELECT * FROM feeds WHERE id = %i AND status = 'online'", $feedId);
        } else {
            $this->climate->info("Processing all online feeds");
            $feeds = DB::query("SELECT * FROM feeds WHERE status = 'online'");
        }

        if (empty($feeds)) {
            $this->climate->warning("No feeds found to process");
            return;
        }

        foreach ($feeds as $feed) {
            $this->climate->out("Processing: {$feed['title']} ({$feed['feed_url']})");
            
            try {
                $this->processFeed($feed);
                DB::update('feeds', [
                    'last_checked' => DB::sqleval("NOW()"),
                    'status' => 'online'
                ], 'id=%i', $feed['id']);
                
                $this->climate->green("✓ Successfully processed feed: {$feed['title']}");
            } catch (\Exception $e) {
                $this->climate->red("✗ Error processing feed {$feed['title']}: {$e->getMessage()}");
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
        $maxItemsToProcess = 100; // Limite para evitar processamento excessivo

        // Processar os itens da primeira página
        foreach ($items as $item) {
            $guid = $item->get_id();
            
            // Skip if we've already processed this item
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
            
            // Extrair a URL da imagem do og:image
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
                // Item might already exist, continue to next item
                continue;
            }
            
            $processedItems++;
            if ($processedItems >= $maxItemsToProcess) {
                break;
            }
        }

        // Verificar se há paginação e processar páginas adicionais
        $this->processPaginatedRssFeed($feed, $simplePie, $count, $updated, $lastGuid, $processedItems, $maxItemsToProcess);

        if ($updated && $lastGuid) {
            // Update the last post ID to the most recent item
            DB::update('feeds', [
                'last_post_id' => $lastGuid,
                'last_updated' => DB::sqleval("NOW()")
            ], 'id=%i', $feed['id']);
        }

        $this->climate->out("Added {$count} new items from feed: {$feed['title']}");
    }

    private function processPaginatedRssFeed(array $feed, SimplePie $simplePie, &$count, &$updated, &$lastGuid, &$processedItems, $maxItemsToProcess): void
    {
        // Verificar se o feed tem links de paginação
        $links = $simplePie->get_links();
        $nextPageUrl = null;
        
        // Procurar por links de próxima página
        if ($links) {
            foreach ($links as $link) {
                if (isset($link['rel']) && ($link['rel'] === 'next' || $link['rel'] === 'self' && strpos($link['href'], 'page=') !== false)) {
                    $nextPageUrl = $link['href'];
                    break;
                }
            }
        }
        
        // Se não encontrou link explícito, tentar inferir do URL atual
        if (!$nextPageUrl && strpos($feed['feed_url'], 'page=') !== false) {
            $urlParts = parse_url($feed['feed_url']);
            parse_str($urlParts['query'] ?? '', $queryParams);
            
            if (isset($queryParams['page'])) {
                $queryParams['page'] = (int)$queryParams['page'] + 1;
                $urlParts['query'] = http_build_query($queryParams);
                
                $nextPageUrl = $this->buildUrl($urlParts);
            }
        }
        
        // Processar até 5 páginas adicionais ou até atingir o limite de itens
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
                    
                    // Skip if we've already processed this item
                    if ($feed['last_post_id'] === $guid) {
                        break 2; // Break out of both loops
                    }
                    
                    if ($lastGuid === null) {
                        $lastGuid = $guid;
                    }
                    
                    $title = $item->get_title();
                    $content = $item->get_content();
                    $author = $item->get_author() ? $item->get_author()->get_name() : null;
                    $url = $item->get_permalink();
                    $date = $item->get_date('Y-m-d H:i:s') ?: date('Y-m-d H:i:s');
                    
                    // Extrair a URL da imagem do og:image
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
                        // Item might already exist, continue to next item
                        continue;
                    }
                    
                    $processedItems++;
                    if ($processedItems >= $maxItemsToProcess) {
                        break 2; // Break out of both loops
                    }
                }
                
                // Procurar pelo próximo link de paginação
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
                
                // Se não encontrou link explícito, tentar inferir do URL atual
                if (!$nextPageUrl) {
                    break;
                }
                
                $currentPage++;
                
            } catch (\Exception $e) {
                $this->climate->yellow("Error processing next page: {$e->getMessage()}");
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
                throw new \Exception("Failed to fetch CSV feed: HTTP status {$statusCode}");
            }
            
            $content = (string) $response->getBody();
        } catch (\Exception $e) {
            throw new \Exception("Failed to fetch CSV feed: " . $e->getMessage());
        }

        $lines = explode("\n", $content);
        $headers = str_getcsv(array_shift($lines));
        
        // Find the column indexes for required fields
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
            if (count($data) <= $guidIndex) continue; // Skip malformed lines
            
            $guid = $data[$guidIndex];
            
            // Skip if we've already processed this item
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
                // Item might already exist, continue to next item
                $this->climate->whisper("Erro ao adicionar item {$title}: {$e->getMessage()}");
                continue;
            }
        }
        
        // Verificar se há paginação no CSV (geralmente via parâmetro na URL)
        $this->processPaginatedCsvFeed($feed, $count, $updated, $lastGuid);
        
        if ($updated && $lastGuid) {
            // Update the last post ID to the most recent item
            DB::update('feeds', [
                'last_post_id' => $lastGuid,
                'last_updated' => DB::sqleval("NOW()")
            ], 'id=%i', $feed['id']);
        }
        
        $this->climate->out("Added {$count} new items from CSV feed: {$feed['title']}");
    }

    private function processPaginatedCsvFeed(array $feed, &$count, &$updated, &$lastGuid): void
    {
        // Verificar se a URL do feed tem parâmetros de paginação
        if (strpos($feed['feed_url'], 'page=') === false && strpos($feed['feed_url'], 'offset=') === false) {
            return; // Não parece ter paginação
        }
        
        $urlParts = parse_url($feed['feed_url']);
        parse_str($urlParts['query'] ?? '', $queryParams);
        
        // Determinar o tipo de paginação
        $pageParam = null;
        $currentValue = null;
        
        if (isset($queryParams['page'])) {
            $pageParam = 'page';
            $currentValue = (int)$queryParams['page'];
            $nextValue = $currentValue + 1;
        } elseif (isset($queryParams['offset'])) {
            $pageParam = 'offset';
            $currentValue = (int)$queryParams['offset'];
            $limit = $queryParams['limit'] ?? 10; // Valor padrão se não especificado
            $nextValue = $currentValue + $limit;
        } else {
            return; // Não conseguiu determinar o parâmetro de paginação
        }
        
        // Processar até 5 páginas adicionais
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
                $this->climate->whisper("Buscando próxima página CSV: {$nextPageUrl}");
                $response = $this->httpClient->get($nextPageUrl);
                $statusCode = $response->getStatusCode();
                
                if ($statusCode !== 200) {
                    $this->climate->yellow("Failed to fetch next CSV page: HTTP status {$statusCode}");
                    break;
                }
                
                $content = (string) $response->getBody();
                
                $lines = explode("\n", $content);
                $headers = str_getcsv(array_shift($lines));
                
                // Find the column indexes for required fields
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
                    if (count($data) <= $guidIndex) continue; // Skip malformed lines
                    
                    $guid = $data[$guidIndex];
                    
                    // Skip if we've already processed this item
                    if ($feed['last_post_id'] === $guid) {
                        break 2; // Break out of both loops
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
                        // Item might already exist, continue to next item
                        $this->climate->whisper("Erro ao adicionar item {$title}: {$e->getMessage()}");
                        continue;
                    }
                    
                    $processedItems++;
                    if ($processedItems >= $maxItemsToProcess) {
                        break 2; // Break out of both loops
                    }
                }
                
                // Se não encontrou nenhum item novo nesta página, parar
                if ($pageItemCount === 0) {
                    break;
                }
                
                // Preparar para a próxima página
                if ($pageParam === 'page') {
                    $nextValue++;
                } else { // offset
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
                throw new \Exception("Failed to fetch JSON feed: HTTP status {$statusCode}");
            }
            
            $content = (string) $response->getBody();
        } catch (\Exception $e) {
            throw new \Exception("Failed to fetch JSON feed: " . $e->getMessage());
        }
        
        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("Invalid JSON feed: " . json_last_error_msg());
        }
        
        // Handle different JSON feed formats
        $items = $data['items'] ?? $data['entries'] ?? $data['feed'] ?? $data;
        
        if (!is_array($items)) {
            throw new \Exception("Could not find items in JSON feed");
        }
        // Verificar se há informações de paginação
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
                continue; // Skip items without a unique identifier
            }
            
            // Skip if we've already processed this item
            if ($feed['last_post_id'] === $guid) {
                break;
            }
            
            if ($lastGuid === null) {
                $lastGuid = $guid;
            }
            
            $title = $item['title'] ?? 'Untitled';
            $content = $item['content'] ?? $item['content_html'] ?? $item['summary'] ?? '';
            $author = $item['author']['name'] ?? $item['author'] ?? null;
            $url = $item['url'] ?? $item['link'] ?? '';
            $date = $item['date_published'] ?? $item['published'] ?? $item['date'] ?? date('Y-m-d H:i:s');
            
            $this->climate->whisper("Processando item JSON: {$title} ({$url})");
            
            // Extrair a URL da imagem do og:image
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
                // Item might already exist, continue to next item
                $this->climate->whisper("Erro ao adicionar item JSON {$title}: {$e->getMessage()}");
                continue;
            }
            
            $processedItems++;
            if ($processedItems >= $maxItemsToProcess) {
                break;
            }
        }
        
        // Processar páginas adicionais se houver
        if ($nextPageUrl) {
            $this->processPaginatedJsonFeed($feed, $nextPageUrl, $count, $updated, $lastGuid, $processedItems, $maxItemsToProcess);
        }
        
        if ($updated && $lastGuid) {
            // Update the last post ID to the most recent item
            DB::update('feeds', [
                'last_post_id' => $lastGuid,
                'last_updated' => DB::sqleval("NOW()")
            ], 'id=%i', $feed['id']);
        }
        
        $this->climate->out("Added {$count} new items from JSON feed: {$feed['title']}");
    }

    private function processPaginatedJsonFeed(array $feed, string $nextPageUrl, &$count, &$updated, &$lastGuid, &$processedItems, $maxItemsToProcess): void
    {
        // Processar até 5 páginas adicionais ou até atingir o limite de itens
        $maxPages = 5;
        $currentPage = 1;
        
        while ($nextPageUrl && $currentPage < $maxPages && $processedItems < $maxItemsToProcess) {
            $this->climate->out("Processing next JSON page: {$nextPageUrl}");
            
            try {
                $this->climate->whisper("Buscando próxima página JSON: {$nextPageUrl}");
                $response = $this->httpClient->get($nextPageUrl);
                $statusCode = $response->getStatusCode();
                
                if ($statusCode !== 200) {
                    $this->climate->yellow("Failed to fetch next JSON page: HTTP status {$statusCode}");
                    break;
                }
                
                $content = (string) $response->getBody();
                
                $data = json_decode($content, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $this->climate->yellow("Invalid JSON in next page: " . json_last_error_msg());
                    break;
                }
                
                // Handle different JSON feed formats
                $items = $data['items'] ?? $data['entries'] ?? $data['feed'] ?? $data;
                
                if (!is_array($items)) {
                    $this->climate->yellow("Could not find items in next JSON page");
                    break;
                }
                
                $pageItemCount = 0;
                
                foreach ($items as $item) {
                    $guid = $item['id'] ?? $item['guid'] ?? $item['url'] ?? null;
                    if (!$guid) {
                        continue; // Skip items without a unique identifier
                    }
                    
                    // Skip if we've already processed this item
                    if ($feed['last_post_id'] === $guid) {
                        break 2; // Break out of both loops
                    }
                    
                    if ($lastGuid === null) {
                        $lastGuid = $guid;
                    }
                    
                    $title = $item['title'] ?? 'Untitled';
                    $content = $item['content'] ?? $item['content_html'] ?? $item['summary'] ?? '';
                    $author = $item['author']['name'] ?? $item['author'] ?? null;
                    $url = $item['url'] ?? $item['link'] ?? '';
                    $date = $item['date_published'] ?? $item['published'] ?? $item['date'] ?? date('Y-m-d H:i:s');
                    
                    $this->climate->whisper("Processando item JSON da página {$currentPage}: {$title} ({$url})");
                    
                    // Extrair a URL da imagem do og:image
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
                        // Item might already exist, continue to next item
                        $this->climate->whisper("Erro ao adicionar item JSON da página {$currentPage} {$title}: {$e->getMessage()}");
                        continue;
                    }
                    
                    $processedItems++;
                    if ($processedItems >= $maxItemsToProcess) {
                        break 2; // Break out of both loops
                    }
                }
                
                // Se não encontrou nenhum item novo nesta página, parar
                if ($pageItemCount === 0) {
                    break;
                }
                
                // Verificar se há próxima página
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
        $this->climate->info("Processando feed XML: {$feed['feed_url']}");
        
        try {
            $response = $this->httpClient->get($feed['feed_url']);
            $statusCode = $response->getStatusCode();
            
            if ($statusCode !== 200) {
                throw new \Exception("Failed to fetch XML feed: HTTP status {$statusCode}");
            }
            
            $content = (string) $response->getBody();
        } catch (\Exception $e) {
            throw new \Exception("Failed to fetch XML feed: " . $e->getMessage());
        }
        
        $xml = simplexml_load_string($content);
        if ($xml === false) {
            throw new \Exception("Invalid XML feed");
        }
        
        // Try to determine the structure of the XML
        $items = $xml->xpath('//item') ?: $xml->xpath('//entry') ?: [];
        
        $count = 0;
        $updated = false;
        $lastGuid = null;
        $processedItems = 0;
        $maxItemsToProcess = 100;
        
        foreach ($items as $item) {
            $guid = (string)($item->guid ?? $item->id ?? $item->link ?? '');
            if (empty($guid)) {
                continue; // Skip items without a unique identifier
            }
            
            // Skip if we've already processed this item
            if ($feed['last_post_id'] === $guid) {
                break;
            }
            
            if ($lastGuid === null) {
                $lastGuid = $guid;
            }
            
            $title = (string)($item->title ?? 'Untitled');
            $content = (string)($item->description ?? $item->content ?? $item->summary ?? '');
            $author = (string)($item->author ?? $item->creator ?? '');
            $url = (string)($item->link ?? $item->url ?? '');
            $date = (string)($item->pubDate ?? $item->published ?? $item->date ?? date('Y-m-d H:i:s'));
            
            $this->climate->whisper("Processando item XML: {$title} ({$url})");
            
            // Extrair a URL da imagem do og:image
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
                // Item might already exist, continue to next item
                $this->climate->whisper("Erro ao adicionar item XML {$title}: {$e->getMessage()}");
                continue;
            }
            
            $processedItems++;
            if ($processedItems >= $maxItemsToProcess) {
                break;
            }
            
        }
        
        // Verificar se há paginação no XML
        $this->processPaginatedXmlFeed($feed, $xml, $count, $updated, $lastGuid, $processedItems, $maxItemsToProcess);
        
        if ($updated && $lastGuid) {
            // Update the last post ID to the most recent item
            DB::update('feeds', [
                'last_post_id' => $lastGuid,
                'last_updated' => DB::sqleval("NOW()")
            ], 'id=%i', $feed['id']);
        }
        $this->climate->out("Added {$count} new items from XML feed: {$feed['title']}");
    }
    
    private function processPaginatedXmlFeed(array $feed, \SimpleXMLElement $xml, &$count, &$updated, &$lastGuid, &$processedItems, $maxItemsToProcess): void
    {
        // Verificar se há links de paginação no XML
        $nextPageUrl = null;
        
        // Procurar por links de próxima página em diferentes formatos
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
        
        // Se não encontrou link explícito, tentar inferir do URL atual
        if (!$nextPageUrl && strpos($feed['feed_url'], 'page=') !== false) {
            $urlParts = parse_url($feed['feed_url']);
            parse_str($urlParts['query'] ?? '', $queryParams);
            
            if (isset($queryParams['page'])) {
                $queryParams['page'] = (int)$queryParams['page'] + 1;
                $urlParts['query'] = http_build_query($queryParams);
                
                $nextPageUrl = $this->buildUrl($urlParts);
            }
        }
        
        // Processar até 5 páginas adicionais ou até atingir o limite de itens
        $maxPages = 5;
        $currentPage = 1;
        
        while ($nextPageUrl && $currentPage < $maxPages && $processedItems < $maxItemsToProcess) {
            $this->climate->out("Processing next XML page: {$nextPageUrl}");
            
            try {
                $this->climate->whisper("Buscando próxima página XML: {$nextPageUrl}");
                $response = $this->httpClient->get($nextPageUrl);
                $statusCode = $response->getStatusCode();
                
                if ($statusCode !== 200) {
                    $this->climate->yellow("Failed to fetch next XML page: HTTP status {$statusCode}");
                    break;
                }
                
                $content = (string) $response->getBody();
                
                $nextXml = simplexml_load_string($content);
                if ($nextXml === false) {
                    $this->climate->yellow("Invalid XML in next page");
                    break;
                }
                
                // Try to determine the structure of the XML
                $items = $nextXml->xpath('//item') ?: $nextXml->xpath('//entry') ?: [];
                
                $pageItemCount = 0;
                
                foreach ($items as $item) {
                    $guid = (string)($item->guid ?? $item->id ?? $item->link ?? '');
                    if (empty($guid)) {
                        continue; // Skip items without a unique identifier
                    }
                    
                    // Skip if we've already processed this item
                    if ($feed['last_post_id'] === $guid) {
                        break 2; // Break out of both loops
                    }
                    
                    if ($lastGuid === null) {
                        $lastGuid = $guid;
                    }
                    
                    $title = (string)($item->title ?? 'Untitled');
                    $content = (string)($item->description ?? $item->content ?? $item->summary ?? '');
                    $author = (string)($item->author ?? $item->creator ?? '');
                    $url = (string)($item->link ?? $item->url ?? '');
                    $date = (string)($item->pubDate ?? $item->published ?? $item->date ?? date('Y-m-d H:i:s'));
                    
                    $this->climate->whisper("Processando item XML da página {$currentPage}: {$title} ({$url})");
                    
                    // Extrair a URL da imagem do og:image
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
                        // Item might already exist, continue to next item
                        $this->climate->whisper("Erro ao adicionar item XML da página {$currentPage} {$title}: {$e->getMessage()}");
                        continue;
                    }
                    
                    $processedItems++;
                    if ($processedItems >= $maxItemsToProcess) {
                        break 2; // Break out of both loops
                    }
                }
                
                // Se não encontrou nenhum item novo nesta página, parar
                if ($pageItemCount === 0) {
                    break;
                }
                
                // Procurar pelo próximo link de paginação
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
                
                // Se não encontrou link explícito, tentar inferir do URL atual
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
            $this->climate->whisper("Extraindo imagem de: {$url}");
            $response = $this->httpClient->get($url);
            $statusCode = $response->getStatusCode();
            
            // Aguardar 1 segundo após cada requisição
            sleep(1);
            
            if ($statusCode !== 200) {
                $this->climate->whisper("Falha ao extrair imagem: Status {$statusCode}");
                return null;
            }
            
            $html = (string) $response->getBody();
            
            // Extrair og:image usando expressão regular
            if (preg_match('/<meta[^>]*property=["\']og:image["\'][^>]*content=["\'](.*?)["\'][^>]*>/i', $html, $matches)) {
                $this->climate->whisper("Imagem extraída (og:image): {$matches[1]}");
                return $matches[1];
            }
            
            // Tentar extrair usando outra abordagem se a primeira falhar
            if (preg_match('/<meta[^>]*content=["\'](.*?)["\'][^>]*property=["\']og:image["\'][^>]*>/i', $html, $matches)) {
                $this->climate->whisper("Imagem extraída (og:image alt): {$matches[1]}");
                return $matches[1];
            }
            
            $this->climate->whisper("Nenhuma imagem encontrada");
            return null;
        } catch (\Exception $e) {
            // Em caso de erro, retornar null
            $this->climate->whisper("Erro ao extrair imagem: {$e->getMessage()}");
            return null;
        }
    }
    
    
}
