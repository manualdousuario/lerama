<?php
declare(strict_types=1);

namespace Lerama\Services;

class FeedTypeDetector
{
    /**
     * Detect the feed type from a URL
     *
     * @param string $url The feed URL
     * @return string|null The detected feed type or null if not detected
     */
    public function detectType(string $url): ?string
    {
        try {
            $content = file_get_contents($url);
            if ($content === false) {
                return null;
            }
            
            // Try to detect based on content
            return $this->detectTypeFromContent($content);
        } catch (\Exception $e) {
            return null;
        }
    }
    
    /**
     * Detect the feed type from content
     *
     * @param string $content The feed content
     * @return string|null The detected feed type or null if not detected
     */
    public function detectTypeFromContent(string $content): ?string
    {
        // Check if it's JSON
        if ($this->isJson($content)) {
            return 'json';
        }
        
        // Check if it's CSV
        if ($this->isCsv($content)) {
            return 'csv';
        }
        
        // Check if it's XML-based (RSS, Atom, RDF)
        if ($this->isXml($content)) {
            // Try to determine the specific XML feed type
            return $this->detectXmlFeedType($content);
        }
        
        return null;
    }
    
    /**
     * Check if content is JSON
     *
     * @param string $content The content to check
     * @return bool True if content is JSON, false otherwise
     */
    private function isJson(string $content): bool
    {
        json_decode($content);
        return json_last_error() === JSON_ERROR_NONE;
    }
    
    /**
     * Check if content is CSV
     *
     * @param string $content The content to check
     * @return bool True if content is CSV, false otherwise
     */
    private function isCsv(string $content): bool
    {
        // Simple heuristic: Check if the content has multiple lines and commas
        $lines = explode("\n", $content);
        if (count($lines) < 2) {
            return false;
        }
        
        // Check if the first line has commas and could be a header
        $firstLine = trim($lines[0]);
        if (strpos($firstLine, ',') === false) {
            return false;
        }
        
        // Check if the second line also has commas
        $secondLine = trim($lines[1]);
        if (strpos($secondLine, ',') === false) {
            return false;
        }
        
        // Check if the number of commas in the first and second lines are similar
        $firstLineCommas = substr_count($firstLine, ',');
        $secondLineCommas = substr_count($secondLine, ',');
        
        return abs($firstLineCommas - $secondLineCommas) <= 1;
    }
    
    /**
     * Check if content is XML
     *
     * @param string $content The content to check
     * @return bool True if content is XML, false otherwise
     */
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
    
    /**
     * Detect the specific XML feed type
     *
     * @param string $content The XML content
     * @return string The detected feed type
     */
    private function detectXmlFeedType(string $content): string
    {
        // Try to load the XML
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($content);
        if ($xml === false) {
            return 'xml'; // Generic XML
        }
        
        // Check for RSS 2.0
        if (isset($xml->channel) && isset($xml->channel->item)) {
            return 'rss2';
        }
        
        // Check for Atom
        if (isset($xml->entry) || $xml->getName() === 'feed') {
            return 'atom';
        }
        
        // Check for RSS 1.0 / RDF
        if (strpos($content, '<rdf:RDF') !== false) {
            return 'rdf';
        }
        
        // Check for RSS 1.0 without explicit RDF namespace
        if (isset($xml->item) && !isset($xml->channel)) {
            return 'rss1';
        }
        
        // Default to generic XML
        return 'xml';
    }
}