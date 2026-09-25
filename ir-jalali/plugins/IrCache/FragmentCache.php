<?php

declare(strict_types=1);

namespace IRJalali\Plugins\IrCache;

use IRJalali\Core\Cache\CacheInterface;
use IRJalali\Core\Kernel\Application;

/**
 * Fragment cache for themes/plugins (Part 1 §19).
 * Usage: FragmentCache::render('home.hero', 300, fn() => expensiveHtml());
 * Works on any configured cache driver — no Redis required.
 */
final class FragmentCache
{
    private const PREFIX = 'fragment:';

    public static function render(string $key, int $ttl, callable $producer): string
    {
        $safeKey = self::PREFIX . preg_replace('/[^a-z0-9_.\-]/i', '', $key);
        try {
            /** @var CacheInterface $cache */
            $cache = Application::get()->make(CacheInterface::class);
            $cached = $cache->get($safeKey);
            if (is_string($cached)) {
                return $cached;
            }
            $html = (string) $producer();
            if ($html !== '') {
                $cache->set($safeKey, $html, max(10, $ttl));
            }

            return $html;
        } catch (\Throwable) {
            return (string) $producer();
        }
    }

    public static function forget(string $key): void
    {
        try {
            Application::get()->make(CacheInterface::class)
                ->delete(self::PREFIX . preg_replace('/[^a-z0-9_.\-]/i', '', $key));
        } catch (\Throwable) {
        }
    }

    public static function flush(): int
    {
        try {
            return Application::get()->make(CacheInterface::class)->clearPrefix(self::PREFIX);
        } catch (\Throwable) {
            return 0;
        }
    }
}
