<?php

declare(strict_types=1);

namespace IRJalali\Core\Plugins;

use IRJalali\Core\Blocks\BlockRegistry;
use IRJalali\Core\Config\Config;
use IRJalali\Core\Database\Database;
use IRJalali\Core\Events\Dispatcher;
use IRJalali\Core\Hooks\Hooks;
use IRJalali\Core\Kernel\Container;
use IRJalali\Core\Logging\Logger;
use IRJalali\Core\Scheduler\Scheduler;
use IRJalali\Core\Version\Compatibility;
use IRJalali\Core\Widgets\WidgetRegistry;

/**
 * Discovers, boots, activates, deactivates, and uninstalls plugins.
 */
final class PluginManager
{
    private const string REGISTRY = 'plugin.booted';

    /** @var array<string, Plugin> */
    private array $plugins = [];

    /** @var array<string, list<string>> slug => granted capabilities (DB) */
    private array $grantedCaps = [];

    /** @var array<string, PluginServiceProvider> */
    private array $providers = [];

    /** @var array<string, PluginContext> */
    private array $contexts = [];

    /** @var array<string, callable> */
    private array $adminPageHandlers = [];

    /** @var array<string, string> Admin page slug => permission */
    private array $adminPagePermissions = [];

    /** @var list<array{methods: list<string>, path: string, handler: mixed, middleware: list<string>}> */
    private array $routes = [];

    /** @var array<string, array{name: string, description: string, handler: callable, plugin: string}> */
    private array $commands = [];

    public function __construct(
        private readonly Container $app,
        private readonly string $pluginsPath,
    ) {
    }

    /**
     * Discover plugins from disk (plugins/) merged with DB status rows.
     *
     * @return array<string, Plugin>
     */
    public function discover(): array
    {
        /** @var Database $db */
        $db = $this->app->make(Database::class);
        $rows = [];
        try {
            foreach ($db->table('plugins')->orderBy('slug')->get() as $row) {
                $rows[$row['slug']] = $row;
            }
        } catch (\Throwable) {
            // Installer phase — no plugins table yet.
        }

        $manifests = [];
        if (is_dir($this->pluginsPath)) {
            foreach (scandir($this->pluginsPath) ?: [] as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }
                $manifestPath = $this->pluginsPath . '/' . $entry . '/plugin.json';
                if (is_file($manifestPath)) {
                    $manifests[$entry] = $manifestPath;
                }
            }
        }

        $this->plugins = [];
        $this->grantedCaps = [];
        foreach ($manifests as $dir => $manifestPath) {
            try {
                $manifest = PluginManifestLoader::load($manifestPath);
            } catch (\Throwable $e) {
                $this->app->make(Logger::class)->error('plugin.manifest', ['dir' => $dir, 'error' => $e->getMessage()]);
                continue;
            }
            $slug = (string) $manifest['slug'];
            $row = $rows[$slug] ?? null;
            $status = (string) ($row['status'] ?? 'inactive');
            $status = $status === 'active' ? 'active' : 'inactive';
            $this->plugins[$slug] = Plugin::fromManifest($slug, dirname($manifestPath), $manifest, $status);
            $granted = json_decode((string) ($row['granted_capabilities'] ?? '[]'), true);
            $this->grantedCaps[$slug] = is_array($granted) ? array_values($granted) : [];
        }

        return $this->plugins;
    }

    /** @return list<string> */
    public function granted(string $slug): array
    {
        if ($this->plugins === []) {
            $this->discover();
        }

        return $this->grantedCaps[$slug] ?? [];
    }

    /** All discovered plugins (disk ∪ database rows). */
    public function all(): array
    {
        return $this->plugins !== [] ? $this->plugins : $this->discover();
    }

    public function plugin(string $slug): ?Plugin
    {
        return $this->all()[$slug] ?? null;
    }

    public function isMustUse(Plugin $plugin): bool
    {
        if (!empty($plugin->manifest['must_use'])) {
            return true;
        }
        $configured = $this->app->make(Config::class)->get('plugins.must_use', []);

        return is_array($configured) && in_array($plugin->slug, $configured, true);
    }

    /** Boot all active (+ must-use) plugins, once per request, before routing. */
    public function boot(): void
    {
        if ($this->app->has(self::REGISTRY) && $this->app->make(self::REGISTRY) === true) {
            return;
        }
        $this->app->instance(self::REGISTRY, true);

        if ($this->plugins === []) {
            $this->discover();
        }

        $bootable = array_filter(
            $this->plugins,
            fn (Plugin $p): bool => $p->isActive() || $this->isMustUse($p)
        );
        foreach ($this->resolveOrder($bootable) as $plugin) {
            $this->bootOne($plugin);
        }

        $this->syncRegistriesIfStale();
    }

    /** @return array<string, PluginContext> */
    public function contexts(): array
    {
        return $this->contexts;
    }

    public function context(string $slug): ?PluginContext
    {
        return $this->contexts[$slug] ?? null;
    }

    /** @return list<array{methods: list<string>, path: string, handler: mixed, middleware: list<string>}> */
    public function routes(): array
    {
        return $this->routes;
    }

    public function adminPage(string $slug): ?callable
    {
        return $this->adminPageHandlers[$slug] ?? null;
    }

    public function adminPagePermission(string $slug): ?string
    {
        return $this->adminPagePermissions[$slug] ?? null;
    }

    /** @return list<array{slug: string, title: string, icon: string, permission: string}> */
    public function adminPages(): array
    {
        $pages = [];
        foreach ($this->contexts as $context) {
            foreach ($context->collectedAdminPages() as $page) {
                $pages[] = [
                    'slug' => $page['slug'],
                    'title' => $page['title'],
                    'icon' => $page['icon'],
                    'permission' => $page['permission'],
                ];
            }
        }

        return $pages;
    }

    /** @return array<string, array{name: string, description: string, handler: callable, plugin: string}> */
    public function commands(): array
    {
        return $this->commands;
    }

    public function activate(string $slug, array $granted): void
    {
        $plugin = $this->get($slug);
        if ($plugin->isActive()) {
            return;
        }
        $granted = array_values(array_intersect($granted, $plugin->capabilities));

        /** @var PluginMigrator $migrator */
        $migrator = $this->app->make(PluginMigrator::class);
        $migrator->migrate($plugin);

        /** @var Database $db */
        $db = $this->app->make(Database::class);
        $this->ensureRow($db, $plugin, $granted);

        $context = $this->makeContext($plugin, $granted);
        $provider = $this->provider($plugin);
        $provider->setContext($context);
        $provider->activate();

        $this->discover(); // refresh status
        $this->bootOne($this->get($slug)); // register blocks/routes in-process
        $this->syncRegistries();
    }

    public function deactivate(string $slug): void
    {
        $plugin = $this->get($slug);
        if (!$plugin->isActive()) {
            return;
        }
        if ($this->isMustUse($plugin)) {
            throw new \RuntimeException('Must-use plugins cannot be deactivated.');
        }
        try {
            $provider = $this->provider($plugin);
            $context = $this->contexts[$slug] ?? $this->makeContext($plugin, $this->grantedCaps[$slug] ?? []);
            $provider->setContext($context);
            $provider->deactivate();
        } catch (\Throwable $e) {
            $this->app->make(Logger::class)->error('plugin.deactivate', ['slug' => $slug, 'error' => $e->getMessage()]);
        }

        /** @var Database $db */
        $db = $this->app->make(Database::class);
        $db->table('plugins')->where('slug', $slug)->update(['status' => 'inactive', 'updated_at' => date('Y-m-d H:i:s')]);
        $this->discover(); // refresh status
    }

    public function uninstall(string $slug): void
    {
        $plugin = $this->get($slug);
        if ($plugin->isActive()) {
            $this->deactivate($slug);
        }
        if ($this->isMustUse($plugin)) {
            throw new \RuntimeException('Must-use plugins cannot be uninstalled.');
        }
        try {
            $provider = $this->provider($plugin);
            $provider->setContext($this->makeContext($plugin, $this->grantedCaps[$slug] ?? []));
            $provider->uninstall();
        } catch (\Throwable $e) {
            $this->app->make(Logger::class)->error('plugin.uninstall', ['slug' => $slug, 'error' => $e->getMessage()]);
        }

        /** @var Database $db */
        $db = $this->app->make(Database::class);
        $row = $db->table('plugins')->where('slug', $slug)->first();
        if ($row !== null) {
            $db->delete('plugin_settings', 'plugin_id = :id', ['id' => $row['id']]);
            $db->delete('plugin_migrations', 'plugin_slug = :s', ['s' => $slug]);
            $db->table('plugins')->where('id', $row['id'])->delete();
        }

        /** @var BlockRegistry $blocks */
        $blocks = $this->app->make(BlockRegistry::class);
        $blocks->unregisterByVendor(explode('-', $slug)[0]);
        /** @var WidgetRegistry $widgets */
        $widgets = $this->app->make(WidgetRegistry::class);
        $widgets->unregisterByVendor(explode('-', $slug)[0]);
    }

    private function get(string $slug): Plugin
    {
        if ($this->plugins === []) {
            $this->discover();
        }
        if (!isset($this->plugins[$slug])) {
            throw new \RuntimeException("Plugin [{$slug}] not found.");
        }

        return $this->plugins[$slug];
    }

    private function bootOne(Plugin $plugin): void
    {
        // Must-use plugins auto-receive their declared capabilities.
        $granted = $this->isMustUse($plugin) && !$plugin->isActive()
            ? $plugin->capabilities
            : ($this->grantedCaps[$plugin->slug] ?? []);
        $context = $this->makeContext($plugin, $granted);
        // Stored BEFORE boot() so IRJalali::for() works inside boot().
        $this->contexts[$plugin->slug] = $context;
        try {
            $provider = $this->provider($plugin);
            $provider->setContext($context);
            $provider->boot($context);
            $this->providers[$plugin->slug] = $provider;

            foreach ($context->collectedRoutes() as $route) {
                $this->routes[] = $route;
            }
            foreach ($context->collectedAdminPages() as $page) {
                $this->adminPageHandlers[$page['slug']] = $this->resolveAdminHandler($page['handler']);
                $this->adminPagePermissions[$page['slug']] = $page['permission'];
            }
            foreach ($context->collectedCommands() as $command) {
                $this->commands[$command['name']] = [...$command, 'plugin' => $plugin->slug];
            }
        } catch (\Throwable $e) {
            unset($this->contexts[$plugin->slug], $this->providers[$plugin->slug]);
            $this->app->make(Logger::class)->error('plugin.boot', ['slug' => $plugin->slug, 'error' => $e->getMessage()]);
        }
    }

    private function makeContext(Plugin $plugin, array $granted): PluginContext
    {
        return new PluginContext(
            plugin: $plugin,
            granted: array_values(array_intersect($granted, Capabilities::all())),
            db: $this->app->make(Database::class),
            hooks: $this->app->make(Hooks::class),
            events: $this->app->make(Dispatcher::class),
            blocks: $this->app->make(BlockRegistry::class),
            widgets: $this->app->make(WidgetRegistry::class),
            scheduler: $this->app->make(Scheduler::class),
            logger: $this->app->make(Logger::class),
            http: $this->app->make(HttpClient::class),
        );
    }

    private function resolveAdminHandler(mixed $handler): callable
    {
        if ($handler instanceof \Closure) {
            return $handler;
        }
        if (is_array($handler) && count($handler) === 2) {
            $instance = $this->app->make($handler[0]);
            $method = $handler[1];

            return fn (mixed $request): mixed => $instance->{$method}($request);
        }

        return \Closure::fromCallable($handler);
    }

    private function syncRegistriesIfStale(): void
    {
        try {
            /** @var BlockRegistry $blocks */
            $blocks = $this->app->make(BlockRegistry::class);
            /** @var WidgetRegistry $widgets */
            $widgets = $this->app->make(WidgetRegistry::class);
            /** @var Database $db */
            $db = $this->app->make(Database::class);
            $storedBlocks = array_column($db->table('blocks')->select(['slug'])->get(), 'slug');
            $storedWidgets = array_column($db->table('widgets')->select(['slug'])->get(), 'slug');
            if (array_diff(array_keys($blocks->all()), $storedBlocks) !== []
                || array_diff(array_keys($widgets->all()), $storedWidgets) !== []) {
                $blocks->syncCatalog();
                $widgets->syncCatalog();
            }
        } catch (\Throwable) {
            // Best effort; catalog sync must never break a request.
        }
    }

    private function provider(Plugin $plugin): PluginServiceProvider
    {
        $class = $plugin->className();
        if (!class_exists($class)) {
            throw new \RuntimeException("Plugin entry class [{$class}] not found.");
        }
        $provider = new $class($plugin);
        if (!$provider instanceof PluginServiceProvider) {
            throw new \RuntimeException("Plugin entry [{$class}] must extend PluginServiceProvider.");
        }

        return $provider;
    }

    /**
     * Dependency-aware boot order with semver constraint checks.
     * Missing/unsatisfied dependencies keep the plugin unbooted.
     *
     * @param  array<string, Plugin> $plugins
     * @return array<string, Plugin>
     */
    private function resolveOrder(array $plugins): array
    {
        $ordered = [];
        $pending = $plugins;
        $logger = $this->app->make(Logger::class);
        $guard = count($pending) + 1;
        while ($pending !== [] && $guard-- > 0) {
            $progress = false;
            foreach ($pending as $slug => $plugin) {
                $deps = array_filter(
                    $plugin->dependencies,
                    fn (string $constraint, string $dep): bool => !in_array($dep, ['php', 'core', 'mysql'], true),
                    ARRAY_FILTER_USE_BOTH
                );
                $ready = true;
                foreach ($deps as $dep => $constraint) {
                    $depPlugin = $ordered[$dep] ?? $pending[$dep] ?? null;
                    if ($depPlugin === null || isset($pending[$dep])) {
                        $ready = false; // missing, or not ordered yet
                        break;
                    }
                    if ($constraint !== '*' && $constraint !== '' && !Compatibility::satisfies($depPlugin->version, $constraint)) {
                        $logger->error('plugin.deps', ['slug' => $slug, 'error' => "Dependency [{$dep}] version [{$depPlugin->version}] does not satisfy [{$constraint}]."]);
                        unset($pending[$slug]);
                        $ready = false;
                        break;
                    }
                }
                if ($ready) {
                    $ordered[$slug] = $plugin;
                    unset($pending[$slug]);
                    $progress = true;
                }
            }
            if (!$progress) {
                foreach ($pending as $slug => $plugin) {
                    $logger->error('plugin.deps', ['slug' => $slug, 'error' => 'Unresolved dependencies, skipped.']);
                }
                break;
            }
        }

        return $ordered;
    }

    private function ensureRow(Database $db, Plugin $plugin, array $granted): void
    {
        $now = date('Y-m-d H:i:s');
        $row = $db->table('plugins')->where('slug', $plugin->slug)->first();
        $data = [
            'name' => $plugin->name,
            'version' => $plugin->version,
            'author' => $plugin->author !== '' ? $plugin->author : null,
            'status' => 'active',
            'dependencies' => json_encode($plugin->dependencies, JSON_UNESCAPED_UNICODE),
            'granted_capabilities' => json_encode(array_values($granted), JSON_UNESCAPED_UNICODE),
            'updated_at' => $now,
        ];
        if ($row === null) {
            $db->insert('plugins', ['slug' => $plugin->slug, ...$data, 'created_at' => $now]);
        } else {
            $db->table('plugins')->where('id', $row['id'])->update($data);
        }
    }

    private function syncRegistries(): void
    {
        try {
            $this->app->make(BlockRegistry::class)->syncCatalog();
            $this->app->make(WidgetRegistry::class)->syncCatalog();
        } catch (\Throwable) {
            // Non-fatal: catalog sync failure must not break activation.
        }
    }
}
