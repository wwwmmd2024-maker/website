<?php

declare(strict_types=1);

/**
 * Application bootstrap: global helpers, core scheduler jobs,
 * plugin/theme/module loading (engines land in Part 2 — the hook
 * points below are already the official extension API).
 */

use IRJalali\App\Repositories\PostRepository;
use IRJalali\Core\Cache\CacheInterface;
use IRJalali\Core\Kernel\Application;

if (!function_exists('irj')) {
    function irj(string $abstract): mixed
    {
        return Application::get()->make($abstract);
    }
}

if (!function_exists('e')) {
    function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('t')) {
    /** @param array<string, string> $replace */
    function t(string $key, array $replace = []): string
    {
        return irj(\IRJalali\Core\Translation\Translator::class)->get($key, $replace);
    }
}

// Apply DB-backed locale/timezone once installed.
$__app = Application::get();
if ($__app->isInstalled()) {
    try {
        $__db = $__app->make(\IRJalali\Core\Database\Database::class);
        $__locale = $__db->value("SELECT `value` FROM options WHERE `key` = 'language' LIMIT 1");
        $__tz = $__db->value("SELECT `value` FROM options WHERE `key` = 'timezone' LIMIT 1");
        if (is_string($__locale) && $__locale !== '') {
            $__app->make(\IRJalali\Core\Translation\Translator::class)->setLocale($__locale);
        }
        if (is_string($__tz) && $__tz !== '') {
            try {
                date_default_timezone_set($__tz);
            } catch (\Throwable) {
            }
        }
    } catch (\Throwable) {
        // Database unreachable — installer/500 handler will surface it.
    }
}
unset($__app, $__db, $__locale, $__tz);

// Core scheduled jobs (resolved via scheduler.handlers filter).
/** @var \IRJalali\Core\Hooks\Hooks $__hooks */
$__hooks = Application::get()->container()->make(\IRJalali\Core\Hooks\Hooks::class);
$__hooks->addFilter('scheduler.handlers', function (array $handlers): array {
    $handlers['core.publish_scheduled'] = function (): void {
        $count = irj(PostRepository::class)->publishDueScheduled(date('Y-m-d H:i:s'));
        if ($count > 0) {
            irj(\IRJalali\Core\Logging\Logger::class)->channel('scheduler')->info('Published scheduled posts', ['count' => $count]);
        }
    };
    $handlers['core.cache_cleanup'] = function (): void {
        irj(CacheInterface::class)->clearPrefix('ratelimit:');
    };
    $handlers['core.site_backup'] = function (): void {
        try {
            $path = irj(\IRJalali\Core\Updates\SiteBackupService::class)->create('scheduled');
            irj(\IRJalali\Core\Logging\Logger::class)->channel('scheduler')->info('Scheduled site backup created', ['path' => $path]);
        } catch (\Throwable $e) {
            irj(\IRJalali\Core\Logging\Logger::class)->channel('scheduler')->error('Scheduled site backup failed', ['error' => $e->getMessage()]);
        }
    };

    return $handlers;
});
unset($__hooks);

// ── Part 2: theme functions + core blocks/widgets ────────────────────
$__boot = Application::get();
if ($__boot->isInstalled()) {
    try {
        $__themes = $__boot->make(\IRJalali\Core\Themes\ThemeManager::class);
        $__themes->loadFunctions($__themes->activeSlug());
    } catch (\Throwable) {
        // Theme errors must never white-screen the boot process.
    }
    try {
        $__blocks = $__boot->make(\IRJalali\Core\Blocks\BlockRegistry::class);
        \IRJalali\Core\Blocks\CoreBlocks::register($__blocks);
        $__widgets = $__boot->make(\IRJalali\Core\Widgets\WidgetRegistry::class);
        \IRJalali\Core\Widgets\CoreWidgets::register(
            $__widgets,
            $__boot->make(\IRJalali\Core\Database\Database::class),
            $__boot->make(\IRJalali\Core\Security\Csrf::class),
        );
        $__blocks->syncCatalog();
        $__widgets->syncCatalog();
    } catch (\Throwable) {
        // Catalog sync is best-effort at boot.
    }
}
unset($__boot, $__themes, $__blocks, $__widgets);
