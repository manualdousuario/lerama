<?php

declare(strict_types=1);

namespace Lerama\Config;

class HttpClientConfig
{
    /**
     * Get default HTTP client configuration
     * Optimized to avoid anti-bot barriers and blocks
     * 
     * @return array
     */
    public static function getDefaultConfig(): array
    {
        return [
            'timeout' => 30,
            'connect_timeout' => 15,
            'http_errors' => false,
            'allow_redirects' => [
                'max' => 4,
                'strict' => false,
                'referer' => true,
                'protocols' => ['http', 'https'],
                'track_redirects' => false
            ],
            
            'headers' => [
                'User-Agent' => self::getRandomUserAgent(),
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
                'Accept-Language' => 'pt-BR,pt;q=0.9,en-US;q=0.8,en;q=0.7',
                'Accept-Encoding' => 'gzip, deflate, br',
                'Connection' => 'keep-alive',
                'Upgrade-Insecure-Requests' => '1',
                'Sec-Fetch-Dest' => 'document',
                'Sec-Fetch-Mode' => 'navigate',
                'Sec-Fetch-Site' => 'none',
            ],
            'curl' => [
                CURLOPT_ENCODING => '',
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2_0,
                CURLOPT_SSL_VERIFYPEER => true, 
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_AUTOREFERER => true, 
                CURLOPT_COOKIEFILE => '',
                CURLOPT_COOKIEJAR => '',
            ],
            'verify' => true,
            'decode_content' => 'gzip'
        ];
    }

    /**
     * Get a random modern User-Agent to rotate and avoid detection
     * 
     * @return string
     */
    private static function getRandomUserAgent(): string
    {
        $userAgents = [
            // Chrome on Windows
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Safari/537.36',
            
            // Chrome on macOS
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            
            // Firefox on Windows
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:121.0) Gecko/20100101 Firefox/121.0',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:120.0) Gecko/20100101 Firefox/120.0',
            
            // Firefox on macOS
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:121.0) Gecko/20100101 Firefox/121.0',
            
            // Safari on macOS
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.1 Safari/605.1.15',
            
            // Edge on Windows
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36 Edg/120.0.0.0',
        ];
        
        return $userAgents[array_rand($userAgents)];
    }

    /**
     * Get simplified HTTP client configuration for image extraction
     *
     * @return array
     */
    public static function getExtractedImageConfig(): array
    {
        return [
            'timeout' => 15,
            'connect_timeout' => 10,
            'http_errors' => false,
            'allow_redirects' => [
                'max' => 5,
                'strict' => false,
                'referer' => true,
                'protocols' => ['http', 'https'],
                'track_redirects' => false
            ],
            'headers' => [
                'User-Agent' => self::getRandomUserAgent(),
                'Accept' => 'image/avif,image/webp,image/apng,image/*,*/*;q=0.8',
                'Accept-Encoding' => 'gzip, deflate, br',
                'Accept-Language' => 'pt-BR,pt;q=0.9,en-US;q=0.8,en;q=0.7',
            ],
            'verify' => true,
            'decode_content' => 'gzip'
        ];
    }
}
