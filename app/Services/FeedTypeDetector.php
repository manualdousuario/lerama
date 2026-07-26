<?php

namespace App\Services;

use App\Models\Feed;
use App\Support\HttpClient;
use GuzzleHttp\Client;

// Detects a feed's type (rss1/rss2/atom/rdf/csv/json/xml) by fetching and
// inspecting its content.
class FeedTypeDetector
{
    private Client $httpClient;

    public function __construct()
    {
        $this->httpClient = new Client(HttpClient::defaultConfig());
    }

    public function detectType(string $url, ?int $feedId = null): ?string
    {
        try {
            $response = $this->httpClient->get($url);

            if ($response->getStatusCode() !== 200) {
                if ($feedId) {
                    $this->pauseFeedWithError($feedId, "HTTP error: Status code {$response->getStatusCode()}");
                }

                return null;
            }

            $content = (string) $response->getBody();
            if ($content === '') {
                if ($feedId) {
                    $this->pauseFeedWithError($feedId, 'Empty response received');
                }

                return null;
            }

            return $this->detectTypeFromContent($content);
        } catch (\Throwable $e) {
            if ($feedId) {
                $this->pauseFeedWithError($feedId, $e->getMessage());
            }

            return null;
        }
    }

    public function detectTypeFromContent(string $content): ?string
    {
        if ($this->isJson($content)) {
            return 'json';
        }

        if ($this->isCsv($content)) {
            return 'csv';
        }

        if ($this->isXml($content)) {
            return $this->detectXmlFeedType($content);
        }

        return null;
    }

    private function pauseFeedWithError(int $feedId, string $errorMessage): void
    {
        try {
            Feed::query()->whereKey($feedId)->update([
                'status' => 'paused',
                'last_error' => $errorMessage,
                'last_checked' => now(),
            ]);
        } catch (\Throwable) {
            // Feed update failures are intentionally swallowed.
        }
    }

    private function isJson(string $content): bool
    {
        json_decode($content);

        return json_last_error() === JSON_ERROR_NONE;
    }

    private function isCsv(string $content): bool
    {
        $lines = explode("\n", $content);
        if (count($lines) < 2) {
            return false;
        }

        $firstLine = trim($lines[0]);
        $secondLine = trim($lines[1]);

        if (! str_contains($firstLine, ',') || ! str_contains($secondLine, ',')) {
            return false;
        }

        return abs(substr_count($firstLine, ',') - substr_count($secondLine, ',')) <= 1;
    }

    private function isXml(string $content): bool
    {
        $content = trim($content);

        return str_starts_with($content, '<?xml')
            || str_starts_with($content, '<rss')
            || str_starts_with($content, '<feed')
            || str_starts_with($content, '<rdf:RDF');
    }

    private function detectXmlFeedType(string $content): string
    {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($content);
        if ($xml === false) {
            return 'xml';
        }

        if (isset($xml->channel) && isset($xml->channel->item)) {
            return 'rss2';
        }

        if (isset($xml->entry) || $xml->getName() === 'feed') {
            return 'atom';
        }

        if (str_contains($content, '<rdf:RDF')) {
            return 'rdf';
        }

        if (isset($xml->item) && ! isset($xml->channel)) {
            return 'rss1';
        }

        return 'xml';
    }
}
