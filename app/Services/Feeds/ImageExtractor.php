<?php

namespace App\Services\Feeds;

use App\Services\ProxyService;
use App\Support\HttpClient;
use GuzzleHttp\Client;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Extracts OpenGraph images for items with no image_url. image_fetched_at is
 * stamped BEFORE the attempt, so failures are never retried.
 */
class ImageExtractor
{
    /** @var null|callable(string): void */
    private $out;

    public function __construct(
        private readonly ProxyService $proxyService,
        private readonly int $batchSize = 50,
        ?callable $out = null,
    ) {
        $this->out = $out;
    }

    private function say(string $message): void
    {
        if ($this->out !== null) {
            ($this->out)($message);
        }
    }

    public function run(?int $limit = null): array
    {
        $processed = 0;
        $success = 0;
        $failed = 0;

        do {
            $items = $this->fetchPendingItems($this->batchSize);
            $batchCount = $items->count();

            if ($batchCount === 0) {
                break;
            }

            foreach ($items as $item) {
                $processed++;
                $itemId = (int) $item->id;

                DB::table('feed_items')->where('id', $itemId)->update(['image_fetched_at' => now()]);

                $imageUrl = $this->extractImageFromUrl($item->url);
                if ($imageUrl) {
                    DB::table('feed_items')->where('id', $itemId)->update(['image_url' => $imageUrl]);
                    $success++;
                } else {
                    $failed++;
                }

                if ($limit !== null && $processed >= $limit) {
                    break 2;
                }
            }

            unset($items);
            gc_collect_cycles();
        } while ($batchCount === $this->batchSize);

        if ($success > 0) {
            Cache::flush();
        }

        $this->say("✓ Image extraction complete — processed: {$processed}, successful: {$success}, failed: {$failed}");

        return ['processed' => $processed, 'success' => $success, 'failed' => $failed];
    }

    private function fetchPendingItems(int $limit): Collection
    {
        return DB::table('feed_items as fi')
            ->join('feeds as f', 'fi.feed_id', '=', 'f.id')
            ->whereNull('fi.image_url')
            ->whereNull('fi.image_fetched_at')
            ->orderByDesc('fi.id')
            ->limit($limit)
            ->get(['fi.id', 'fi.url', 'f.proxy_only', 'f.retry_proxy']);
    }

    private function extractImageFromUrl(string $url): ?string
    {
        if (empty($url)) {
            return null;
        }

        $attempts = $this->proxyService->buildAttemptConfigs(HttpClient::imageConfig());

        foreach ($attempts as $attempt) {
            try {
                $client = new Client($attempt['config']);

                return $this->fetchImageUrl($client, $url);
            } catch (\Throwable $e) {
                $this->say("Image extraction via {$attempt['label']} failed for {$url}: {$e->getMessage()}");
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
        $baseUrl = ($parsedUrl['scheme'] ?? 'https').'://'.($parsedUrl['host'] ?? '');

        $imageUrl = $this->matchOgImage($html);
        if ($imageUrl === null) {
            return null;
        }

        if (str_starts_with($imageUrl, '/')) {
            $imageUrl = $baseUrl.$imageUrl;
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
