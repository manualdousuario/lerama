<?php

namespace App\Services;

/**
 * Builds the request attempt chain: up to 2 random proxies from PROXY_URL,
 * then a direct attempt.
 */
class ProxyService
{
    public const PROXY_ATTEMPTS = 2;

    private array $proxies = [];

    public function __construct()
    {
        $this->loadFromConfig();
    }

    public function getRandomProxy(): ?array
    {
        if (empty($this->proxies)) {
            return null;
        }

        return $this->proxies[array_rand($this->proxies)];
    }

    /**
     * @return array<int, array{config: array, usingProxy: bool, label: string}>
     */
    public function buildAttemptConfigs(array $baseConfig): array
    {
        $attempts = [];

        if (! empty($this->proxies)) {
            $verifyProxyCert = (bool) config('lerama.proxy.ssl_verify', true);

            for ($i = 0; $i < self::PROXY_ATTEMPTS; $i++) {
                $proxy = $this->getRandomProxy();
                if ($proxy === null) {
                    break;
                }

                $config = $baseConfig;
                $config['proxy'] = $this->buildProxyUrl($proxy);

                if (($proxy['scheme'] ?? 'http') === 'https' && ! $verifyProxyCert) {
                    $config['curl'] = ($config['curl'] ?? []) + [
                        CURLOPT_PROXY_SSL_VERIFYPEER => false,
                        CURLOPT_PROXY_SSL_VERIFYHOST => 0,
                    ];
                }

                $attempts[] = [
                    'config' => $config,
                    'usingProxy' => true,
                    'label' => 'proxy '.($i + 1).'/'.self::PROXY_ATTEMPTS,
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
        $scheme = $proxy['scheme'] ?? 'http';

        if (! empty($proxy['username']) && ! empty($proxy['password'])) {
            $user = rawurlencode($proxy['username']);
            $pass = rawurlencode($proxy['password']);

            return "{$scheme}://{$user}:{$pass}@{$proxy['host']}:{$proxy['port']}";
        }

        return "{$scheme}://{$proxy['host']}:{$proxy['port']}";
    }

    public function loadFromConfig(): void
    {
        $proxyUrls = (string) config('lerama.proxy.urls', '');

        if (trim($proxyUrls) === '') {
            return;
        }

        $this->proxies = [];

        foreach (explode(',', $proxyUrls) as $url) {
            $url = trim($url);
            if ($url === '') {
                continue;
            }

            $proxy = $this->parseProxyUrl($url);
            if ($proxy !== null) {
                $this->proxies[] = $proxy;
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
            'scheme' => $parts['scheme'] ?? 'http',
            'host' => $parts['host'],
            'port' => (int) $parts['port'],
            'username' => $parts['user'] ?? null,
            'password' => isset($parts['pass']) ? urldecode($parts['pass']) : null,
        ];
    }
}
