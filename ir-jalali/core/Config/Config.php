<?php

declare(strict_types=1);

namespace IRJalali\Core\Config;

/**
 * Dot-notation configuration repository. Loads every config/*.php file.
 */
final class Config
{
    /** @var array<string, mixed> */
    private array $items = [];

    public function __construct(string $configPath)
    {
        foreach (glob($configPath . '/*.php') ?: [] as $file) {
            $key = basename($file, '.php');
            $data = require $file;
            if (is_array($data)) {
                $this->items[$key] = $data;
            }
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $value = $this->items;
        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    public function set(string $key, mixed $value): void
    {
        $segments = explode('.', $key);
        $ref = &$this->items;
        foreach ($segments as $segment) {
            if (!isset($ref[$segment]) || !is_array($ref[$segment])) {
                $ref[$segment] = [];
            }
            $ref = &$ref[$segment];
        }
        $ref = $value;
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return $this->items;
    }
}
