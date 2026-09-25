<?php

declare(strict_types=1);

namespace IRJalali\Core\Plugins;

use IRJalali\Core\Blocks\BlockDefinition;
use IRJalali\Core\Blocks\BlockRegistry;
use IRJalali\Core\Database\Database;
use IRJalali\Core\Events\Dispatcher;
use IRJalali\Core\Hooks\Hooks;
use IRJalali\Core\Logging\Logger;
use IRJalali\Core\Scheduler\Scheduler;
use IRJalali\Core\Widgets\WidgetDefinition;
use IRJalali\Core\Widgets\WidgetRegistry;

/**
 * The complete plugin API surface. Every register* method validates input;
 * sensitive services are gated by granted capabilities (fail closed).
 */
final class PluginContext
{
    /** @var list<array{methods: list<string>, path: string, handler: mixed, middleware: list<string>}> */
    private array $routes = [];

    /** @var list<array{slug: string, title: string, icon: string, handler: mixed, permission: string}> */
    private array $adminPages = [];

    /** @var list<array{name: string, description: string, handler: callable}> */
    private array $commands = [];

    /** @var array<string, mixed> */
    private array $settingsSchema = [];

    /** @var list<string> */
    private array $migrationPaths = [];

    /** @param list<string> $granted */
    public function __construct(
        public readonly Plugin $plugin,
        private readonly array $granted,
        private readonly Database $db,
        private readonly Hooks $hooks,
        private readonly Dispatcher $events,
        private readonly BlockRegistry $blocks,
        private readonly WidgetRegistry $widgets,
        private readonly Scheduler $scheduler,
        private readonly Logger $logger,
        private readonly HttpClient $http,
    ) {
    }

    public function can(string $capability): bool
    {
        return in_array($capability, $this->granted, true);
    }

    private function require(string ...$capabilities): void
    {
        foreach ($capabilities as $capability) {
            if (!$this->can($capability)) {
                throw new CapabilityDeniedException("Plugin [{$this->plugin->slug}] lacks capability [{$capability}].");
            }
        }
    }

    // ── Capability-gated services ────────────────────────────────
    public function db(): Database
    {
        $this->require(Capabilities::DATABASE_READ);

        return $this->db;
    }

    public function files(): PluginFiles
    {
        return new PluginFiles($this->plugin->path, $this->granted);
    }

    public function http(): HttpClient
    {
        $this->require(Capabilities::NETWORK_REQUEST);

        return $this->http;
    }

    public function logger(): Logger
    {
        return $this->logger->channel('plugin-' . preg_replace('/[^a-z0-9\-]/i', '', $this->plugin->slug));
    }

    // ── Content model ────────────────────────────────────────────
    /** @param array<string, mixed> $args */
    public function registerPostType(string $slug, array $args = []): void
    {
        $this->require(Capabilities::DATABASE_WRITE);
        if (!preg_match('/^[a-z0-9_\-]{2,40}$/i', $slug)) {
            throw new \InvalidArgumentException("Invalid post type slug [{$slug}].");
        }
        $now = date('Y-m-d H:i:s');
        $settings = (array) ($args['settings'] ?? []);
        $settings['source'] = 'plugin:' . $this->plugin->slug;
        $existing = $this->db->table('post_types')->where('slug', $slug)->first();
        $row = [
            'name' => mb_substr((string) ($args['name'] ?? $slug), 0, 90),
            'icon' => mb_substr((string) ($args['icon'] ?? 'post'), 0, 50),
            'supports' => json_encode(array_slice((array) ($args['supports'] ?? ['title', 'editor']), 0, 20), JSON_UNESCAPED_UNICODE),
            'settings' => json_encode($settings, JSON_UNESCAPED_UNICODE),
            'is_system' => 0,
            'updated_at' => $now,
        ];
        if ($existing === null) {
            $row['slug'] = $slug;
            $row['created_at'] = $now;
            $this->db->insert('post_types', $row);
        } else {
            $this->db->table('post_types')->where('id', $existing['id'])->update($row);
        }
    }

    /** @param array<string, mixed> $args */
    public function registerTaxonomy(string $slug, array $args = []): void
    {
        $this->require(Capabilities::DATABASE_WRITE);
        if (!preg_match('/^[a-z0-9_\-]{2,40}$/i', $slug)) {
            throw new \InvalidArgumentException("Invalid taxonomy slug [{$slug}].");
        }
        $now = date('Y-m-d H:i:s');
        $existing = $this->db->table('taxonomies')->where('slug', $slug)->first();
        $row = [
            'name' => mb_substr((string) ($args['name'] ?? $slug), 0, 90),
            'post_types' => json_encode(array_slice((array) ($args['post_types'] ?? ['post']), 0, 20), JSON_UNESCAPED_UNICODE),
            'hierarchical' => !empty($args['hierarchical']) ? 1 : 0,
            'updated_at' => $now,
        ];
        if ($existing === null) {
            $row['slug'] = $slug;
            $row['created_at'] = $now;
            $this->db->insert('taxonomies', $row);
        } else {
            $this->db->table('taxonomies')->where('id', $existing['id'])->update($row);
        }
    }

    // ── Blocks & widgets ─────────────────────────────────────────
    public function registerBlock(BlockDefinition|array $block): void
    {
        if (is_array($block)) {
            $block = new BlockDefinition(
                slug: (string) ($block['slug'] ?? ''),
                title: (string) ($block['title'] ?? $block['slug'] ?? ''),
                category: (string) ($block['category'] ?? 'general'),
                icon: (string) ($block['icon'] ?? '▣'),
                description: (string) ($block['description'] ?? ''),
                schema: (array) ($block['schema'] ?? []),
                defaults: (array) ($block['defaults'] ?? []),
                render: $block['render'] ?? null,
                assets: (array) ($block['assets'] ?? []),
                supports: (array) ($block['supports'] ?? []),
                permission: $block['permission'] ?? null,
                source: 'plugin',
                version: $this->plugin->version,
            );
        }
        if (!str_starts_with($block->slug, $this->vendorPrefix())) {
            throw new \InvalidArgumentException("Block slug [{$block->slug}] must use vendor prefix [{$this->vendorPrefix()}].");
        }
        $this->blocks->register($block);
    }

    public function registerWidget(WidgetDefinition|array $widget): void
    {
        if (is_array($widget)) {
            $widget = new WidgetDefinition(
                slug: (string) ($widget['slug'] ?? ''),
                title: (string) ($widget['title'] ?? $widget['slug'] ?? ''),
                description: (string) ($widget['description'] ?? ''),
                icon: (string) ($widget['icon'] ?? '◧'),
                schema: (array) ($widget['schema'] ?? []),
                defaults: (array) ($widget['defaults'] ?? []),
                render: $widget['render'] ?? null,
                source: 'plugin',
                version: $this->plugin->version,
            );
        }
        if (!str_starts_with($widget->slug, $this->vendorPrefix())) {
            throw new \InvalidArgumentException("Widget slug [{$widget->slug}] must use vendor prefix [{$this->vendorPrefix()}].");
        }
        $this->widgets->register($widget);
    }

    private function vendorPrefix(): string
    {
        return explode('-', $this->plugin->slug)[0] . '/';
    }

    // ── Routes & API ─────────────────────────────────────────────
    /** @param list<string> $methods @param list<string> $middleware */
    public function registerRoute(array $methods, string $path, mixed $handler, array $middleware = []): void
    {
        $this->routes[] = [
            'methods' => array_map('strtoupper', $methods),
            'path' => '/' . ltrim($path, '/'),
            'handler' => $this->normalizeHandler($handler),
            'middleware' => $middleware,
        ];
    }

    public function registerApiEndpoint(string $method, string $path, mixed $handler, string $ability = 'api.access'): void
    {
        $this->routes[] = [
            'methods' => [strtoupper($method)],
            'path' => '/api/v1/' . trim($path, '/'),
            'handler' => $this->normalizeHandler($handler),
            'middleware' => [
                \IRJalali\App\Middleware\ApiThrottle::class,
                \IRJalali\App\Middleware\ApiAuthenticate::class,
            ],
            'ability' => $ability,
        ];
    }

    /** @return list<array{methods: list<string>, path: string, handler: mixed, middleware: list<string>}> */
    public function collectedRoutes(): array
    {
        return $this->routes;
    }

    // ── Admin ────────────────────────────────────────────────────
    public function registerAdminPage(string $slug, string $title, mixed $handler, string $icon = '◈', string $permission = 'dashboard.view'): void
    {
        $this->require(Capabilities::ADMIN_ACCESS);
        if (!preg_match('/^[a-z0-9_\-]{2,60}$/i', $slug)) {
            throw new \InvalidArgumentException("Invalid admin page slug [{$slug}].");
        }
        $this->adminPages[] = [
            'slug' => $this->plugin->slug . '--' . $slug,
            'title' => mb_substr($title, 0, 80),
            'icon' => mb_substr($icon, 0, 8),
            'handler' => $this->normalizeHandler($handler),
            'permission' => $permission,
            'plugin' => $this->plugin->slug,
        ];
    }

    /** @return list<array{slug: string, title: string, icon: string, handler: mixed, permission: string}> */
    public function collectedAdminPages(): array
    {
        return $this->adminPages;
    }

    /** @param array<string, mixed> $fields field schema list */
    public function registerSettings(array $fields): void
    {
        $this->require(Capabilities::SETTINGS_READ);
        $this->settingsSchema = array_slice($fields, 0, 50);
    }

    /** @return array<string, mixed> */
    public function settingsSchema(): array
    {
        return $this->settingsSchema;
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        $this->require(Capabilities::SETTINGS_READ);
        $row = $this->db->select(
            'SELECT ps.value FROM plugin_settings ps INNER JOIN plugins p ON p.id = ps.plugin_id WHERE p.slug = :s AND ps.key = :k LIMIT 1',
            ['s' => $this->plugin->slug, 'k' => $key]
        );

        return $row[0]['value'] ?? $default;
    }

    public function saveSetting(string $key, mixed $value): void
    {
        $this->require(Capabilities::SETTINGS_WRITE);
        $plugin = $this->db->table('plugins')->where('slug', $this->plugin->slug)->first();
        if ($plugin === null) {
            throw new \RuntimeException('Plugin row missing.');
        }
        $stored = is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE);
        $now = date('Y-m-d H:i:s');
        $existing = $this->db->table('plugin_settings')->where('plugin_id', $plugin['id'])->where('key', $key)->first();
        if ($existing === null) {
            $this->db->insert('plugin_settings', ['plugin_id' => $plugin['id'], 'key' => $key, 'value' => (string) $stored, 'created_at' => $now, 'updated_at' => $now]);
        } else {
            $this->db->table('plugin_settings')->where('id', $existing['id'])->update(['value' => (string) $stored, 'updated_at' => $now]);
        }
    }

    // ── Fields, hooks, events, cron ──────────────────────────────
    /** @param array<string, mixed> $field */
    public function registerField(array $field): void
    {
        $this->require(Capabilities::DATABASE_WRITE);
        $groupSlug = 'plugin:' . $this->plugin->slug;
        $group = $this->db->table('custom_field_groups')->where('title', $groupSlug)->first();
        if ($group === null) {
            $now = date('Y-m-d H:i:s');
            $groupId = $this->db->insert('custom_field_groups', [
                'title' => $groupSlug,
                'location_rules' => json_encode($field['location'] ?? [], JSON_UNESCAPED_UNICODE),
                'ordering' => 0, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now,
            ]);
        } else {
            $groupId = $group['id'];
        }
        $key = (string) ($field['key'] ?? '');
        if (!preg_match('/^[a-z0-9_\-]{2,60}$/i', $key)) {
            throw new \InvalidArgumentException('Invalid field key.');
        }
        $existing = $this->db->table('custom_fields')->where('group_id', $groupId)->where('key', $key)->first();
        $row = [
            'label' => mb_substr((string) ($field['label'] ?? $key), 0, 140),
            'type' => mb_substr((string) ($field['type'] ?? 'text'), 0, 30),
            'settings' => json_encode($field['settings'] ?? [], JSON_UNESCAPED_UNICODE),
            'ordering' => (int) ($field['ordering'] ?? 0),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if ($existing === null) {
            $row['group_id'] = $groupId;
            $row['key'] = $key;
            $row['created_at'] = $row['updated_at'];
            $this->db->insert('custom_fields', $row);
        } else {
            $this->db->table('custom_fields')->where('id', $existing['id'])->update($row);
        }
    }

    public function registerHook(string $hook, callable $callback, int $priority = 10): void
    {
        $this->hooks->addAction($hook, $callback, $priority);
    }

    public function registerFilter(string $hook, callable $callback, int $priority = 10): void
    {
        $this->hooks->addFilter($hook, $callback, $priority);
    }

    public function registerEvent(string $event, callable $listener): void
    {
        $this->events->listen($event, $listener);
    }

    public function registerCron(string $hook, string $name, string $schedule = 'hourly', ?callable $handler = null): void
    {
        $this->scheduler->ensureTask($this->plugin->slug . '.' . $hook, $name, $schedule);
        if ($handler !== null) {
            $fullHook = $this->plugin->slug . '.' . $hook;
            $this->hooks->addFilter('scheduler.handlers', function (array $handlers) use ($fullHook, $handler): array {
                $handlers[$fullHook] = $handler;

                return $handlers;
            });
        }
    }

    public function registerMigration(string $directory): void
    {
        $this->migrationPaths[] = $directory;
    }

    /** @return list<string> */
    public function migrationPaths(): array
    {
        $paths = $this->migrationPaths;
        $default = $this->plugin->path . '/Migrations';
        if (is_dir($default)) {
            $paths[] = $default;
        }

        return array_values(array_unique($paths));
    }

    public function registerCommand(string $name, string $description, callable $handler): void
    {
        if (!preg_match('/^[a-z0-9_\-:]{2,60}$/i', $name)) {
            throw new \InvalidArgumentException("Invalid command name [{$name}].");
        }
        $this->commands[] = ['name' => $name, 'description' => mb_substr($description, 0, 200), 'handler' => $handler];
    }

    /**
     * Normalize any callable into the handler shapes the Router accepts
     * (Closure, or [container-made class-string, method]).
     */
    private function normalizeHandler(mixed $handler): mixed
    {
        if ($handler instanceof \Closure) {
            return $handler;
        }
        if (is_array($handler) && count($handler) === 2 && is_string($handler[0]) && is_string($handler[1])) {
            return $handler;
        }
        if (is_callable($handler)) {
            return \Closure::fromCallable($handler);
        }

        throw new \InvalidArgumentException('Handler must be a Closure, [Class, method] or another callable.');
    }

    /** @return list<array{name: string, description: string, handler: callable}> */
    public function collectedCommands(): array
    {
        return $this->commands;
    }
}
