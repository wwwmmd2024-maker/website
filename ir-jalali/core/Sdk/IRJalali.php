<?php

declare(strict_types=1);

namespace IRJalali\Core\Sdk;

use IRJalali\Core\Blocks\BlockDefinition;
use IRJalali\Core\Blocks\BlockRegistry;
use IRJalali\Core\Builder\RenderContext;
use IRJalali\Core\Builder\Renderer;
use IRJalali\Core\Database\Database;
use IRJalali\Core\Events\Dispatcher;
use IRJalali\Core\Hooks\Hooks;
use IRJalali\Core\Kernel\Application;
use IRJalali\Core\Plugins\Capabilities;
use IRJalali\Core\Plugins\CapabilityDeniedException;
use IRJalali\Core\Plugins\Plugin;
use IRJalali\Core\Plugins\PluginContext;
use IRJalali\Core\Plugins\PluginManager;
use IRJalali\Core\Widgets\WidgetDefinition;
use IRJalali\Core\Widgets\WidgetRegistry;

/**
 * Developer facade. Usage inside a plugin boot():
 *
 *   IRJalali::for('my-plugin')->registerBlock([...]);
 *
 * All extension goes through here — no Core edits allowed.
 */
final class IRJalali
{
    private function __construct(private readonly PluginContext $context)
    {
    }

    public static function for(string $slug): self
    {
        $app = Application::get();
        /** @var PluginManager $manager */
        $manager = $app->make(PluginManager::class);
        $context = $manager->context($slug);
        if ($context === null) {
            throw new \RuntimeException("Plugin context [{$slug}] is not booted.");
        }

        return new self($context);
    }

    /** Raw context for advanced usage. */
    public function context(): PluginContext
    {
        return $this->context;
    }

    public function plugin(): Plugin
    {
        return $this->context->plugin;
    }

    // ── Registrations (delegated) ─────────────────────────────────
    /** @param array<string, mixed> $args */
    public function registerPostType(string $slug, array $args = []): self
    {
        $this->context->registerPostType($slug, $args);

        return $this;
    }

    /** @param array<string, mixed> $args */
    public function registerTaxonomy(string $slug, array $args = []): self
    {
        $this->context->registerTaxonomy($slug, $args);

        return $this;
    }

    public function registerBlock(BlockDefinition|array $block): self
    {
        $this->context->registerBlock($block);

        return $this;
    }

    public function registerWidget(WidgetDefinition|array $widget): self
    {
        $this->context->registerWidget($widget);

        return $this;
    }

    /** @param list<string> $methods @param list<string> $middleware */
    public function registerRoute(array $methods, string $path, mixed $handler, array $middleware = []): self
    {
        $this->context->registerRoute($methods, $path, $handler, $middleware);

        return $this;
    }

    public function registerApiEndpoint(string $method, string $path, mixed $handler, string $ability = 'api.access'): self
    {
        $this->context->registerApiEndpoint($method, $path, $handler, $ability);

        return $this;
    }

    public function registerAdminPage(string $slug, string $title, mixed $handler, string $icon = '◈', string $permission = 'dashboard.view'): self
    {
        $this->context->registerAdminPage($slug, $title, $handler, $icon, $permission);

        return $this;
    }

    /** @param array<string, mixed> $fields */
    public function registerSettings(array $fields): self
    {
        $this->context->registerSettings($fields);

        return $this;
    }

    /** @param array<string, mixed> $field */
    public function registerField(array $field): self
    {
        $this->context->registerField($field);

        return $this;
    }

    public function on(string $hook, callable $callback, int $priority = 10): self
    {
        $this->context->registerHook($hook, $callback, $priority);

        return $this;
    }

    public function filter(string $hook, callable $callback, int $priority = 10): self
    {
        $this->context->registerFilter($hook, $callback, $priority);

        return $this;
    }

    public function listen(string $event, callable $listener): self
    {
        $this->context->registerEvent($event, $listener);

        return $this;
    }

    public function cron(string $hook, string $name, string $schedule = 'hourly', ?callable $handler = null): self
    {
        $this->context->registerCron($hook, $name, $schedule, $handler);

        return $this;
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return $this->context->setting($key, $default);
    }

    public function saveSetting(string $key, mixed $value): self
    {
        $this->context->saveSetting($key, $value);

        return $this;
    }

    public function command(string $name, string $description, callable $handler): self
    {
        $this->context->registerCommand($name, $description, $handler);

        return $this;
    }

    // ── Capability-gated helpers ──────────────────────────────────
    /** Safe read-only query helper (requires database.read). */
    public function query(string $sql, array $bindings = []): array
    {
        if (!$this->context->can(Capabilities::DATABASE_READ)) {
            throw new CapabilityDeniedException('Plugin lacks capability [database.read].');
        }
        if (!preg_match('/^\s*(SELECT|SHOW|DESCRIBE|EXPLAIN)\b/i', $sql)) {
            throw new \InvalidArgumentException('Only read queries are allowed through IRJalali::query().');
        }

        return Application::get()->make(Database::class)->select($sql, $bindings);
    }

    /** Render a builder tree from plugin code (frontend/preview/headless share it). */
    public function render(array $tree, RenderContext $ctx): string
    {
        /** @var Renderer $renderer */
        $renderer = Application::get()->make(Renderer::class);

        return $renderer->render($tree, $ctx)->html;
    }

    /** Render a registered block by slug (failures degrade via the registry). */
    public function renderBlock(string $slug, array $attrs, RenderContext $ctx): string
    {
        /** @var BlockRegistry $blocks */
        $blocks = Application::get()->make(BlockRegistry::class);

        return $blocks->render($slug, $attrs, $ctx);
    }

    /** Render a registered widget by slug. */
    public function renderWidget(string $slug, array $attrs, RenderContext $ctx): string
    {
        /** @var WidgetRegistry $widgets */
        $widgets = Application::get()->make(WidgetRegistry::class);

        return $widgets->render($slug, $attrs, $ctx);
    }

    /** Fire a typed event (plugins can both listen and dispatch). */
    public function dispatch(object $event): object
    {
        return Application::get()->make(Dispatcher::class)->dispatch($event);
    }

    public function doAction(string $hook, mixed ...$args): void
    {
        Application::get()->make(Hooks::class)->doAction($hook, ...$args);
    }

    public function applyFilters(string $hook, mixed $value, mixed ...$args): mixed
    {
        return Application::get()->make(Hooks::class)->applyFilters($hook, $value, ...$args);
    }
}
