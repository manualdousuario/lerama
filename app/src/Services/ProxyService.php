<?php
declare(strict_types=1);

namespace Lerama\Services;

class ProxyService
{
    public const PROXY_ATTEMPTS = 2;

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

    public function buildAttemptConfigs(array $baseConfig): array
    {
        $attempts = [];

        if (!empty($this->directProxies)) {
            for ($i = 0; $i < self::PROXY_ATTEMPTS; $i++) {
                $proxy = $this->getRandomProxy();
                if ($proxy === null) {
                    break;
                }

                $config = $baseConfig;
                $config['proxy'] = $this->buildProxyUrl($proxy);

                $attempts[] = [
                    'config' => $config,
                    'usingProxy' => true,
                    'label' => 'proxy ' . ($i + 1) . '/' . self::PROXY_ATTEMPTS,
                ];
            }
        }

        $attempts[] = [
            'config' => $baseConfig,
            'usingProxy' => false,
            'label' => 'direct',
        ];

        return $attempts;
    }

    public function buildProxyUrl(array $proxy): string
    {
        if ($proxy['username'] && $proxy['password']) {
            return "http://{$proxy['username']}:{$proxy['password']}@{$proxy['host']}:{$proxy['port']}";
        }

        return "http://{$proxy['host']}:{$proxy['port']}";
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
