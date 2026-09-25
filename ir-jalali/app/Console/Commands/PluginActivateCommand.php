<?php

declare(strict_types=1);

namespace IRJalali\App\Console\Commands;

use IRJalali\Core\Console\Command;
use IRJalali\Core\Console\Output;
use IRJalali\Core\Plugins\PluginManager;

final class PluginActivateCommand extends Command
{
    public function __construct(private readonly PluginManager $plugins)
    {
    }

    public function name(): string
    {
        return 'plugin:activate';
    }

    public function description(): string
    {
        return 'Activate a plugin (runs its migrations, grants requested capabilities after consent).';
    }

    public function usage(): string
    {
        return 'plugin:activate <slug> [--yes]';
    }

    /** @param list<string> $args @param array<string, string|bool> $options */
    public function handle(array $args, array $options, Output $out): int
    {
        $slug = $args[0] ?? '';
        if ($slug === '') {
            $out->error('Provide a plugin slug.');
            return 1;
        }
        $plugins = $this->plugins->discover();
        $plugin = $plugins[$slug] ?? null;
        if ($plugin === null) {
            $out->error("Plugin [{$slug}] not found.");
            return 1;
        }
        if ($plugin->isActive()) {
            $out->info("Plugin [{$slug}] is already active.");
            return 0;
        }
        if ($plugin->capabilities !== [] && !$this->flag($options, 'yes')) {
            $out->warning('This plugin requests capabilities: ' . implode(', ', $plugin->capabilities));
            if (!$out->confirm('Grant them and activate?', false)) {
                $out->info('Aborted.');
                return 1;
            }
        }
        $this->plugins->activate($slug, $plugin->capabilities);
        $out->success("Plugin [{$slug}] activated.");

        return 0;
    }
}
