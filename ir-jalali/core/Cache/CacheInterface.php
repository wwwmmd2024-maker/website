<?php

declare(strict_types=1);

namespace IRJalali\Core\Cache;

interface CacheInterface
{
    public function get(string $key): mixed;

    public function set(string $key, mixed $value, int $ttl = 0): bool;

    public function delete(string $key): bool;

    public function clear(): bool;

    /** Remaining TTL in seconds, 0 when missing/expired. */
    public function ttl(string $key): int;

    /** Delete every entry whose key starts with the given prefix. */
    public function clearPrefix(string $prefix): int;
}
