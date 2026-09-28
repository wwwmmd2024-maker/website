<?php

declare(strict_types=1);

namespace IRJalali\App\Console\Commands;

use IRJalali\Core\Console\Command;
use IRJalali\Core\Console\Output;
use IRJalali\Core\Plugins\PluginManager;

final class PluginListCommand extends Command
{
    public function __construct(private readonly PluginManager $plugins)
    {
    }

    public function name(): string
    {
        return 'plugin:list';
    }

    public function description(): string
    {
        return 'List all discovered plugins with their status.';
    }

    /** @param list<string> $args @param array<string, string|bool> $options */
    public function handle(array $args, array $options, Output $out): int
    {
        $plugins = $this->plugins->discover();
        if ($plugins === []) {
            $out->info('No plugins found.');
            return 0;
        }
        $rows = [];
        foreach ($plugins as $plugin) {
            $rows[] = [
                $plugin->slug,
                mb_substr($plugin->name, 0, 40),
                $plugin->version,
                $plugin->status,
                mb_substr($plugin->author, 0, 24),
            ];
        }
        $out->table(['Slug', 'Name', 'Version', 'Status', 'Author'], $rows);

        return 0;
    }
}
