<?php

namespace App\Services\Feeds;

use App\Models\Category;
use App\Models\Feed;
use App\Models\Tag;
use App\Services\CacheWarmer;
use App\Services\FeedSlugService;
use App\Services\FeedTypeDetector;
use App\Services\ItemCountService;
use App\Support\HttpClient;
use App\Support\Slugger;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;

/**
 * Imports feeds from a ';'-separated CSV (columns: url, name, tags, category)
 * and writes <file>_result.csv with the outcome of each row.
 */
class FeedImporter
{
    /** @var null|callable(string): void */
    private $out;

    private Client $httpClient;

    public function __construct(
        private readonly FeedTypeDetector $detector,
        private readonly ItemCountService $counts,
        ?callable $out = null,
    ) {
        $this->out = $out;
        $this->httpClient = new Client(HttpClient::defaultConfig());
    }

    private function say(string $message): void
    {
        if ($this->out !== null) {
            ($this->out)($message);
        }
    }

    public function import(string $csvPath): array
    {
        if (! file_exists($csvPath)) {
            throw new \RuntimeException("CSV file not found: {$csvPath}");
        }

        $handle = fopen($csvPath, 'r');
        if (! $handle) {
            throw new \RuntimeException("Failed to open CSV file: {$csvPath}");
        }

        $header = fgetcsv($handle, 0, ';', '"', '');
        if (! $header || ! in_array('url', $header)) {
            fclose($handle);
            throw new \RuntimeException("CSV must have at least 'url' column");
        }

        $nameIndex = array_search('name', $header);
        $urlIndex = array_search('url', $header);
        $tagsIndex = array_search('tags', $header) !== false ? array_search('tags', $header) : array_search('tag', $header);
        $categoryIndex = array_search('category', $header);

        $results = [];
        $lineNumber = 0;

        while (($row = fgetcsv($handle, 0, ';', '"', '')) !== false) {
            $lineNumber++;

            if (empty($row[$urlIndex])) {
                $results[] = ['line' => $lineNumber, 'url' => '', 'status' => 'error', 'message' => 'Empty URL'];

                continue;
            }

            $url = trim($row[$urlIndex]);
            $name = $nameIndex !== false && ! empty($row[$nameIndex]) ? trim($row[$nameIndex]) : '';
            $tags = $tagsIndex !== false && ! empty($row[$tagsIndex]) ? trim($row[$tagsIndex]) : '';
            $category = $categoryIndex !== false && ! empty($row[$categoryIndex]) ? trim($row[$categoryIndex]) : '';

            $this->say("Line {$lineNumber}: Processing {$url}");

            $result = $this->importFeed($url, $name, $tags, $category);
            $results[] = ['line' => $lineNumber, 'url' => $url, 'status' => $result['status'], 'message' => $result['message']];

            if ($lineNumber % 50 === 0) {
                gc_collect_cycles();
            }
        }

        fclose($handle);

        $this->generateResultCsv($csvPath, $results);

        $successCount = count(array_filter($results, fn ($r) => $r['status'] === 'success'));

        if ($successCount > 0) {
            Cache::flush();
            app(CacheWarmer::class)->warmImportant();
        }

        return [
            'success' => $successCount,
            'errors' => count($results) - $successCount,
        ];
    }

    private function importFeed(string $url, string $name, string $tags, string $category): array
    {
        try {
            $isSubstack = stripos($url, 'substack.com') !== false;
            $isButtondown = stripos($url, 'buttondown') !== false;

            if ($isSubstack) {
                $feedUrl = rtrim($url, '/').'/feed';
                $feedType = 'rss2';
            } elseif ($isButtondown) {
                $feedUrl = rtrim($url, '/').'/rss';
                $feedType = 'rss2';
            } else {
                $feedUrl = $this->discoverFeedUrl($url);
                if (! $feedUrl) {
                    return ['status' => 'error', 'message' => 'Could not discover feed URL'];
                }

                $feedType = $this->detector->detectType($feedUrl);
                if (! $feedType) {
                    return ['status' => 'error', 'message' => 'Could not detect feed type'];
                }
            }

            if (Feed::query()->where('feed_url', $feedUrl)->exists()) {
                return ['status' => 'error', 'message' => 'Feed already exists'];
            }

            $feedTitle = $name !== '' ? $name : $this->getFeedTitle($feedUrl, $feedType);

            $feed = Feed::create([
                'title' => $feedTitle,
                'feed_url' => $feedUrl,
                'site_url' => $url,
                'slug' => FeedSlugService::generateForFeed($url),
                'feed_type' => $feedType,
                'status' => 'online',
            ]);

            if ($tags !== '') {
                $this->processTags($feed, $tags);
            }
            if ($category !== '') {
                $this->processCategory($feed, $category);
            }

            return ['status' => 'success', 'message' => "Feed imported successfully (ID: {$feed->id}, Type: {$feedType})"];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    private function discoverFeedUrl(string $url): ?string
    {
        try {
            if ($this->detector->detectType($url)) {
                return $url;
            }

            $response = $this->httpClient->get($url);
            if ($response->getStatusCode() !== 200) {
                return null;
            }

            $html = (string) $response->getBody();
            $feedUrls = [];

            if (preg_match_all('/<link[^>]*type=["\']application\/(rss|atom)\+xml["\'][^>]*href=["\']([^"\']+)["\'][^>]*>/i', $html, $matches)) {
                $feedUrls = array_merge($feedUrls, $matches[2]);
            }
            if (preg_match_all('/<link[^>]*href=["\']([^"\']+)["\'][^>]*type=["\']application\/(rss|atom)\+xml["\'][^>]*>/i', $html, $matches)) {
                $feedUrls = array_merge($feedUrls, $matches[1]);
            }

            unset($html);

            $parsedUrl = parse_url($url);
            $baseUrl = $parsedUrl['scheme'].'://'.$parsedUrl['host'];

            foreach (['/feed', '/rss', '/feed.xml', '/rss.xml', '/atom.xml', '/index.xml'] as $path) {
                $testUrl = $baseUrl.$path;
                if (! in_array($testUrl, $feedUrls)) {
                    $feedUrls[] = $testUrl;
                }
            }

            foreach ($feedUrls as $feedUrl) {
                if (! str_starts_with($feedUrl, 'http')) {
                    $feedUrl = $baseUrl.$feedUrl;
                }

                if ($this->detector->detectType($feedUrl)) {
                    return $feedUrl;
                }
            }

            return null;
        } catch (\Throwable $e) {
            $this->say("Error discovering feed: {$e->getMessage()}");

            return null;
        }
    }

    private function getFeedTitle(string $feedUrl, string $feedType): string
    {
        try {
            $response = $this->httpClient->get($feedUrl);
            if ($response->getStatusCode() !== 200) {
                return 'Imported Feed';
            }

            $content = (string) $response->getBody();

            if (in_array($feedType, ['rss1', 'rss2', 'atom', 'rdf', 'xml'])) {
                libxml_use_internal_errors(true);
                $xml = simplexml_load_string($content);
                unset($content);

                if ($xml !== false) {
                    if (isset($xml->channel->title)) {
                        return (string) $xml->channel->title;
                    }
                    if (isset($xml->title)) {
                        return (string) $xml->title;
                    }
                }
            }

            return 'Imported Feed';
        } catch (\Throwable) {
            return 'Imported Feed';
        } finally {
            libxml_clear_errors();
        }
    }

    private function processTags(Feed $feed, string $tagsString): void
    {
        $tagNames = array_map('trim', explode(',', str_replace(';', ',', $tagsString)));

        foreach ($tagNames as $tagName) {
            if ($tagName === '') {
                continue;
            }

            $tag = Tag::firstOrCreate(
                ['slug' => $this->createSlug($tagName)],
                ['name' => $tagName]
            );

            $feed->tags()->syncWithoutDetaching([$tag->id]);
        }

        $this->counts->recountTaxonomy([], $feed->tags()->pluck('tags.id')->all());
    }

    private function processCategory(Feed $feed, string $categoryName): void
    {
        $category = Category::firstOrCreate(
            ['slug' => $this->createSlug($categoryName)],
            ['name' => $categoryName]
        );

        $feed->categories()->syncWithoutDetaching([$category->id]);

        $this->counts->recountTaxonomy([$category->id], []);
    }

    private function createSlug(string $text): string
    {
        return Slugger::slug($text);
    }

    private function generateResultCsv(string $originalCsvPath, array $results): void
    {
        $pathInfo = pathinfo($originalCsvPath);
        $resultPath = $pathInfo['dirname'].'/'.$pathInfo['filename'].'_result.csv';

        $handle = fopen($resultPath, 'w');
        if (! $handle) {
            $this->say("Failed to create result CSV: {$resultPath}");

            return;
        }

        fputcsv($handle, ['line', 'url', 'status', 'message'], ';', '"', '');

        foreach ($results as $result) {
            fputcsv($handle, [$result['line'], $result['url'], $result['status'], $result['message']], ';', '"', '');
        }

        fclose($handle);

        $this->say("Result CSV created: {$resultPath}");
    }
}
