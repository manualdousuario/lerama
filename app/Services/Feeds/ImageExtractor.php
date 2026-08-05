<?php

namespace App\Services\Feeds;

use App\Services\ProxyService;
use App\Support\HttpClient;
use App\Support\UrlValidator;
use GuzzleHttp\Client;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ImageExtractor
{
    private const MAX_URL_LENGTH = 512;

    private const RETRY_AFTER_HOURS = 6;

    private const RETRY_WINDOW_HOURS = 48;

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
            ->where(function ($query): void {
                $query->whereNull('fi.image_fetched_at')
                    ->orWhere(function ($query): void {
                        $query->where('fi.image_fetched_at', '<', now()->subHours(self::RETRY_AFTER_HOURS))
                            ->where('fi.created_at', '>', now()->subHours(self::RETRY_WINDOW_HOURS));
                    });
            })
            ->orderByDesc('fi.id')
            ->limit($limit)
            ->get(['fi.id', 'fi.url', 'f.proxy_only', 'f.retry_proxy']);
    }

    public function extractImageFromUrl(?string $url): ?string
    {
        if (empty($url)) {
            return null;
        }

        $attempts = $this->proxyService->buildAttemptConfigs(HttpClient::defaultConfig());

        foreach ($attempts as $attempt) {
            try {
                $client = new Client($attempt['config']);

                // A 200 with no usable og:image is a miss, not a success: fall
                // through to the next proxy (or the direct attempt) instead of
                // giving up on the first config.
                $imageUrl = $this->fetchImageUrl($client, $url);
                if ($imageUrl !== null) {
                    return $imageUrl;
                }
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

        $imageUrl = $this->matchOgImage((string) $response->getBody());
        if ($imageUrl === null) {
            return null;
        }

        $imageUrl = $this->resolveUrl($url, $imageUrl);
        if ($imageUrl === null || strlen($imageUrl) > self::MAX_URL_LENGTH) {
            return null;
        }

        if (! UrlValidator::validate($imageUrl)['valid']) {
            $this->say("Rejected og:image {$imageUrl} from {$url}");

            return null;
        }

        return $imageUrl;
    }

    private function resolveUrl(string $pageUrl, string $imageUrl): ?string
    {
        $parsed = parse_url($pageUrl);
        $scheme = $parsed['scheme'] ?? 'https';
        $host = $parsed['host'] ?? '';

        if ($host === '') {
            return null;
        }

        $authority = $host.(isset($parsed['port']) ? ':'.$parsed['port'] : '');

        if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $imageUrl)) {
            return $imageUrl;
        }

        if (str_starts_with($imageUrl, '//')) {
            return $scheme.':'.$imageUrl;
        }

        if (str_starts_with($imageUrl, '/')) {
            return $scheme.'://'.$authority.$imageUrl;
        }

        if (str_contains($imageUrl, ':')) {
            return null;
        }

        $basePath = rtrim(str_replace('\\', '/', dirname($parsed['path'] ?? '/')), '/');

        return $scheme.'://'.$authority.$basePath.'/'.$imageUrl;
    }

    private function matchOgImage(string $html): ?string
    {
        $patterns = [
            '/<meta[^>]*property=(["\'])og:image\1[^>]*content=(["\'])(.*?)\2/i',
            '/<meta[^>]*content=(["\'])(.*?)\1[^>]*property=(["\'])og:image\3/i',
        ];

        foreach ($patterns as $index => $pattern) {
            if (preg_match($pattern, $html, $matches)) {
                $value = trim($index === 0 ? $matches[3] : $matches[2]);

                $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');

                if ($value !== '') {
                    return $value;
                }
            }
        }

        return null;
    }
}
