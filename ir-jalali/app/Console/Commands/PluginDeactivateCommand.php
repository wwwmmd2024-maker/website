<?php

declare(strict_types=1);

namespace IRJalali\App\Console\Commands;

use IRJalali\Core\Console\Command;
use IRJalali\Core\Console\Output;
use IRJalali\Core\Plugins\PluginManager;

final class PluginDeactivateCommand extends Command
{
    public function __construct(private readonly PluginManager $plugins)
    {
    }

    public function name(): string
    {
        return 'plugin:deactivate';
    }

    public function description(): string
    {
        return 'Deactivate a plugin (data is kept).';
    }

    public function usage(): string
    {
        return 'plugin:deactivate <slug>';
    }

    /** @param list<string> $args @param array<string, string|bool> $options */
    public function handle(array $args, array $options, Output $out): int
    {
        $slug = $args[0] ?? '';
        if ($slug === '') {
            $out->error('Provide a plugin slug.');
            return 1;
        }
        $this->plugins->deactivate($slug);
        $out->success("Plugin [{$slug}] deactivated.");

        return 0;
    }
}
