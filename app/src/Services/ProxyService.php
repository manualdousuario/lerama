<?php
declare(strict_types=1);

namespace Lerama\Services;

class ProxyService
{
    private array $directProxies = [];

    public function __construct()
    {
        $this->loadProxyUrlEnv();
    }

    public function getRandomProxy(): ?array
    {
        if (empty($this->directProxies)) {
            return null;
        }

        return $this->directProxies[array_rand($this->directProxies)];
    }

    public function loadProxyUrlEnv(): void
    {
        $proxyUrls = $_ENV['PROXY_URL'] ?? null;

        if (empty($proxyUrls)) {
            return;
        }

        $this->directProxies = [];
        $urls = explode(',', $proxyUrls);

        foreach ($urls as $url) {
            $url = trim($url);
            if (empty($url)) {
                continue;
            }

            $proxy = $this->parseProxyUrl($url);
            if ($proxy) {
                $this->directProxies[] = $proxy;
            }
        }
    }

    public function parseProxyUrl(string $url): ?array
    {
        $parts = parse_url($url);
        if ($parts === false || empty($parts['host']) || empty($parts['port'])) {
            return null;
        }

        return [
            'host' => $parts['host'],
            'port' => (int) $parts['port'],
            'username' => $parts['user'] ?? null,
            'password' => isset($parts['pass']) ? urldecode($parts['pass']) : null,
        ];
    }
}
