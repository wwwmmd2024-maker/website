<?php

declare(strict_types=1);

namespace IRJalali\Core\Cache;

/**
 * Redis cache driver (optional — Part 1 §19). Uses the ext-redis client when
 * available. The platform falls back to FileCache when Redis is unreachable,
 * so deployments without Redis are fully supported.
 */
final class RedisCache implements CacheInterface
{
    private \Redis $redis;

    public function __construct(
        private readonly string $host = '127.0.0.1',
        private readonly int $port = 6379,
        private readonly int $defaultTtl = 3600,
        private readonly string $prefix = 'irj:',
        private readonly float $timeout = 2.0,
    ) {
        if (!extension_loaded('redis')) {
            throw new \RuntimeException('ext-redis is not loaded.');
        }
        $this->redis = new \Redis();
        if (!@$this->redis->connect($host, $port, $this->timeout)) {
            throw new \RuntimeException("Cannot connect to Redis at {$host}:{$port}.");
        }
    }

    public function get(string $key): mixed
    {
        $raw = $this->redis->get($this->prefix . $key);
        if ($raw === false) {
            return null;
        }
        $decoded = json_decode((string) $raw, true);

        return $decoded === null && $raw !== 'null' ? $raw : $decoded;
    }

    public function set(string $key, mixed $value, int $ttl = 0): bool
    {
        $ttl = $ttl > 0 ? $ttl : $this->defaultTtl;

        return $this->redis->setex($this->prefix . $key, $ttl, json_encode($value, JSON_UNESCAPED_UNICODE) ?: 'null');
    }

    public function delete(string $key): bool
    {
        return $this->redis->del($this->prefix . $key) > 0;
    }

    public function clear(): bool
    {
        // Only clear keys under our prefix.
        $iterator = null;
        $deleted = true;
        while (($keys = $this->redis->scan($iterator, $this->prefix . '*', 200)) !== false) {
            if ($keys === []) {
                break;
            }
            foreach ($keys as $key) {
                $this->redis->del($key);
            }
            if ($iterator === 0 || $iterator === null) {
                break;
            }
        }

        return $deleted;
    }

    public function ttl(string $key): int
    {
        $ttl = $this->redis->ttl($this->prefix . $key);

        return $ttl > 0 ? $ttl : 0;
    }

    public function clearPrefix(string $prefix): int
    {
        $count = 0;
        $iterator = null;
        while (($keys = $this->redis->scan($iterator, $this->prefix . $prefix . '*', 200)) !== false) {
            if ($keys === []) {
                break;
            }
            foreach ($keys as $key) {
                $count += (int) $this->redis->del($key);
            }
            if ($iterator === 0 || $iterator === null) {
                break;
            }
        }

        return $count;
    }
}
