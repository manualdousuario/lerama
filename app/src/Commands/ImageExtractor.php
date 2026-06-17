<?php

declare(strict_types=1);

namespace Lerama\Commands;

use League\CLImate\CLImate;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Lerama\Config\HttpClientConfig;
use Lerama\Services\ProxyService;
use DB;

/**
 * Background extractor for OpenGraph images.
 */
class ImageExtractor
{
    private CLImate $climate;
    private Client $httpClient;
    private ?Client $proxyClient = null;
    private ProxyService $proxyService;
    private int $batchSize;
    private bool $usingProxy = false;

    public function __construct(CLImate $climate, int $batchSize = 50)
    {
        $this->climate = $climate;
        $this->batchSize = max(1, $batchSize);
        $this->proxyService = new ProxyService();
        $this->httpClient = new Client(HttpClientConfig::getExtractedImageConfig());
    }

    public function run(?int $limit = null): void
    {
        $processed = 0;
        $success = 0;
        $failed = 0;

        do {
            $items = $this->fetchPendingItems($this->batchSize);
            if (empty($items)) {
                break;
            }

            $batchCount = count($items);

            foreach ($items as $item) {
                $processed++;
                $this->markTried($item['id']);

                $proxyOnly = (int)($item['proxy_only'] ?? 0) === 1;
                $retryProxy = (int)($item['retry_proxy'] ?? 0) === 1;

                $imageUrl = $this->extractImageFromUrl($item['url'], $proxyOnly, $retryProxy);
                if ($imageUrl) {
                    $this->storeImage($item['id'], $imageUrl);
                    $success++;
                    $this->climate->whisper("Extracted image for item {$item['id']}: {$imageUrl}");
                } else {
                    $failed++;
                    $this->climate->whisper("No image found for item {$item['id']}: {$item['url']}");
                }

                if ($limit !== null && $processed >= $limit) {
                    break 2;
                }
            }

            unset($items);
            gc_collect_cycles();
        } while ($batchCount === $this->batchSize);

        $this->climate->green("✓ Image extraction complete");
        $this->climate->info("Processed: {$processed}");
        $this->climate->info("Successful: {$success}");
        $this->climate->info("Failed/No image: {$failed}");
    }

    private function fetchPendingItems(int $limit): array
    {
        return DB::query(
            "SELECT fi.id, fi.url, f.proxy_only, f.retry_proxy
             FROM feed_items fi
             JOIN feeds f ON fi.feed_id = f.id
             WHERE fi.image_url IS NULL
               AND fi.image_fetched_at IS NULL
             ORDER BY fi.id DESC
             LIMIT %i",
            $limit
        ) ?: [];
    }

    private function markTried(int $itemId): void
    {
        DB::update('feed_items', [
            'image_fetched_at' => DB::sqleval('NOW()')
        ], 'id=%i', $itemId);
    }

    private function storeImage(int $itemId, string $imageUrl): void
    {
        DB::update('feed_items', [
            'image_url' => $imageUrl
        ], 'id=%i', $itemId);
    }

    private function extractImageFromUrl(string $url, bool $proxyOnly, bool $retryProxy): ?string
    {
        if (empty($url)) {
            return null;
        }

        $useProxy = $proxyOnly || $retryProxy;

        if ($useProxy) {
            $this->setupProxyClient();
            if ($proxyOnly) {
                $this->climate->whisper("Using proxy (proxy_only) for image extraction: {$url}");
            } else {
                $this->climate->whisper("Using proxy (retry) for image extraction: {$url}");
            }
        } else {
            $this->httpClient = new Client(HttpClientConfig::getExtractedImageConfig());
            $this->usingProxy = false;
        }

        try {
            return $this->fetchImageUrl($url);
        } catch (GuzzleException $e) {
            $this->climate->whisper("Direct image extraction failed for {$url}: " . $e->getMessage());

            // Retry once via proxy if not already using one and proxies are available.
            if (!$this->usingProxy && $this->proxyService->getRandomProxy() !== null) {
                $this->climate->yellow("Retrying image extraction via proxy: {$url}");
                $this->setupProxyClient();

                try {
                    return $this->fetchImageUrl($url);
                } catch (\Exception $retryException) {
                    $this->climate->whisper("Proxy retry failed for {$url}: " . $retryException->getMessage());
                }
            }

            return null;
        } catch (\Exception $e) {
            $this->climate->whisper("Error extracting image from {$url}: " . $e->getMessage());
            return null;
        }
    }

    private function fetchImageUrl(string $url): ?string
    {
        $client = $this->usingProxy ? $this->proxyClient : $this->httpClient;
        if ($client === null) {
            $client = $this->httpClient;
        }

        $response = $client->get($url);
        if ($response->getStatusCode() !== 200) {
            return null;
        }

        $html = (string) $response->getBody();
        $parsedUrl = parse_url($url);
        $baseUrl = ($parsedUrl['scheme'] ?? 'https') . '://' . ($parsedUrl['host'] ?? '');

        $imageUrl = $this->matchOgImage($html);
        if ($imageUrl === null) {
            return null;
        }

        if (substr($imageUrl, 0, 1) === '/') {
            $imageUrl = $baseUrl . $imageUrl;
        }

        return $imageUrl;
    }

    private function setupProxyClient(): bool
    {
        $proxy = $this->proxyService->getRandomProxy();

        if (!$proxy) {
            $this->climate->warning("No proxy available, using direct connection for image extraction");
            $this->proxyClient = null;
            $this->usingProxy = false;
            return false;
        }

        $config = HttpClientConfig::getExtractedImageConfig();

        $proxyUrl = '';
        if ($proxy['username'] && $proxy['password']) {
            $proxyUrl = "http://{$proxy['username']}:{$proxy['password']}@{$proxy['host']}:{$proxy['port']}";
        } else {
            $proxyUrl = "http://{$proxy['host']}:{$proxy['port']}";
        }

        $config['proxy'] = $proxyUrl;
        $this->proxyClient = new Client($config);
        $this->usingProxy = true;

        $this->climate->info("Using proxy for image extraction: {$proxy['host']}:{$proxy['port']}");
        return true;
    }

    private function matchOgImage(string $html): ?string
    {
        $patterns = [
            '/<meta[^>]*property=["\']og:image["\'][^>]*content=["\'](.*?)["\'][^>]*>/i',
            '/<meta[^>]*content=["\'](.*?)["\'][^>]*property=["\']og:image["\'][^>]*>/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $matches)) {
                return trim($matches[1]);
            }
        }

        return null;
    }
}
