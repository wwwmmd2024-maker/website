<?php

declare(strict_types=1);

namespace IRJalali\App\Console\Commands;

use IRJalali\Core\Console\Command;
use IRJalali\Core\Console\Output;
use IRJalali\Core\Kernel\Application;
use IRJalali\Core\Plugins\PluginManager;

final class PluginRemoveCommand extends Command
{
    public function __construct(
        private readonly Application $app,
        private readonly PluginManager $plugins,
    ) {
    }

    public function name(): string
    {
        return 'plugin:remove';
    }

    public function description(): string
    {
        return 'Uninstall a plugin (wipes its data) and delete its files. Use --keep-files to keep files.';
    }

    public function usage(): string
    {
        return 'plugin:remove <slug> [--yes] [--keep-files]';
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
        if (!$this->flag($options, 'yes') && !$out->confirm("Uninstall [{$slug}] and wipe its data?", false)) {
            $out->info('Aborted.');
            return 1;
        }
        $path = $plugin->path;
        $this->plugins->uninstall($slug);
        $out->success("Plugin [{$slug}] uninstalled.");

        if ($this->flag($options, 'keep-files')) {
            return 0;
        }
        $pluginsDir = realpath($this->app->basePath('plugins'));
        $target = realpath($path);
        if ($pluginsDir === false || $target === false || !str_starts_with($target, $pluginsDir . DIRECTORY_SEPARATOR)) {
            $out->warning('Files kept: plugin path is outside the plugins directory.');
            return 0;
        }
        $this->deleteDir($target);
        $out->success('Plugin files deleted.');

        return 0;
    }

    private function deleteDir(string $dir): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $entry) {
            $path = $entry->getPathname();
            if ($entry->isDir() && !$entry->isLink()) {
                @rmdir($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }
}
