<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;

class FaviconService
{
    private const SOURCE = 'https://icons.duckduckgo.com/ip3/%s.ico';

    /** @var array<string, bool> */
    private static array $memo = [];

    public function __construct(private ?Client $client = null) {}

    public function domain(string $siteUrl): ?string
    {
        $host = parse_url($siteUrl, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return null;
        }

        return preg_replace('/^www\./', '', strtolower($host));
    }

    public function url(string $siteUrl): ?string
    {
        $domain = $this->domain($siteUrl);

        if ($domain === null) {
            return null;
        }

        return $this->hasFavicon($domain) ? $this->iconUrl($domain) : null;
    }

    public function hasFavicon(string $domain): bool
    {
        if (isset(self::$memo[$domain])) {
            return self::$memo[$domain];
        }

        $key = 'favicon:'.$domain;

        try {
            $cached = Cache::store(config('lerama.favicon.cache_store'))->get($key);
        } catch (\Throwable) {
            return self::$memo[$domain] = false;
        }

        if ($cached !== null) {
            return self::$memo[$domain] = (bool) $cached;
        }

        $status = $this->check($domain);
        $has = $status === 200;

        $ttl = in_array($status, [200, 404], true)
            ? config('lerama.favicon.ttl')
            : config('lerama.favicon.error_ttl');

        try {
            Cache::store(config('lerama.favicon.cache_store'))->put($key, $has ? 1 : 0, $ttl);
        } catch (\Throwable) {
            //
        }

        return self::$memo[$domain] = $has;
    }

    public function iconUrl(string $domain): string
    {
        return sprintf(self::SOURCE, rawurlencode($domain));
    }

    public static function flushMemo(): void
    {
        self::$memo = [];
    }

    private function check(string $domain): ?int
    {
        try {
            return $this->client()->head($this->iconUrl($domain))->getStatusCode();
        } catch (\Throwable) {
            return null;
        }
    }

    private function client(): Client
    {
        return $this->client ??= new Client([
            'timeout' => 3,
            'connect_timeout' => 2,
            'http_errors' => false,
            'allow_redirects' => false,
        ]);
    }
}
