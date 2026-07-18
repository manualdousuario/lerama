<?php

declare(strict_types=1);

namespace Lerama\Commands;

use League\CLImate\CLImate;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Lerama\Config\HttpClientConfig;
use Lerama\Services\ProxyService;
use Lerama\Services\CacheInvalidator;
use Lerama\Services\CacheWarmer;
use DB;

/**
 * Background extractor for OpenGraph images.
 */
class ImageExtractor
{
    private CLImate $climate;
    private ProxyService $proxyService;
    private int $batchSize;

    public function __construct(CLImate $climate, int $batchSize = 50)
    {
        $this->climate = $climate;
        $this->batchSize = max(1, $batchSize);
        $this->proxyService = new ProxyService();
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

        if ($success > 0) {
            $this->climate->info("Invalidating item cache due to image updates...");
            $deleted = CacheInvalidator::invalidateItems();
            $this->climate->green("✓ Invalidated {$deleted} cache tag reference(s)");
        }

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

        $attempts = $this->proxyService->buildAttemptConfigs(HttpClientConfig::getExtractedImageConfig());

        foreach ($attempts as $attempt) {
            try {
                $client = new Client($attempt['config']);
                return $this->fetchImageUrl($client, $url);
            } catch (\Exception $e) {
                $this->climate->whisper("Image extraction via {$attempt['label']} failed for {$url}: " . $e->getMessage());
            }
        }

        return null;
    }

    private function fetchImageUrl(Client $client, string $url): ?string
    {
        $response = $client->get($url);
        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException("HTTP Status {$response->getStatusCode()}");
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
