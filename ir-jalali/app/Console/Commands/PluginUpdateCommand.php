<?php

declare(strict_types=1);

namespace IRJalali\App\Console\Commands;

use IRJalali\Core\Console\Command;
use IRJalali\Core\Console\Output;
use IRJalali\Core\Plugins\PluginUpdater;

final class PluginUpdateCommand extends Command
{
    public function __construct(private readonly PluginUpdater $updater)
    {
    }

    public function name(): string
    {
        return 'plugin:update';
    }

    public function description(): string
    {
        return 'Update a plugin from the marketplace (with backup + rollback).';
    }

    public function usage(): string
    {
        return 'plugin:update <slug> [--version=..]';
    }

    /** @param list<string> $args @param array<string, string|bool> $options */
    public function handle(array $args, array $options, Output $out): int
    {
        $slug = $args[0] ?? '';
        if ($slug === '') {
            $out->error('Provide a plugin slug.');
            return 1;
        }
        $version = $this->option($options, 'version');
        $result = $this->updater->update($slug, is_string($version) ? $version : null);
        if (empty($result['success'])) {
            $out->error((string) ($result['message'] ?? 'Update failed.'));
            return 1;
        }
        $out->success((string) ($result['message'] ?? 'Updated.'));

        return 0;
    }
}
