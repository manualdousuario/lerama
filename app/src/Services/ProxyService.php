<?php
declare(strict_types=1);

namespace Lerama\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class ProxyService
{
    private array $proxyList = [];
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
        
        $this->loadCachedProxyList();
    }

    public function getRandomProxy(): ?array
    {
        if (empty($this->proxyList)) {
            $this->fetchProxyList();
        }
        
        if (empty($this->proxyList)) {
            return null;
        }
        
        return $this->proxyList[array_rand($this->proxyList)];
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