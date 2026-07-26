<?php

namespace App\Services\Feeds;

use App\Enums\FeedStatus;
use App\Mail\FeedOfflineMail;
use App\Models\Feed;
use App\Models\FeedItem;
use App\Services\CacheWarmer;
use App\Services\ItemCountService;
use App\Services\ProxyService;
use App\Support\HttpClient;
use App\Support\Text;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Laminas\Feed\Reader\Reader;

/**
 * Aggregator core.
 *
 * - Feeds are selected by next_fetch_at, FEED_MAX_PER_RUN per run
 * - Backoff: +3600s on error, +86400s on success/304
 * - State machine: online -> paused (error threshold) -> offline (72h paused)
 * - Conditional HTTP via ETag/Last-Modified
 * - Dedupe by cursor (last_post_id) plus INSERT IGNORE on (feed_id, guid)
 * - Pagination capped at 5 pages / 100 items per run
 * - Bulk insert bypasses events, so counters are recomputed in aggregate
 */
class FeedProcessor
{
    public const FETCH_INTERVAL_SUCCESS = 86400;

    public const FETCH_INTERVAL_NOT_MODIFIED = 86400;

    public const FETCH_INTERVAL_ERROR = 3600;

    private const MAX_ITEMS_PER_RUN = 100;

    private const MAX_PAGES_PER_RUN = 5;

    private Client $httpClient;

    private array $defaultClientConfig;

    private bool $subscriberTextShow;

    private int $maxFeedsPerRun;

    private int $errorThreshold;

    private array $itemBuffer = [];

    /** @var null|callable(string): void */
    private $out;

    public function __construct(
        private readonly ProxyService $proxyService,
        private readonly ItemCountService $counts,
        ?callable $out = null,
    ) {
        $this->out = $out;
        $this->defaultClientConfig = HttpClient::defaultConfig();
        $this->httpClient = new Client($this->defaultClientConfig);
        $this->subscriberTextShow = (bool) config('lerama.feeds.subscriber_show_post', false);
        $this->maxFeedsPerRun = (int) config('lerama.feeds.max_per_run', 3);
        $this->errorThreshold = (int) config('lerama.feeds.item_error_threshold', 5);
    }

    private function say(string $message): void
    {
        if ($this->out !== null) {
            ($this->out)($message);
        }
    }

    public function process(?int $feedId = null): void
    {
        if ($feedId) {
            $this->say("Processing feed ID: {$feedId}");
            $feeds = Feed::query()
                ->whereKey($feedId)
                ->whereIn('status', [FeedStatus::Online->value, FeedStatus::Paused->value])
                ->get();
        } else {
            $this->say("Processing online feeds (max: {$this->maxFeedsPerRun}, scheduled via next_fetch_at)");
            $feeds = Feed::dueForFetch()->limit($this->maxFeedsPerRun)->get();
        }

        if ($feeds->isEmpty()) {
            $this->say('No feeds due right now (all online feeds scheduled for later)');

            return;
        }

        $this->say("Found {$feeds->count()} feed(s) due for processing");

        foreach ($feeds as $feed) {
            $this->processSingleFeed($feed->toArray());
        }

        $this->say('Flushing cache and warming important entries...');
        Cache::flush();
        $summary = app(CacheWarmer::class)->warmImportant();
        $this->say("✓ Warmed categories ({$summary['categories']}), tags ({$summary['tags']}), feeds ({$summary['feeds_dropdown']}), home items ({$summary['home']['items_count']})");

        gc_collect_cycles();
    }

    private function processSingleFeed(array $feed): void
    {
        $proxyOnly = ($feed['proxy_only'] ?? 0) == 1;

        $this->httpClient = new Client($this->defaultClientConfig);

        // Pessimistic: if this run dies, the feed retries in an hour.
        DB::table('feeds')->where('id', $feed['id'])->update([
            'last_checked' => now(),
            'next_fetch_at' => time() + self::FETCH_INTERVAL_ERROR,
        ]);

        try {
            $this->processFeed($feed);

            $updateData = [
                'retry_count' => 0,
                'paused_at' => null,
                'next_fetch_at' => time() + self::FETCH_INTERVAL_SUCCESS,
                'status' => FeedStatus::Online->value,
            ];

            if (! $proxyOnly) {
                $updateData['retry_proxy'] = 0;
            }

            DB::table('feeds')->where('id', $feed['id'])->update($updateData);

            $this->say("✓ Feed processed successfully: {$feed['title']}");
        } catch (\Throwable $e) {
            $this->say("✗ Error processing feed {$feed['title']}: {$e->getMessage()}");

            $retryCount = ($feed['retry_count'] ?? 0) + 1;
            $isAutoManaged = in_array($feed['status'] ?? null, [FeedStatus::Online->value, FeedStatus::Paused->value], true);

            $errorData = ['retry_count' => $retryCount];

            if ($retryCount >= $this->errorThreshold) {
                $errorData['paused_at'] = now();
                if ($isAutoManaged) {
                    $errorData['status'] = FeedStatus::Paused->value;
                }
                $this->say("Feed {$feed['title']} marked as paused after {$retryCount} attempts");
            } elseif ($retryCount > 3 && ! $proxyOnly) {
                $errorData['retry_proxy'] = 1;
                if ($isAutoManaged) {
                    $errorData['status'] = FeedStatus::Online->value;
                }
                $this->say("Feed {$feed['title']} will use proxy in next attempts");
            } else {
                if ($isAutoManaged) {
                    $errorData['status'] = FeedStatus::Online->value;
                }
            }

            DB::table('feeds')->where('id', $feed['id'])->update($errorData);
        }
    }

    public function checkPausedFeeds(): void
    {
        $this->say('Checking paused feeds...');

        $pausedFeeds = Feed::paused()->get();

        if ($pausedFeeds->isEmpty()) {
            $this->say('No paused feeds found');

            return;
        }

        foreach ($pausedFeeds as $feedModel) {
            $feed = $feedModel->toArray();
            $pausedAt = $feedModel->paused_at?->timestamp ?? time();
            $hoursSincePaused = (time() - $pausedAt) / 3600;
            $proxyOnly = ($feed['proxy_only'] ?? 0) == 1;

            $this->say("Feed {$feed['title']} has been paused for ".round($hoursSincePaused, 1).' hours');

            if ($hoursSincePaused >= 24) {
                try {
                    $this->httpClient = new Client($this->defaultClientConfig);
                    $this->processFeed($feed);

                    $feedTitle = $this->extractFeedTitle($feed);

                    $updateData = [
                        'title' => $feedTitle ?: $feed['title'],
                        'last_checked' => now(),
                        'status' => FeedStatus::Online->value,
                        'retry_count' => 0,
                        'paused_at' => null,
                    ];

                    if (! $proxyOnly) {
                        $updateData['retry_proxy'] = 0;
                    }

                    DB::table('feeds')->where('id', $feed['id'])->update($updateData);

                    $this->say("✓ Feed {$feed['title']} is working again");
                } catch (\Throwable $e) {
                    if ($hoursSincePaused >= 72) {
                        DB::table('feeds')->where('id', $feed['id'])->update([
                            'last_checked' => now(),
                            'status' => FeedStatus::Offline->value,
                        ]);

                        $this->notifyOffline($feedModel->fresh());
                        $this->say("✗ Feed {$feed['title']} marked offline after 72 hours paused");
                    } else {
                        DB::table('feeds')->where('id', $feed['id'])->update(['last_checked' => now()]);
                        $this->say("! Feed {$feed['title']} remains inaccessible after 24 hours paused");
                    }
                }
            }
        }

        Cache::flush();
        app(CacheWarmer::class)->warmImportant();
        gc_collect_cycles();
    }

    private function notifyOffline(Feed $feed): void
    {
        $adminEmail = (string) config('lerama.admin.email', '');
        $smtpHost = (string) config('mail.mailers.smtp.host', '');

        if ($adminEmail === '' || $smtpHost === '') {
            Log::info("Feed marked offline (no e-mail sent): {$feed->title}");

            return;
        }

        try {
            Mail::to($adminEmail)->send(new FeedOfflineMail($feed));
        } catch (\Throwable $e) {
            Log::error('Failed to send feed offline notification: '.$e->getMessage());
        }
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
                    DB::table('feeds')->where('id', $feed['id'])->update([
                        'etag' => $newEtag,
                        'last_modified' => $newLastModified,
                    ]);
                }
            }

            return false;
        } catch (\Throwable $e) {
            $this->say("Conditional request failed for {$feed['title']}: {$e->getMessage()}");

            return false;
        }
    }

    private function processFeed(array $feed): void
    {
        if ($this->isNotModified($feed)) {
            $this->say("Feed not modified (304): {$feed['title']}");
            DB::table('feeds')->where('id', $feed['id'])->update([
                'next_fetch_at' => time() + self::FETCH_INTERVAL_NOT_MODIFIED,
            ]);

            return;
        }

        match ($feed['feed_type']) {
            'rss1', 'rss2', 'atom', 'rdf' => $this->processRssFeed($feed),
            'csv' => $this->processCsvFeed($feed),
            'json' => $this->processJsonFeed($feed),
            'xml' => $this->processXmlFeed($feed),
            default => throw new \RuntimeException("Unsupported feed type: {$feed['feed_type']}"),
        };
    }

    private function processRssFeed(array $feed): void
    {
        $feedContent = $this->fetchFeedContent($feed['feed_url']);
        $reader = Reader::importString($feedContent);

        $count = 0;
        $updated = false;
        $lastGuid = null;
        $processedItems = 0;

        foreach ($reader as $entry) {
            $guid = $entry->getId();

            if ($feed['last_post_id'] === $guid) {
                break;
            }

            $lastGuid ??= $guid;

            $this->bufferItem($this->rssEntryToItem($entry, $feed));
            $count++;
            $updated = true;

            $processedItems++;
            if ($processedItems >= self::MAX_ITEMS_PER_RUN) {
                break;
            }
        }

        $this->processPaginatedRssFeed($feed, $feedContent, $count, $updated, $lastGuid, $processedItems);

        $this->flushAndRecount((int) $feed['id'], $feed);

        if ($updated && $lastGuid) {
            DB::table('feeds')->where('id', $feed['id'])->update([
                'last_feed_item_id' => $this->findItemIdByGuid((int) $feed['id'], $lastGuid),
                'last_post_id' => $lastGuid,
                'last_updated' => now(),
            ]);
        }

        $this->say("Added {$count} new items from feed: {$feed['title']}");
        unset($reader);
    }

    private function rssEntryToItem($entry, array $feed): array
    {
        $author = null;
        foreach ($entry->getAuthors() ?? [] as $authorData) {
            $author = $authorData['name'] ?? null;
            break;
        }

        $content = $entry->getContent();
        $url = $entry->getLink();
        $dateObj = $entry->getDateCreated() ?? $entry->getDateModified();

        $isVisible = $this->subscriberTextShow ? true : ($this->checkRealContent($content, $url)['status'] === 'visible');

        return [
            'feed_id' => $feed['id'],
            'title' => $this->resolveTitle($entry->getTitle(), $feed),
            'author' => Text::plain($author),
            'content' => $content,
            'url' => $url,
            'image_url' => null,
            'guid' => $entry->getId(),
            'published_at' => $dateObj ? $dateObj->format('Y-m-d H:i:s') : date('Y-m-d H:i:s'),
            'is_visible' => $isVisible ? 1 : 0,
        ];
    }

    private function processPaginatedRssFeed(array $feed, string $feedContent, int &$count, bool &$updated, ?string &$lastGuid, int &$processedItems): void
    {
        $nextPageUrl = $this->extractNextLink($feedContent, $feed['feed_url']);
        $currentPage = 1;

        while ($nextPageUrl && $currentPage < self::MAX_PAGES_PER_RUN && $processedItems < self::MAX_ITEMS_PER_RUN) {
            $this->say("Processing next page: {$nextPageUrl}");

            try {
                $nextContent = $this->fetchFeedContent($nextPageUrl);
                $nextReader = Reader::importString($nextContent);

                foreach ($nextReader as $entry) {
                    $guid = $entry->getId();

                    if ($feed['last_post_id'] === $guid) {
                        break 2;
                    }

                    $lastGuid ??= $guid;

                    $this->bufferItem($this->rssEntryToItem($entry, $feed));
                    $count++;
                    $updated = true;

                    $processedItems++;
                    if ($processedItems >= self::MAX_ITEMS_PER_RUN) {
                        break 2;
                    }
                }

                $nextPageUrl = $this->extractNextLink($nextContent, $nextPageUrl);
                if (! $nextPageUrl) {
                    break;
                }

                $currentPage++;
                unset($nextReader, $nextContent);
            } catch (\Throwable $e) {
                $this->say("Error processing next page: {$e->getMessage()}");
                break;
            }
        }
    }

    private function fetchFeedContent(string $url): string
    {
        $attempts = $this->proxyService->buildAttemptConfigs($this->defaultClientConfig);
        $lastMessage = '';

        foreach ($attempts as $attempt) {
            try {
                $client = new Client($attempt['config']);
                $response = $client->get($url);
                $statusCode = $response->getStatusCode();
                $body = (string) $response->getBody();

                if ($statusCode === 200 && ! $this->isCdnBlocked($statusCode, $body, $response)) {
                    return $body;
                }

                $lastMessage = $this->isCdnBlocked($statusCode, $body, $response)
                    ? "CDN block (Cloudflare) (HTTP {$statusCode})"
                    : "HTTP Status {$statusCode}";
            } catch (\Throwable $e) {
                $lastMessage = $e->getMessage();
            }

            $this->say("Feed fetch via {$attempt['label']} failed for {$url}: {$lastMessage}");
        }

        throw new \RuntimeException("Failed to fetch feed {$url} after all attempts: {$lastMessage}");
    }

    // Detects CDN/anti-bot blocks: Cloudflare challenges, captchas, rate limits.
    private function isCdnBlocked(int $status, string $body, $response = null): bool
    {
        if (in_array($status, [403, 429, 503], true)) {
            return true;
        }

        if ($response !== null && stripos($response->getHeaderLine('cf-mitigated'), 'challenge') !== false) {
            return true;
        }

        $sample = substr($body, 0, 4096);
        $markers = [
            'Just a moment', 'Attention Required', 'cf-browser-verification',
            'Checking your browser', 'challenge-platform', '_cf_chl', 'cf_chl_opt',
            'Cloudflare Ray ID', 'DDoS protection by', 'enable JavaScript and cookies',
            'Please enable Cookies', 'cf-turnstile', 'hcaptcha',
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
        $dom = new \DOMDocument;
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

        if (str_contains($feedUrl, 'page=')) {
            $urlParts = parse_url($feedUrl);
            parse_str($urlParts['query'] ?? '', $queryParams);
            if (isset($queryParams['page'])) {
                $queryParams['page'] = (int) $queryParams['page'] + 1;
                $urlParts['query'] = http_build_query($queryParams);

                return $this->buildUrl($urlParts);
            }
        }

        return null;
    }

    private function buildUrl(array $parts): string
    {
        $url = ($parts['scheme'] ?? '').'://';

        if (isset($parts['user'])) {
            $url .= $parts['user'];
        }
        if (isset($parts['pass'])) {
            $url .= ':'.$parts['pass'];
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            $url .= '@';
        }

        $url .= $parts['host'] ?? '';

        if (isset($parts['port'])) {
            $url .= ':'.$parts['port'];
        }

        return $url
            .($parts['path'] ?? '')
            .(isset($parts['query']) ? '?'.$parts['query'] : '')
            .(isset($parts['fragment']) ? '#'.$parts['fragment'] : '');
    }

    private function processCsvFeed(array $feed): void
    {
        $content = $this->fetchFeedContent($feed['feed_url']);
        $lines = explode("\n", $content);
        unset($content);

        $headers = str_getcsv(array_shift($lines));
        [$titleIndex, $authorIndex, $contentIndex, $urlIndex, $guidIndex, $dateIndex] = $this->csvColumnIndexes($headers);

        if ($titleIndex === false || $urlIndex === false || $guidIndex === false) {
            throw new \RuntimeException('CSV feed missing required columns (title, url, guid)');
        }

        $count = 0;
        $updated = false;
        $lastGuid = null;

        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }

            $data = str_getcsv($line);
            if (count($data) <= $guidIndex) {
                continue;
            }

            $guid = $data[$guidIndex];

            if ($feed['last_post_id'] === $guid) {
                break;
            }

            $lastGuid ??= $guid;

            $itemContent = $contentIndex !== false && isset($data[$contentIndex]) ? $data[$contentIndex] : null;
            $isVisible = $this->subscriberTextShow ? true : ($this->checkRealContent($itemContent, $data[$urlIndex])['status'] === 'visible');

            $this->bufferItem([
                'feed_id' => $feed['id'],
                'title' => $this->resolveTitle($data[$titleIndex] ?? null, $feed),
                'author' => Text::plain($authorIndex !== false && isset($data[$authorIndex]) ? $data[$authorIndex] : null),
                'content' => $itemContent,
                'url' => $data[$urlIndex],
                'image_url' => null,
                'guid' => $guid,
                'published_at' => $dateIndex !== false && isset($data[$dateIndex]) ? $data[$dateIndex] : date('Y-m-d H:i:s'),
                'is_visible' => $isVisible ? 1 : 0,
            ]);
            $count++;
            $updated = true;
            unset($data);
        }

        $this->processPaginatedCsvFeed($feed, $count, $updated, $lastGuid);

        $this->flushAndRecount((int) $feed['id'], $feed);

        if ($updated && $lastGuid) {
            DB::table('feeds')->where('id', $feed['id'])->update([
                'last_feed_item_id' => $this->findItemIdByGuid((int) $feed['id'], $lastGuid),
                'last_post_id' => $lastGuid,
                'last_updated' => now(),
            ]);
        }

        $this->say("Added {$count} new items from CSV feed: {$feed['title']}");
        unset($lines);
    }

    private function csvColumnIndexes(array $headers): array
    {
        return [
            array_search('title', $headers),
            array_search('author', $headers),
            array_search('content', $headers),
            array_search('url', $headers),
            array_search('guid', $headers),
            array_search('date', $headers),
        ];
    }

    private function processPaginatedCsvFeed(array $feed, int &$count, bool &$updated, ?string &$lastGuid): void
    {
        if (! str_contains($feed['feed_url'], 'page=') && ! str_contains($feed['feed_url'], 'offset=')) {
            return;
        }

        $urlParts = parse_url($feed['feed_url']);
        parse_str($urlParts['query'] ?? '', $queryParams);

        if (isset($queryParams['page'])) {
            $pageParam = 'page';
            $nextValue = (int) $queryParams['page'] + 1;
        } elseif (isset($queryParams['offset'])) {
            $pageParam = 'offset';
            $nextValue = (int) $queryParams['offset'] + (int) ($queryParams['limit'] ?? 10);
        } else {
            return;
        }

        $currentPage = 1;
        $processedItems = $count;

        while ($currentPage < self::MAX_PAGES_PER_RUN && $processedItems < self::MAX_ITEMS_PER_RUN) {
            $queryParams[$pageParam] = $nextValue;
            $urlParts['query'] = http_build_query($queryParams);
            $nextPageUrl = $this->buildUrl($urlParts);

            $this->say("Processing next CSV page: {$nextPageUrl}");

            try {
                $content = $this->fetchFeedContent($nextPageUrl);
                $lines = explode("\n", $content);
                unset($content);

                $headers = str_getcsv(array_shift($lines));
                [$titleIndex, $authorIndex, $contentIndex, $urlIndex, $guidIndex, $dateIndex] = $this->csvColumnIndexes($headers);

                if ($titleIndex === false || $urlIndex === false || $guidIndex === false) {
                    break;
                }

                $pageItemCount = 0;

                foreach ($lines as $line) {
                    if (trim($line) === '') {
                        continue;
                    }

                    $data = str_getcsv($line);
                    if (count($data) <= $guidIndex) {
                        continue;
                    }

                    $guid = $data[$guidIndex];

                    if ($feed['last_post_id'] === $guid) {
                        break 2;
                    }

                    $lastGuid ??= $guid;

                    $itemContent = $contentIndex !== false && isset($data[$contentIndex]) ? $data[$contentIndex] : null;
                    $isVisible = $this->subscriberTextShow ? true : ($this->checkRealContent($itemContent, $data[$urlIndex])['status'] === 'visible');

                    $this->bufferItem([
                        'feed_id' => $feed['id'],
                        'title' => $this->resolveTitle($data[$titleIndex] ?? null, $feed),
                        'author' => Text::plain($authorIndex !== false && isset($data[$authorIndex]) ? $data[$authorIndex] : null),
                        'content' => $itemContent,
                        'url' => $data[$urlIndex],
                        'image_url' => null,
                        'guid' => $guid,
                        'published_at' => $dateIndex !== false && isset($data[$dateIndex]) ? $data[$dateIndex] : date('Y-m-d H:i:s'),
                        'is_visible' => $isVisible ? 1 : 0,
                    ]);
                    $count++;
                    $pageItemCount++;
                    $updated = true;

                    $processedItems++;
                    if ($processedItems >= self::MAX_ITEMS_PER_RUN) {
                        break 2;
                    }
                }

                if ($pageItemCount === 0) {
                    break;
                }

                $nextValue = $pageParam === 'page' ? $nextValue + 1 : $nextValue + (int) ($queryParams['limit'] ?? 10);
                $currentPage++;
            } catch (\Throwable $e) {
                $this->say("Error processing next CSV page: {$e->getMessage()}");
                break;
            }
        }
    }

    private function processJsonFeed(array $feed): void
    {
        $content = $this->fetchFeedContent($feed['feed_url']);
        $data = json_decode($content, true);
        unset($content);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Invalid JSON feed: '.json_last_error_msg());
        }

        [$items, $nextPageUrl] = $this->jsonItemsAndNext($data);
        unset($data);

        $count = 0;
        $updated = false;
        $lastGuid = null;
        $processedItems = 0;

        foreach ($items as $item) {
            $guid = $item['id'] ?? $item['guid'] ?? $item['url'] ?? null;
            if (! $guid) {
                continue;
            }

            if ($feed['last_post_id'] === $guid) {
                break;
            }

            $lastGuid ??= $guid;

            $this->bufferItem($this->jsonEntryToItem($item, $feed));
            $count++;
            $updated = true;

            $processedItems++;
            if ($processedItems >= self::MAX_ITEMS_PER_RUN) {
                break;
            }
        }

        if ($nextPageUrl) {
            $this->processPaginatedJsonFeed($feed, $nextPageUrl, $count, $updated, $lastGuid, $processedItems);
        }

        $this->flushAndRecount((int) $feed['id'], $feed);

        if ($updated && $lastGuid) {
            DB::table('feeds')->where('id', $feed['id'])->update([
                'last_feed_item_id' => $this->findItemIdByGuid((int) $feed['id'], $lastGuid),
                'last_post_id' => $lastGuid,
                'last_updated' => now(),
            ]);
        }

        $this->say("Added {$count} new items from JSON feed: {$feed['title']}");
        unset($items);
    }

    private function jsonItemsAndNext(array $data): array
    {
        $items = $data['items'] ?? $data['entries'] ?? $data['feed'] ?? $data;

        if (! is_array($items)) {
            throw new \RuntimeException('Could not find items in JSON feed');
        }

        return [$items, $data['next'] ?? $data['next_page'] ?? $data['nextPage'] ?? null];
    }

    private function jsonEntryToItem(array $item, array $feed): array
    {
        $content = $item['content'] ?? $item['content_html'] ?? $item['summary'] ?? '';
        $url = $item['url'] ?? $item['link'] ?? '';

        $isVisible = $this->subscriberTextShow ? true : ($this->checkRealContent($content, $url)['status'] === 'visible');

        return [
            'feed_id' => $feed['id'],
            'title' => $this->resolveTitle($item['title'] ?? null, $feed),
            'author' => Text::plain($item['author']['name'] ?? $item['author'] ?? null),
            'content' => $content,
            'url' => $url,
            'image_url' => null,
            'guid' => $item['id'] ?? $item['guid'] ?? $item['url'],
            'published_at' => $item['date_published'] ?? $item['published'] ?? $item['date'] ?? date('Y-m-d H:i:s'),
            'is_visible' => $isVisible ? 1 : 0,
        ];
    }

    private function processPaginatedJsonFeed(array $feed, string $nextPageUrl, int &$count, bool &$updated, ?string &$lastGuid, int &$processedItems): void
    {
        $currentPage = 1;

        while ($nextPageUrl && $currentPage < self::MAX_PAGES_PER_RUN && $processedItems < self::MAX_ITEMS_PER_RUN) {
            $this->say("Processing next JSON page: {$nextPageUrl}");

            try {
                $content = $this->fetchFeedContent($nextPageUrl);
                $data = json_decode($content, true);
                unset($content);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    break;
                }

                [$items, $nextPageUrl] = $this->jsonItemsAndNext($data);
                unset($data);

                $pageItemCount = 0;

                foreach ($items as $item) {
                    $guid = $item['id'] ?? $item['guid'] ?? $item['url'] ?? null;
                    if (! $guid) {
                        continue;
                    }

                    if ($feed['last_post_id'] === $guid) {
                        break 2;
                    }

                    $lastGuid ??= $guid;

                    $this->bufferItem($this->jsonEntryToItem($item, $feed));
                    $count++;
                    $pageItemCount++;
                    $updated = true;

                    $processedItems++;
                    if ($processedItems >= self::MAX_ITEMS_PER_RUN) {
                        break 2;
                    }
                }

                if ($pageItemCount === 0 || ! $nextPageUrl) {
                    break;
                }

                $currentPage++;
            } catch (\Throwable $e) {
                $this->say("Error processing next JSON page: {$e->getMessage()}");
                break;
            }
        }
    }

    private function processXmlFeed(array $feed): void
    {
        $content = $this->fetchFeedContent($feed['feed_url']);
        $xml = simplexml_load_string($content);
        unset($content);

        if ($xml === false) {
            throw new \RuntimeException('Invalid XML feed');
        }

        $items = $xml->xpath('//item') ?: $xml->xpath('//entry') ?: [];

        $count = 0;
        $updated = false;
        $lastGuid = null;
        $processedItems = 0;

        foreach ($items as $item) {
            $guid = (string) ($item->guid ?? $item->id ?? $item->link ?? '');
            if ($guid === '') {
                continue;
            }

            if ($feed['last_post_id'] === $guid) {
                break;
            }

            $lastGuid ??= $guid;

            $this->bufferItem($this->xmlEntryToItem($item, $feed));
            $count++;
            $updated = true;

            $processedItems++;
            if ($processedItems >= self::MAX_ITEMS_PER_RUN) {
                break;
            }
        }

        $this->processPaginatedXmlFeed($feed, $xml, $count, $updated, $lastGuid, $processedItems);

        $this->flushAndRecount((int) $feed['id'], $feed);

        if ($updated && $lastGuid) {
            DB::table('feeds')->where('id', $feed['id'])->update([
                'last_feed_item_id' => $this->findItemIdByGuid((int) $feed['id'], $lastGuid),
                'last_post_id' => $lastGuid,
                'last_updated' => now(),
            ]);
        }

        $this->say("Added {$count} new items from XML feed: {$feed['title']}");
        unset($items, $xml);
    }

    private function xmlEntryToItem(\SimpleXMLElement $item, array $feed): array
    {
        $content = (string) ($item->description ?? $item->content ?? $item->summary ?? '');
        $url = (string) ($item->link ?? $item->url ?? '');

        $isVisible = $this->subscriberTextShow ? true : ($this->checkRealContent($content, $url)['status'] === 'visible');

        return [
            'feed_id' => $feed['id'],
            'title' => $this->resolveTitle((string) ($item->title ?? ''), $feed),
            'author' => Text::plain((string) ($item->author ?? $item->creator ?? '')),
            'content' => $content,
            'url' => $url,
            'image_url' => null,
            'guid' => (string) ($item->guid ?? $item->id ?? $item->link),
            'published_at' => (string) ($item->pubDate ?? $item->published ?? $item->date ?? date('Y-m-d H:i:s')),
            'is_visible' => $isVisible ? 1 : 0,
        ];
    }

    private function processPaginatedXmlFeed(array $feed, \SimpleXMLElement $xml, int &$count, bool &$updated, ?string &$lastGuid, int &$processedItems): void
    {
        $nextPageUrl = $this->xmlNextLink($xml);

        if (! $nextPageUrl && str_contains($feed['feed_url'], 'page=')) {
            $urlParts = parse_url($feed['feed_url']);
            parse_str($urlParts['query'] ?? '', $queryParams);

            if (isset($queryParams['page'])) {
                $queryParams['page'] = (int) $queryParams['page'] + 1;
                $urlParts['query'] = http_build_query($queryParams);
                $nextPageUrl = $this->buildUrl($urlParts);
            }
        }

        $currentPage = 1;

        while ($nextPageUrl && $currentPage < self::MAX_PAGES_PER_RUN && $processedItems < self::MAX_ITEMS_PER_RUN) {
            $this->say("Processing next XML page: {$nextPageUrl}");

            try {
                $content = $this->fetchFeedContent($nextPageUrl);
                $nextXml = simplexml_load_string($content);
                unset($content);

                if ($nextXml === false) {
                    break;
                }

                $items = $nextXml->xpath('//item') ?: $nextXml->xpath('//entry') ?: [];
                $pageItemCount = 0;

                foreach ($items as $item) {
                    $guid = (string) ($item->guid ?? $item->id ?? $item->link ?? '');
                    if ($guid === '') {
                        continue;
                    }

                    if ($feed['last_post_id'] === $guid) {
                        break 2;
                    }

                    $lastGuid ??= $guid;

                    $this->bufferItem($this->xmlEntryToItem($item, $feed));
                    $count++;
                    $pageItemCount++;
                    $updated = true;

                    $processedItems++;
                    if ($processedItems >= self::MAX_ITEMS_PER_RUN) {
                        break 2;
                    }
                }

                if ($pageItemCount === 0) {
                    break;
                }

                $nextPageUrl = $this->xmlNextLink($nextXml);
                if (! $nextPageUrl) {
                    break;
                }

                $currentPage++;
            } catch (\Throwable $e) {
                $this->say("Error processing next XML page: {$e->getMessage()}");
                break;
            }
        }
    }

    private function xmlNextLink(\SimpleXMLElement $xml): ?string
    {
        $links = $xml->xpath('//link[@rel="next"]') ?: $xml->xpath('//atom:link[@rel="next"]') ?: [];

        foreach ($links as $link) {
            $attributes = $link->attributes();
            if (isset($attributes['href'])) {
                return (string) $attributes['href'];
            }
        }

        return null;
    }

    private function bufferItem(array $item): void
    {
        $this->itemBuffer[] = $item;
    }

    // INSERT IGNORE in chunks of 100 without events, then recount the feed
    // and its taxonomy in aggregate.
    private function flushAndRecount(int $feedId, array $feed): int
    {
        if (empty($this->itemBuffer)) {
            return 0;
        }

        $inserted = 0;
        foreach (array_chunk($this->itemBuffer, 100) as $chunk) {
            $inserted += DB::table('feed_items')->insertOrIgnore($chunk);
        }

        $this->itemBuffer = [];

        if ($inserted > 0) {
            $this->counts->recountFeedAndTaxonomy($feedId);
        }

        return $inserted;
    }

    // Scoped to the feed: guid is not globally unique (a legacy bug).
    private function findItemIdByGuid(int $feedId, string $guid): ?int
    {
        $id = DB::table('feed_items')
            ->where('feed_id', $feedId)
            ->where('guid', $guid)
            ->orderByDesc('id')
            ->value('id');

        return $id !== null ? (int) $id : null;
    }

    private function resolveTitle(?string $title, array $feed): string
    {
        return Text::plain($title) ?? __('feed_item.no_title', [], $feed['language'] ?? 'en');
    }

    /**
     * Classifies item content: WordPress password-protected posts, Substack
     * "Read more" stubs and subscriber-only markers.
     *
     * @return array{status: string, reason: string}
     */
    public function checkRealContent(?string $content, ?string $url = ''): array
    {
        if (empty($content)) {
            return ['status' => 'visible', 'reason' => 'empty_content'];
        }

        $passwordPatterns = [
            'wp-login.php?action=postpass',
            'Este conteúdo está protegido por senha',
            'This content is password protected.',
        ];

        foreach ($passwordPatterns as $pattern) {
            if (str_contains($content, $pattern)) {
                return ['status' => 'invisible', 'reason' => 'wordpress_password_protected'];
            }
        }

        $endContent = substr(trim($content), -500);

        $readMorePatterns = [
            '/<p>\s*<a\s+href=["\']https?:\/\/[^"\']*\.?substack\.com[^"\']*["\']>\s*Read more\s*<\/a>\s*<\/p>\s*$/i',
            '/<p[^>]*>\s*<a[^>]+href=["\']https?:\/\/[^"\']*\.?substack\.com[^"\']*["\'][^>]*>\s*Read\s+more\s*<\/a>\s*<\/p>\s*$/i',
        ];

        foreach ($readMorePatterns as $pattern) {
            if (preg_match($pattern, $endContent)) {
                return ['status' => 'invisible', 'reason' => 'substack_read_more'];
            }
        }

        $subscriberIndicators = [
            '/Este (?:é um )?conteúdo exclusivo para (?:os )?assinantes/i',
            '/This is (?:a |an )?(?:exclusive )?content for (?:paid )?subscribers/i',
            '/Este(?:s)? (?:es|é) contenido exclusivo para suscriptores/i',
            '/Subscribe (?:now )?to (?:keep |continue )?reading/i',
            '/Assine (?:agora )?para (?:continuar |seguir )?lendo/i',
            '/Suscr[ií]bete (?:ahora )?para (?:seguir |continuar )?leyendo/i',
        ];

        foreach ($subscriberIndicators as $pattern) {
            if (preg_match($pattern, $content)) {
                return ['status' => 'invisible', 'reason' => 'subscriber_text'];
            }
        }

        return ['status' => 'visible', 'reason' => 'no_patterns_matched'];
    }

    public function checkItemsContent(): void
    {
        $this->say('Checking all feed items for special content...');

        $batchSize = (int) config('lerama.content_check_batch_size', 500);
        $lastId = 0;
        $markedInvisible = 0;
        $processed = 0;

        do {
            $items = DB::table('feed_items')
                ->select('id', 'url', 'content')
                ->where('is_visible', 1)
                ->where('id', '>', $lastId)
                ->orderBy('id')
                ->limit($batchSize)
                ->get();

            $batchCount = $items->count();
            if ($batchCount === 0) {
                break;
            }

            foreach ($items as $item) {
                $processed++;
                $lastId = (int) $item->id;

                $contentCheck = $this->checkRealContent($item->content, $item->url);

                if ($contentCheck['status'] === 'invisible') {
                    // Through the model so FeedItemObserver adjusts the counters.
                    $model = FeedItem::query()->find($item->id);
                    if ($model) {
                        $model->is_visible = false;
                        $model->save();
                    }
                    $markedInvisible++;
                }

                if ($processed % 100 === 0) {
                    $this->say("Progress: {$processed} items checked...");
                }
            }

            unset($items);
            gc_collect_cycles();
        } while ($batchCount === $batchSize);

        if ($markedInvisible > 0) {
            Cache::flush();
        }

        $this->say("✓ Process complete! Total: {$processed}, marked invisible: {$markedInvisible}");
    }

    private function extractFeedTitle(array $feed): ?string
    {
        try {
            if (in_array($feed['feed_type'], ['rss1', 'rss2', 'atom', 'rdf'], true)) {
                $feedContent = $this->fetchFeedContent($feed['feed_url']);
                $reader = Reader::importString($feedContent);
                $title = $reader->getTitle();
                unset($reader);

                return $title ?: null;
            }

            return null;
        } catch (\Throwable) {
            return null;
        } finally {
            gc_collect_cycles();
        }
    }
}
