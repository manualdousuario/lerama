<?php

declare(strict_types=1);

namespace Lerama\Commands;

use League\CLImate\CLImate;
use DB;
use Lerama\Services\FeedTypeDetector;
use GuzzleHttp\Client;

class FeedImporter
{
    private CLImate $climate;
    private FeedTypeDetector $feedDetector;
    private Client $httpClient;

    public function __construct(CLImate $climate)
    {
        $this->climate = $climate;
        $this->feedDetector = new FeedTypeDetector();
        $this->httpClient = new Client([
            'timeout' => 10,
            'connect_timeout' => 5,
            'http_errors' => false,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (compatible; Lerama Feed Importer/1.0)'
            ]
        ]);
    }

    public function import(string $csvPath): void
    {
        if (!file_exists($csvPath)) {
            $this->climate->error("CSV file not found: {$csvPath}");
            exit(1);
        }

        $this->climate->info("Starting feed import from: {$csvPath}");

        // Read CSV file
        $handle = fopen($csvPath, 'r');
        if (!$handle) {
            $this->climate->error("Failed to open CSV file: {$csvPath}");
            exit(1);
        }

        // Read header
        $header = fgetcsv($handle, 0, ',', '"', '');
        if (!$header || !in_array('url', $header)) {
            $this->climate->error("CSV must have at least 'url' column");
            fclose($handle);
            exit(1);
        }

        // Get column indexes
        $urlIndex = array_search('url', $header);
        $tagsIndex = array_search('tags', $header);
        $categoryIndex = array_search('category', $header);

        $results = [];
        $lineNumber = 1;

        // Process each line
        while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $lineNumber++;
            
            if (empty($row[$urlIndex])) {
                $this->climate->yellow("Line {$lineNumber}: Skipping empty URL");
                $results[] = [
                    'line' => $lineNumber,
                    'url' => '',
                    'status' => 'error',
                    'message' => 'Empty URL'
                ];
                continue;
            }

            $url = trim($row[$urlIndex]);
            $tags = $tagsIndex !== false && !empty($row[$tagsIndex]) ? trim($row[$tagsIndex]) : '';
            $category = $categoryIndex !== false && !empty($row[$categoryIndex]) ? trim($row[$categoryIndex]) : '';

            $this->climate->out("Line {$lineNumber}: Processing {$url}");

            $result = $this->importFeed($url, $tags, $category);
            $results[] = [
                'line' => $lineNumber,
                'url' => $url,
                'status' => $result['status'],
                'message' => $result['message']
            ];

            if ($result['status'] === 'success') {
                $this->climate->green("✓ Line {$lineNumber}: {$result['message']}");
            } else {
                $this->climate->red("✗ Line {$lineNumber}: {$result['message']}");
            }
        }

        fclose($handle);

        // Generate result CSV
        $this->generateResultCsv($csvPath, $results);

        // Summary
        $successCount = count(array_filter($results, fn($r) => $r['status'] === 'success'));
        $errorCount = count(array_filter($results, fn($r) => $r['status'] === 'error'));

        $this->climate->info("Import completed!");
        $this->climate->green("Successful: {$successCount}");
        $this->climate->red("Errors: {$errorCount}");
    }

    private function importFeed(string $url, string $tags, string $category): array
    {
        try {
            // 1. Discover feed URL
            $feedUrl = $this->discoverFeedUrl($url);
            if (!$feedUrl) {
                return [
                    'status' => 'error',
                    'message' => 'Could not discover feed URL'
                ];
            }

            // 2. Detect feed type
            $feedType = $this->feedDetector->detectType($feedUrl);
            if (!$feedType) {
                return [
                    'status' => 'error',
                    'message' => 'Could not detect feed type'
                ];
            }

            // 3. Check if feed already exists
            $existingFeed = DB::queryFirstRow(
                "SELECT id FROM feeds WHERE feed_url = %s",
                $feedUrl
            );

            if ($existingFeed) {
                return [
                    'status' => 'error',
                    'message' => 'Feed already exists (ID: ' . $existingFeed['id'] . ')'
                ];
            }

            // 4. Get feed title
            $feedTitle = $this->getFeedTitle($feedUrl, $feedType);

            // 5. Insert feed
            DB::insert('feeds', [
                'title' => $feedTitle,
                'feed_url' => $feedUrl,
                'site_url' => $url,
                'feed_type' => $feedType,
                'status' => 'online',
                'created_at' => DB::sqleval('NOW()'),
                'updated_at' => DB::sqleval('NOW()')
            ]);

            $feedId = DB::insertId();

            // 6. Process tags
            if (!empty($tags)) {
                $this->processTags($feedId, $tags);
            }

            // 7. Process category
            if (!empty($category)) {
                $this->processCategory($feedId, $category);
            }

            return [
                'status' => 'success',
                'message' => "Feed imported successfully (ID: {$feedId}, Type: {$feedType})"
            ];

        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    private function discoverFeedUrl(string $url): ?string
    {
        try {
            // First, try the URL directly
            $feedType = $this->feedDetector->detectType($url);
            if ($feedType) {
                return $url;
            }

            // Try to fetch the HTML page and find feed links
            $response = $this->httpClient->get($url);
            if ($response->getStatusCode() !== 200) {
                return null;
            }

            $html = (string) $response->getBody();

            // Look for feed links in HTML
            $feedUrls = [];

            // RSS/Atom link tags
            if (preg_match_all('/<link[^>]*type=["\']application\/(rss|atom)\+xml["\'][^>]*href=["\']([^"\']+)["\'][^>]*>/i', $html, $matches)) {
                $feedUrls = array_merge($feedUrls, $matches[2]);
            }

            // Alternate format
            if (preg_match_all('/<link[^>]*href=["\']([^"\']+)["\'][^>]*type=["\']application\/(rss|atom)\+xml["\'][^>]*>/i', $html, $matches)) {
                $feedUrls = array_merge($feedUrls, $matches[1]);
            }

            // Try common feed URLs
            $commonPaths = [
                '/feed',
                '/rss',
                '/feed.xml',
                '/rss.xml',
                '/atom.xml',
                '/index.xml'
            ];

            $parsedUrl = parse_url($url);
            $baseUrl = $parsedUrl['scheme'] . '://' . $parsedUrl['host'];

            foreach ($commonPaths as $path) {
                $testUrl = $baseUrl . $path;
                if (!in_array($testUrl, $feedUrls)) {
                    $feedUrls[] = $testUrl;
                }
            }

            // Test each discovered URL
            foreach ($feedUrls as $feedUrl) {
                // Make relative URLs absolute
                if (strpos($feedUrl, 'http') !== 0) {
                    $feedUrl = $baseUrl . $feedUrl;
                }

                $feedType = $this->feedDetector->detectType($feedUrl);
                if ($feedType) {
                    return $feedUrl;
                }
            }

            return null;

        } catch (\Exception $e) {
            $this->climate->whisper("Error discovering feed: {$e->getMessage()}");
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

            // Try to extract title based on feed type
            if (in_array($feedType, ['rss1', 'rss2', 'atom', 'rdf', 'xml'])) {
                libxml_use_internal_errors(true);
                $xml = simplexml_load_string($content);
                if ($xml !== false) {
                    // RSS
                    if (isset($xml->channel->title)) {
                        return (string) $xml->channel->title;
                    }
                    // Atom
                    if (isset($xml->title)) {
                        return (string) $xml->title;
                    }
                }
            }

            return 'Imported Feed';

        } catch (\Exception $e) {
            return 'Imported Feed';
        }
    }

    private function processTags(int $feedId, string $tagsString): void
    {
        // Split tags by comma or semicolon
        $tagsString = str_replace(';', ',', $tagsString);
        $tagNames = array_map('trim', explode(',', $tagsString));

        foreach ($tagNames as $tagName) {
            if (empty($tagName)) {
                continue;
            }

            // Create slug
            $slug = $this->createSlug($tagName);

            // Check if tag exists
            $tag = DB::queryFirstRow("SELECT id FROM tags WHERE slug = %s", $slug);

            if (!$tag) {
                // Create tag
                DB::insert('tags', [
                    'name' => $tagName,
                    'slug' => $slug,
                    'created_at' => DB::sqleval('NOW()'),
                    'updated_at' => DB::sqleval('NOW()')
                ]);
                $tagId = DB::insertId();
                $this->climate->whisper("Created new tag: {$tagName}");
            } else {
                $tagId = $tag['id'];
            }

            // Associate tag with feed
            try {
                DB::insert('feed_tags', [
                    'feed_id' => $feedId,
                    'tag_id' => $tagId,
                    'created_at' => DB::sqleval('NOW()')
                ]);
            } catch (\Exception $e) {
                // Tag already associated, skip
            }
        }
    }

    private function processCategory(int $feedId, string $categoryName): void
    {
        if (empty($categoryName)) {
            return;
        }

        // Create slug
        $slug = $this->createSlug($categoryName);

        // Check if category exists
        $category = DB::queryFirstRow("SELECT id FROM categories WHERE slug = %s", $slug);

        if (!$category) {
            // Create category
            DB::insert('categories', [
                'name' => $categoryName,
                'slug' => $slug,
                'created_at' => DB::sqleval('NOW()'),
                'updated_at' => DB::sqleval('NOW()')
            ]);
            $categoryId = DB::insertId();
            $this->climate->whisper("Created new category: {$categoryName}");
        } else {
            $categoryId = $category['id'];
        }

        // Associate category with feed
        try {
            DB::insert('feed_categories', [
                'feed_id' => $feedId,
                'category_id' => $categoryId,
                'created_at' => DB::sqleval('NOW()')
            ]);
        } catch (\Exception $e) {
            // Category already associated, skip
        }
    }

    private function createSlug(string $text): string
    {
        // Convert to lowercase
        $slug = mb_strtolower($text, 'UTF-8');

        // Replace accented characters
        $slug = iconv('UTF-8', 'ASCII//TRANSLIT', $slug);

        // Replace non-alphanumeric characters with hyphens
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);

        // Remove leading/trailing hyphens
        $slug = trim($slug, '-');

        return $slug;
    }

    private function generateResultCsv(string $originalCsvPath, array $results): void
    {
        $pathInfo = pathinfo($originalCsvPath);
        $resultPath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '_result.csv';

        $handle = fopen($resultPath, 'w');
        if (!$handle) {
            $this->climate->error("Failed to create result CSV: {$resultPath}");
            return;
        }

        // Write header
        fputcsv($handle, ['line', 'url', 'status', 'message'], ',', '"', '');

        // Write results
        foreach ($results as $result) {
            fputcsv($handle, [
                $result['line'],
                $result['url'],
                $result['status'],
                $result['message']
            ], ',', '"', '');
        }

        fclose($handle);

        $this->climate->info("Result CSV created: {$resultPath}");
    }
}