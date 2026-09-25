<?php

declare(strict_types=1);

namespace IRJalali\Core\Security;

use IRJalali\Core\Cache\CacheInterface;

/**
 * Fixed-window rate limiter (brute-force / abuse protection).
 */
final class RateLimiter
{
    public function __construct(private readonly CacheInterface $cache)
    {
    }

    public function attempts(string $key): int
    {
        return (int) ($this->cache->get($this->key($key)) ?? 0);
    }

    public function hit(string $key, int $decaySeconds = 60): int
    {
        $attempts = $this->attempts($key) + 1;
        $this->cache->set($this->key($key), $attempts, $decaySeconds);

        return $attempts;
    }

    public function tooManyAttempts(string $key, int $maxAttempts): bool
    {
        return $this->attempts($key) >= $maxAttempts;
    }

    public function clear(string $key): void
    {
        $this->cache->delete($this->key($key));
    }

    public function availableIn(string $key): int
    {
        return $this->cache->ttl($this->key($key));
    }

    private function key(string $key): string
    {
        return 'ratelimit:' . preg_replace('/[^a-zA-Z0-9_.\-@]/', '_', $key);
    }
}
