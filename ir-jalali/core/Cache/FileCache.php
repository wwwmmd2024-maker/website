<?php

declare(strict_types=1);

namespace IRJalali\Core\Cache;

/**
 * File-based cache (works everywhere incl. shared hosting).
 * Redis can be added later behind the same interface — no code change needed.
 */
final class FileCache implements CacheInterface
{
    public function __construct(
        private readonly string $directory,
        private readonly int $defaultTtl = 3600,
    ) {
        if (!is_dir($this->directory)) {
            mkdir($this->directory, 0755, true);
        }
    }

    public function get(string $key): mixed
    {
        $file = $this->file($key);
        if (!is_file($file)) {
            return null;
        }
        $payload = $this->read($file);
        if ($payload === null) {
            return null;
        }
        if ($payload['expires'] !== 0 && $payload['expires'] < time()) {
            @unlink($file);

            return null;
        }

        return $payload['value'];
    }

    public function set(string $key, mixed $value, int $ttl = 0): bool
    {
        $ttl = $ttl <= 0 ? $this->defaultTtl : $ttl;
        $payload = ['expires' => $ttl > 0 ? time() + $ttl : 0, 'value' => $value];
        $data = serialize($payload);
        $file = $this->file($key);
        $tmp = $file . '.' . getmypid() . '.tmp';
        if (file_put_contents($tmp, $data, LOCK_EX) === false) {
            return false;
        }

        return rename($tmp, $file);
    }

    public function delete(string $key): bool
    {
        $file = $this->file($key);

        return !is_file($file) || @unlink($file);
    }

    public function clear(): bool
    {
        $ok = true;
        foreach (glob($this->directory . '/*.cache') ?: [] as $file) {
            if (!@unlink($file)) {
                $ok = false;
            }
        }

        return $ok;
    }

    public function ttl(string $key): int
    {
        $payload = $this->read($this->file($key));
        if ($payload === null) {
            return 0;
        }
        if ($payload['expires'] === 0) {
            return 0;
        }
        $left = $payload['expires'] - time();

        return $left > 0 ? $left : 0;
    }

    public function clearPrefix(string $prefix): int
    {
        // Keys are hashed, so maintain a small key-map for prefix clears.
        $map = $this->read($this->directory . '/_map.cache');
        $keys = ($map !== null && is_array($map['value'])) ? $map['value'] : [];
        $removed = 0;
        foreach ($keys as $stored) {
            if (is_string($stored) && str_starts_with($stored, $prefix)) {
                if ($this->delete($stored)) {
                    $removed++;
                }
                unset($keys[array_search($stored, $keys, true)]);
            }
        }
        file_put_contents(
            $this->directory . '/_map.cache',
            serialize(['expires' => 0, 'value' => array_values($keys)]),
            LOCK_EX
        );

        return $removed;
    }

    public function remember(string $key, callable $callback, int $ttl = 0): mixed
    {
        $value = $this->get($key);
        if ($value !== null) {
            return $value;
        }
        $value = $callback();
        $this->set($key, $value, $ttl);

        return $value;
    }

    private function file(string $key): string
    {
        return $this->directory . '/' . hash('sha256', $key) . '.cache';
    }

    /** @return array{expires: int, value: mixed}|null */
    private function read(string $file): ?array
    {
        if (!is_file($file)) {
            return null;
        }
        $raw = @file_get_contents($file);
        if ($raw === false) {
            return null;
        }
        $payload = @unserialize($raw, ['allowed_classes' => false]);

        return is_array($payload) ? $payload : null;
    }
}
