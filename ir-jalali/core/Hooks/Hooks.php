<?php

declare(strict_types=1);

namespace IRJalali\Core\Hooks;

/**
 * WordPress-style action/filter hooks — the PRIMARY extension point.
 * Plugins, themes and modules extend the platform ONLY through here
 * (plus events, contracts and the REST API). Core is never edited.
 */
final class Hooks
{
    /** @var array<string, array<int, list<callable>>> */
    private array $actions = [];

    /** @var array<string, array<int, list<callable>>> */
    private array $filters = [];

    public function addAction(string $hook, callable $callback, int $priority = 10): void
    {
        $this->actions[$hook][$priority][] = $callback;
    }

    public function doAction(string $hook, mixed ...$args): void
    {
        foreach ($this->sorted($this->actions[$hook] ?? []) as $callback) {
            $callback(...$args);
        }
    }

    public function addFilter(string $hook, callable $callback, int $priority = 10): void
    {
        $this->filters[$hook][$priority][] = $callback;
    }

    public function applyFilters(string $hook, mixed $value, mixed ...$args): mixed
    {
        foreach ($this->sorted($this->filters[$hook] ?? []) as $callback) {
            $value = $callback($value, ...$args);
        }

        return $value;
    }

    public function hasAction(string $hook): bool
    {
        return !empty($this->actions[$hook]);
    }

    public function hasFilter(string $hook): bool
    {
        return !empty($this->filters[$hook]);
    }

    /** @param array<int, list<callable>> $grouped @return list<callable> */
    private function sorted(array $grouped): array
    {
        ksort($grouped);
        $out = [];
        foreach ($grouped as $callbacks) {
            array_push($out, ...$callbacks);
        }

        return $out;
    }
}
