<?php

declare(strict_types=1);

namespace IRJalali\Core\Plugins;

/**
 * Base class for every plugin's entry point
 * (IRJalali\Plugins\<Studly>\Plugin extends this).
 */
abstract class PluginServiceProvider
{
    protected PluginContext $context;

    public function __construct(protected Plugin $plugin)
    {
    }

    /** Register blocks/routes/pages/hooks (runs on every request). */
    abstract public function boot(PluginContext $context): void;

    /** Runs once on activation (after migrations). */
    public function activate(): void
    {
    }

    /** Runs once on deactivation. Data is kept. */
    public function deactivate(): void
    {
    }

    /** Runs on uninstall (after confirmation). MAY wipe plugin data. */
    public function uninstall(): void
    {
    }

    public function setContext(PluginContext $context): void
    {
        $this->context = $context;
    }

    /** Shortcuts usable inside activate/deactivate/uninstall. */
    protected function context(): PluginContext
    {
        return $this->context;
    }
}
