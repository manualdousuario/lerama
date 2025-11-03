<?php

declare(strict_types=1);

namespace Lerama\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Lerama\Config\HttpClientConfig;
use DB;

class FeedTypeDetector
{
    private \GuzzleHttp\Client $httpClient;

    public function __construct()
    {
        $this->httpClient = new \GuzzleHttp\Client(HttpClientConfig::getDefaultConfig());
    }

    public function detectType(string $url, ?int $feedId = null): ?string
    {
        try {
            $response = $this->httpClient->get($url);
            $statusCode = $response->getStatusCode();

            if ($statusCode !== 200) {
                if ($feedId) {
                    $this->pauseFeedWithError($feedId, "HTTP error: Status code {$statusCode}");
                }
                return null;
            }

            $content = (string) $response->getBody();
            if (empty($content)) {
                if ($feedId) {
                    $this->pauseFeedWithError($feedId, "Empty response received");
                }
                return null;
            }

            return $this->detectTypeFromContent($content);
        } catch (\Exception $e) {
            if ($feedId) {
                $this->pauseFeedWithError($feedId, $e->getMessage());
            }
            return null;
        }
    }

    private function pauseFeedWithError(int $feedId, string $errorMessage): void
    {
        try {
            DB::update('feeds', [
                'status' => 'paused',
                'last_error' => $errorMessage,
                'last_checked' => DB::sqleval("NOW()")
            ], 'id=%i', $feedId);
        } catch (\Exception $e) {
            // Log error if needed
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
        if (strpos($firstLine, ',') === false) {
            return false;
        }

        $secondLine = trim($lines[1]);
        if (strpos($secondLine, ',') === false) {
            return false;
        }

        $firstLineCommas = substr_count($firstLine, ',');
        $secondLineCommas = substr_count($secondLine, ',');

        return abs($firstLineCommas - $secondLineCommas) <= 1;
    }

    private function isXml(string $content): bool
    {
        $content = trim($content);
        return (
            strpos($content, '<?xml') === 0 ||
            strpos($content, '<rss') === 0 ||
            strpos($content, '<feed') === 0 ||
            strpos($content, '<rdf:RDF') === 0
        );
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

        if (strpos($content, '<rdf:RDF') !== false) {
            return 'rdf';
        }

        if (isset($xml->item) && !isset($xml->channel)) {
            return 'rss1';
        }

        return 'xml';
    }
}
