<?php

declare(strict_types=1);

namespace IRJalali\App\Repositories;

use IRJalali\Core\Cache\CacheInterface;
use IRJalali\Core\Database\Database;

/**
 * Key/value options store with read-through cache.
 */
final class OptionRepository
{
    public function __construct(
        private readonly Database $db,
        private readonly CacheInterface $cache,
    ) {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $cached = $this->cache->get('option:' . $key);
        if ($cached !== null) {
            return $cached === '__NULL__' ? $default : $cached;
        }
        $row = $this->db->table('options')->where('key', $key)->first();
        $value = $row['value'] ?? null;
        $this->cache->set('option:' . $key, $value ?? '__NULL__', 3600);

        return $value ?? $default;
    }

    public function set(string $key, mixed $value, bool $autoload = true): void
    {
        $stored = is_scalar($value) || $value === null
            ? (string) $value
            : json_encode($value, JSON_UNESCAPED_UNICODE);
        $now = date('Y-m-d H:i:s');
        $existing = $this->db->table('options')->where('key', $key)->first();
        if ($existing === null) {
            $this->db->insert('options', [
                'key' => $key,
                'value' => $stored,
                'autoload' => $autoload ? 1 : 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            $this->db->table('options')->where('key', $key)->update([
                'value' => $stored,
                'updated_at' => $now,
            ]);
        }
        $this->cache->set('option:' . $key, $stored, 3600);
    }

    /** @return array<string, string> */
    public function all(): array
    {
        $rows = $this->db->select('SELECT `key`, `value` FROM options');
        $out = [];
        foreach ($rows as $row) {
            $out[$row['key']] = $row['value'];
        }

        return $out;
    }
}
