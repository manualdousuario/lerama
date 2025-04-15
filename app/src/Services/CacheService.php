<?php

declare(strict_types=1);

namespace Lerama\Services;

class CacheService
{
    private \Redis $redis;
    private int $defaultTtl = 604800; // 7 days

    public function __construct()
    {
        $this->redis = new \Redis();
        $this->redis->connect(
            $_ENV['REDIS_HOST'] ?? 'localhost',
            (int)($_ENV['REDIS_PORT'] ?? 6379)
        );
        
        if (isset($_ENV['REDIS_PASSWORD']) && !empty($_ENV['REDIS_PASSWORD'])) {
            $this->redis->auth($_ENV['REDIS_PASSWORD']);
        }
    }

    public function get(string $key)
    {
        $data = $this->redis->get($key);
        if ($data === false) {
            return null;
        }
        
        return unserialize($data);
    }

    public function set(string $key, $value, ?int $ttl = null): bool
    {
        $ttl = $ttl ?? $this->defaultTtl;
        return $this->redis->setex($key, $ttl, serialize($value));
    }

    public function delete(string $key): bool
    {
        return $this->redis->del($key) > 0;
    }
    
    public function flushByPattern(string $pattern): void
    {
        $keys = $this->redis->keys($pattern);
        if (!empty($keys)) {
            foreach ($keys as $key) {
                $this->redis->del($key);
            }
        }
    }

    public function exists(string $key): bool
    {
        return $this->redis->exists($key) > 0;
    }

    public function generateKey(string $prefix, array $params = []): string
    {
        $paramsString = empty($params) ? '' : '_' . md5(serialize($params));
        return $prefix . $paramsString;
    }
}