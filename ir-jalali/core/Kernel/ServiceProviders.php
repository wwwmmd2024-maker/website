<?php

declare(strict_types=1);

namespace IRJalali\Core\Kernel;

use IRJalali\Core\Auth\Auth;
use IRJalali\Core\Cache\CacheInterface;
use IRJalali\Core\Cache\FileCache;
use IRJalali\Core\Config\Config;
use IRJalali\Core\Database\Connection;
use IRJalali\Core\Database\Database;
use IRJalali\Core\Date\DateService;
use IRJalali\Core\Events\Dispatcher;
use IRJalali\Core\Filesystem\Filesystem;
use IRJalali\Core\Hooks\Hooks;
use IRJalali\Core\Http\Request;
use IRJalali\Core\Http\Router;
use IRJalali\Core\Logging\Logger;
use IRJalali\Core\Security\Csrf;
use IRJalali\Core\Security\Hasher;
use IRJalali\Core\Security\RateLimiter;
use IRJalali\Core\Translation\Translator;
use IRJalali\Core\View\View;

/**
 * Registers every core service into the container.
 * Production code must resolve services from here — never `new` them ad-hoc.
 */
final class ServiceProviders
{
    public static function register(Container $c, Config $config, string $basePath): void
    {
        $c->singleton(Filesystem::class, fn() => new Filesystem($basePath));

        $c->singleton(Logger::class, fn() => new Logger(
            $basePath . '/storage/logs',
            $config->get('app.debug', false) ? 'debug' : 'info'
        ));

        // Cache driver: Redis when configured AND reachable, else file. The
        // platform must run without Redis, so failures fall back gracefully.
        $c->singleton(CacheInterface::class, function () use ($config, $basePath) {
            $ttl = (int) $config->get('cache.default_ttl', 3600);
            if ((string) $config->get('cache.driver', 'file') === 'redis') {
                try {
                    return new \IRJalali\Core\Cache\RedisCache(
                        (string) $config->get('cache.redis_host', '127.0.0.1'),
                        (int) $config->get('cache.redis_port', 6379),
                        $ttl,
                    );
                } catch (\Throwable) {
                    // Unreachable / ext-redis missing → file cache.
                }
            }

            return new FileCache($basePath . '/storage/cache', $ttl);
        });

        $c->singleton(Connection::class, fn() => Connection::fromConfig($config->get('database', [])));

        $c->singleton(Database::class, fn(Container $c) => new Database(
            $c->make(Connection::class),
            $c->make(Logger::class),
        ));

        $c->singleton(Dispatcher::class, fn() => new Dispatcher());
        $c->singleton(Hooks::class, fn() => new Hooks());

        $c->singleton(Translator::class, function () use ($config, $basePath) {
            $t = new Translator($basePath . '/languages', $config->get('app.locale', 'fa_IR'));
            $t->setFallback($config->get('app.fallback_locale', 'en_US'));

            return $t;
        });

        $c->singleton(DateService::class, fn() => new DateService(
            $config->get('app.calendar', 'jalali'),
            $config->get('app.timezone', 'Asia/Tehran'),
        ));

        $c->singleton(Hasher::class, fn() => new Hasher());
        $c->singleton(Csrf::class, fn() => new Csrf());

        $c->singleton(RateLimiter::class, fn(Container $c) => new RateLimiter(
            $c->make(CacheInterface::class)
        ));

        $c->singleton(Auth::class, fn(Container $c) => new Auth(
            $c->make(Database::class),
            $c->make(Hasher::class),
            $c->make(Logger::class),
        ));

        $c->singleton(View::class, fn(Container $c) => new View(
            $basePath . '/app/Views',
            $c->make(Translator::class),
            $c->make(Csrf::class),
            $c->make(DateService::class),
        ));

        $c->singleton(Router::class, fn() => new Router());
        $c->singleton(Request::class, fn() => Request::capture());

        $c->singleton(\IRJalali\Core\Scheduler\Scheduler::class, fn(Container $c) => new \IRJalali\Core\Scheduler\Scheduler(
            $c->make(Database::class),
            $c->make(Hooks::class),
            $c->make(Logger::class),
        ));
        $c->singleton(\IRJalali\Core\Queue\Queue::class, fn(Container $c) => new \IRJalali\Core\Queue\Queue(
            $c->make(Database::class)
        ));

        // ── Part 2: builder ──────────────────────────────────────
        $c->singleton(\IRJalali\Core\Blocks\BlockRegistry::class, fn(Container $c) => new \IRJalali\Core\Blocks\BlockRegistry(
            $c->make(Database::class),
            $c->make(Logger::class),
        ));
        $c->singleton(\IRJalali\Core\Widgets\WidgetRegistry::class, fn(Container $c) => new \IRJalali\Core\Widgets\WidgetRegistry(
            $c->make(Database::class),
            $c->make(Logger::class),
        ));
        $c->singleton(\IRJalali\Core\Builder\ConditionEngine::class, fn() => new \IRJalali\Core\Builder\ConditionEngine());
        $c->singleton(\IRJalali\Core\Builder\DynamicData::class, fn(Container $c) => new \IRJalali\Core\Builder\DynamicData(
            $c->make(Database::class),
            $c->make(\IRJalali\App\Repositories\OptionRepository::class),
        ));
        $c->singleton(\IRJalali\Core\Builder\QueryLoop::class, fn(Container $c) => new \IRJalali\Core\Builder\QueryLoop(
            $c->make(Database::class),
        ));
        $c->singleton(\IRJalali\Core\Builder\GlobalStyles::class, fn(Container $c) => new \IRJalali\Core\Builder\GlobalStyles(
            $c->make(\IRJalali\App\Repositories\OptionRepository::class),
        ));
        $c->singleton(\IRJalali\Core\Builder\Renderer::class, fn(Container $c) => new \IRJalali\Core\Builder\Renderer(
            $c->make(\IRJalali\Core\Builder\ConditionEngine::class),
            $c->make(\IRJalali\Core\Builder\DynamicData::class),
            $c->make(\IRJalali\Core\Builder\QueryLoop::class),
            $c->make(\IRJalali\Core\Blocks\BlockRegistry::class),
            $c->make(\IRJalali\Core\Widgets\WidgetRegistry::class),
            $c->make(Database::class),
            $c->make(Csrf::class),
            $c->make(Auth::class),
        ));

        // ── Part 2: themes ───────────────────────────────────────
        $c->singleton(\IRJalali\Core\Themes\ThemeManager::class, fn(Container $c) => new \IRJalali\Core\Themes\ThemeManager(
            $basePath . '/themes',
            $c->make(\IRJalali\App\Repositories\OptionRepository::class),
            $c->make(Database::class),
            $c->make(Hooks::class),
            $c->make(Logger::class),
        ));

        // ── Part 2: plugins ──────────────────────────────────────
        $c->singleton(\IRJalali\Core\Plugins\HttpClient::class, fn() => new \IRJalali\Core\Plugins\HttpClient());
        $c->singleton(\IRJalali\Core\Plugins\PluginSecurityScanner::class, fn() => new \IRJalali\Core\Plugins\PluginSecurityScanner());
        $c->singleton(\IRJalali\Core\Plugins\PluginManager::class, fn(Container $c) => new \IRJalali\Core\Plugins\PluginManager(
            $c,
            $basePath . '/plugins',
        ));
        $c->singleton(\IRJalali\Core\Plugins\PluginInstaller::class, fn(Container $c) => new \IRJalali\Core\Plugins\PluginInstaller($c));
        $c->singleton(\IRJalali\Core\Plugins\PluginUpdater::class, fn(Container $c) => new \IRJalali\Core\Plugins\PluginUpdater($c));
        $c->singleton(\IRJalali\Core\Plugins\PluginMigrator::class, fn(Container $c) => new \IRJalali\Core\Plugins\PluginMigrator(
            $c->make(Database::class),
        ));

        // ── Part 2: marketplace / licensing / backups ────────────
        $c->singleton(\IRJalali\Core\Marketplace\MarketplaceClient::class, function (Container $c) use ($config, $basePath) {
            $remote = $config->get('marketplace.remote_url');
            $local = $config->get('marketplace.local_path');

            return new \IRJalali\Core\Marketplace\MarketplaceClient(
                $c->make(\IRJalali\Core\Plugins\HttpClient::class),
                is_string($local) && $local !== '' ? $local : $basePath . '/marketplace',
                is_string($remote) && $remote !== '' ? $remote : null,
            );
        });
        $c->singleton(\IRJalali\Core\Licensing\LicenseManager::class, fn(Container $c) => new \IRJalali\Core\Licensing\LicenseManager(
            $c->make(Database::class),
            $c->make(\IRJalali\Core\Marketplace\MarketplaceClient::class),
            (int) $config->get('licensing.grace_days', 14),
            (int) $config->get('licensing.verify_cache_hours', 24),
        ));
        $c->singleton(\IRJalali\Core\Updates\BackupService::class, function () use ($config, $basePath) {
            $path = $config->get('backups.path');

            return new \IRJalali\Core\Updates\BackupService(
                is_string($path) && $path !== '' ? $path : $basePath . '/storage/backups',
                (int) $config->get('backups.keep', 5),
            );
        });

        // ── Part 3: site-wide backups + notification center ──────
        $c->singleton(\IRJalali\Core\Updates\SiteBackupService::class, function (Container $c) use ($config, $basePath) {
            $path = $config->get('backups.path');

            return new \IRJalali\Core\Updates\SiteBackupService(
                $c->make(Database::class),
                $basePath,
                is_string($path) && $path !== '' ? $path : $basePath . '/storage/backups',
                (int) $config->get('backups.keep_site', 10),
            );
        });
        $c->singleton(\IRJalali\App\Services\NotificationService::class, fn (Container $c) => new \IRJalali\App\Services\NotificationService(
            $c->make(Database::class),
            $c->make(Dispatcher::class),
        ));
    }
}
