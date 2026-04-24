<?php
declare(strict_types=1);

namespace Lerama\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class ProxyService
{
    private array $proxyList = [];
    private array $directProxies = [];
    private string $cacheFile;
    private Client $httpClient;

    public function __construct()
    {
        $this->cacheFile = __DIR__ . '/../../storage/proxy_list.cache';
        $this->httpClient = new Client([
            'timeout' => 10,
            'connect_timeout' => 5,
            'http_errors' => false
        ]);

        $storageDir = dirname($this->cacheFile);
        if (!is_dir($storageDir)) {
            mkdir($storageDir, 0755, true);
        }

        if (empty($_ENV['PROXY_LIST'] ?? '')) {
            $this->clearCachedProxyList();
        } else {
            $this->loadCachedProxyList();
        }

        $this->loadProxyUrlEnv();
    }

    public function clearCachedProxyList(): bool
    {
        $this->proxyList = [];

        if (file_exists($this->cacheFile)) {
            return unlink($this->cacheFile);
        }

        return true;
    }

    public function getRandomProxy(): ?array
    {
        if (empty($this->proxyList) && !empty($_ENV['PROXY_LIST'] ?? '')) {
            $this->fetchProxyList();
        }

        $combined = array_merge($this->proxyList, $this->directProxies);

        if (empty($combined)) {
            return null;
        }

        return $combined[array_rand($combined)];
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

    public function fetchProxyList(): bool
    {
        $proxyListUrl = $_ENV['PROXY_LIST'] ?? null;
        
        if (empty($proxyListUrl)) {
            return false;
        }
        
        try {
            $response = $this->httpClient->get($proxyListUrl);
            
            if ($response->getStatusCode() !== 200) {
                return false;
            }
            
            $content = (string) $response->getBody();
            $lines = explode("\n", $content);
            $proxies = [];
            
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) {
                    continue;
                }
                
                $proxy = $this->parseProxyString($line);
                if ($proxy) {
                    $proxies[] = $proxy;
                }
            }
            
            if (!empty($proxies)) {
                $this->proxyList = $proxies;
                $this->saveCachedProxyList($proxies);
                return true;
            }
            
            return false;
        } catch (GuzzleException $e) {
            error_log("Error fetching proxy list: " . $e->getMessage());
            return false;
        }
    }

    public function loadCachedProxyList(): bool
    {
        if (!file_exists($this->cacheFile)) {
            return false;
        }
        
        $content = file_get_contents($this->cacheFile);
        if ($content === false) {
            return false;
        }
        
        $proxies = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($proxies)) {
            return false;
        }
        
        $this->proxyList = $proxies;
        return true;
    }

    public function saveCachedProxyList(array $proxies): bool
    {
        $content = json_encode($proxies);
        if ($content === false) {
            return false;
        }
        
        return file_put_contents($this->cacheFile, $content) !== false;
    }

    public function parseProxyString(string $proxyString): ?array
    {
        if (preg_match('/^([^:]+):(\d+):([^:]+):(.+)$/', $proxyString, $matches)) {
            return [
                'host' => $matches[1],
                'port' => (int) $matches[2],
                'username' => $matches[3],
                'password' => $matches[4]
            ];
        }
        
        if (preg_match('/^([^@]+)@([^:]+):([^:]+):(\d+)$/', $proxyString, $matches)) {
            return [
                'host' => $matches[3],
                'port' => (int) $matches[4],
                'username' => $matches[1],
                'password' => $matches[2]
            ];
        }
        
        if (preg_match('/^([^:]+):(\d+)$/', $proxyString, $matches)) {
            return [
                'host' => $matches[1],
                'port' => (int) $matches[2],
                'username' => null,
                'password' => null
            ];
        }
        
        return null;
    }
}