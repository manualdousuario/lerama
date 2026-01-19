<?php

declare(strict_types=1);

namespace Lerama\Services;

use Predis\Client;
use Predis\Connection\ConnectionException;

class CacheService
{
    private static ?CacheService $instance = null;
    private ?Client $redis = null;
    private bool $enabled;
    private string $prefix;
    private int $defaultTtl;
    private bool $connected = false;

    private function __construct()
    {
        $this->enabled = filter_var($_ENV['CACHE_ENABLED'] ?? 'true', FILTER_VALIDATE_BOOLEAN);
        $this->prefix = $_ENV['CACHE_PREFIX'] ?? 'lerama';
        $this->defaultTtl = (int)($_ENV['CACHE_DEFAULT_TTL'] ?? 300);
    }

    /**
     * Get singleton instance of CacheService
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize Redis connection (lazy initialization)
     */
    private function connect(): bool
    {
        if (!$this->enabled) {
            return false;
        }

        if ($this->redis !== null) {
            return $this->connected;
        }

        try {
            $options = [
                'scheme' => 'tcp',
                'host' => $_ENV['REDIS_HOST'] ?? 'redis',
                'port' => (int)($_ENV['REDIS_PORT'] ?? 6379),
                'database' => (int)($_ENV['REDIS_DATABASE'] ?? 0),
                'read_write_timeout' => 2,
                'timeout' => 2,
            ];

            $password = $_ENV['REDIS_PASSWORD'] ?? '';
            if (!empty($password)) {
                $options['password'] = $password;
            }

            $this->redis = new Client($options);
            $this->redis->ping();
            $this->connected = true;
            return true;
        } catch (ConnectionException $e) {
            error_log("Redis connection failed: " . $e->getMessage());
            $this->connected = false;
            return false;
        } catch (\Exception $e) {
            error_log("Redis error: " . $e->getMessage());
            $this->connected = false;
            return false;
        }
    }

    /**
     * Check if cache is available
     */
    public function isAvailable(): bool
    {
        return $this->enabled && $this->connect();
    }

    /**
     * Generate a cache key with prefix
     */
    public function key(string ...$parts): string
    {
        return $this->prefix . ':' . implode(':', $parts);
    }

    /**
     * Generate a hash for query parameters
     */
    public function hash(mixed $data): string
    {
        return substr(md5(serialize($data)), 0, 8);
    }

    /**
     * Get a value from cache
     * 
     * @param string $key Cache key
     * @return mixed|null Cached value or null if not found/unavailable
     */
    public function get(string $key): mixed
    {
        if (!$this->connect()) {
            return null;
        }

        try {
            $value = $this->redis->get($key);
            if ($value === null) {
                return null;
            }
            return unserialize($value);
        } catch (\Exception $e) {
            error_log("Cache get error for key {$key}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Set a value in cache
     * 
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @param int|null $ttl Time-to-live in seconds (null uses default)
     * @param array $tags Tags for group invalidation
     * @return bool Success status
     */
    public function set(string $key, mixed $value, ?int $ttl = null, array $tags = []): bool
    {
        if (!$this->connect()) {
            return false;
        }

        try {
            $ttl = $ttl ?? $this->defaultTtl;
            $serialized = serialize($value);
            
            $this->redis->setex($key, $ttl, $serialized);
            
            // Store key in tag sets for group invalidation
            foreach ($tags as $tag) {
                $tagKey = $this->key('tag', $tag);
                $this->redis->sadd($tagKey, [$key]);
                // Set tag expiry slightly longer than cache items
                $this->redis->expire($tagKey, $ttl + 60);
            }
            
            return true;
        } catch (\Exception $e) {
            error_log("Cache set error for key {$key}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete a cache key
     * 
     * @param string $key Cache key
     * @return bool Success status
     */
    public function delete(string $key): bool
    {
        if (!$this->connect()) {
            return false;
        }

        try {
            $this->redis->del([$key]);
            return true;
        } catch (\Exception $e) {
            error_log("Cache delete error for key {$key}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete all cache keys associated with a tag
     * 
     * @param string $tag Tag name
     * @return int Number of keys deleted
     */
    public function deleteByTag(string $tag): int
    {
        if (!$this->connect()) {
            return 0;
        }

        try {
            $tagKey = $this->key('tag', $tag);
            $keys = $this->redis->smembers($tagKey);
            
            $deleted = 0;
            if (!empty($keys)) {
                $deleted = $this->redis->del($keys);
            }
            
            // Remove the tag set itself
            $this->redis->del([$tagKey]);
            
            return $deleted;
        } catch (\Exception $e) {
            error_log("Cache deleteByTag error for tag {$tag}: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Delete all cache keys associated with multiple tags
     * 
     * @param array $tags Array of tag names
     * @return int Total number of keys deleted
     */
    public function deleteByTags(array $tags): int
    {
        $deleted = 0;
        foreach ($tags as $tag) {
            $deleted += $this->deleteByTag($tag);
        }
        return $deleted;
    }

    /**
     * Flush all cache (with prefix)
     * 
     * @return bool Success status
     */
    public function flush(): bool
    {
        if (!$this->connect()) {
            return false;
        }

        try {
            $pattern = $this->prefix . ':*';
            $cursor = 0;
            
            do {
                $result = $this->redis->scan($cursor, ['MATCH' => $pattern, 'COUNT' => 100]);
                $cursor = $result[0];
                $keys = $result[1];
                
                if (!empty($keys)) {
                    $this->redis->del($keys);
                }
            } while ($cursor != 0);
            
            return true;
        } catch (\Exception $e) {
            error_log("Cache flush error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Remember - get from cache or execute callback and cache result
     * 
     * @param string $key Cache key
     * @param int|null $ttl Time-to-live in seconds
     * @param array $tags Tags for group invalidation
     * @param callable $callback Function to execute if cache miss
     * @return mixed Cached or computed value
     */
    public function remember(string $key, ?int $ttl, array $tags, callable $callback): mixed
    {
        $cached = $this->get($key);
        
        if ($cached !== null) {
            return $cached;
        }
        
        $value = $callback();
        $this->set($key, $value, $ttl, $tags);
        
        return $value;
    }

    /**
     * Get cache statistics
     *
     * @return array Cache stats or empty array if unavailable
     */
    public function stats(): array
    {
        if (!$this->connect()) {
            return ['available' => false];
        }

        try {
            $info = $this->redis->info();
            $statsInfo = $this->redis->info('stats');
            
            // Get total keys count using DBSIZE (more reliable)
            $totalKeys = $this->redis->dbsize();
            
            // Get lerama-specific keys count
            $pattern = $this->prefix . ':*';
            $leramaKeys = 0;
            $cursor = 0;
            do {
                $result = $this->redis->scan($cursor, ['MATCH' => $pattern, 'COUNT' => 1000]);
                $cursor = $result[0];
                $leramaKeys += count($result[1]);
            } while ($cursor != 0);
            
            return [
                'available' => true,
                'redis_host' => $_ENV['REDIS_HOST'] ?? 'redis',
                'redis_database' => $_ENV['REDIS_DATABASE'] ?? 0,
                'connected_clients' => $info['connected_clients'] ?? 0,
                'used_memory' => $info['used_memory_human'] ?? 'unknown',
                'total_keys' => $totalKeys,
                'lerama_keys' => $leramaKeys,
                'hits' => $statsInfo['keyspace_hits'] ?? 0,
                'misses' => $statsInfo['keyspace_misses'] ?? 0,
            ];
        } catch (\Exception $e) {
            return ['available' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Prevent cloning of singleton
     */
    private function __clone() {}

    /**
     * Prevent unserialization of singleton
     */
    public function __wakeup()
    {
        throw new \Exception("Cannot unserialize singleton");
    }
}
