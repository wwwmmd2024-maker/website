<?php

declare(strict_types=1);

namespace IRJalali\App\Console\Commands;

use IRJalali\Core\Console\Command;
use IRJalali\Core\Console\Output;
use IRJalali\Core\Plugins\PluginInstaller;

final class PluginInstallCommand extends Command
{
    public function __construct(private readonly PluginInstaller $installer)
    {
    }

    public function name(): string
    {
        return 'plugin:install';
    }

    public function description(): string
    {
        return 'Install a plugin from marketplace, ZIP file or URL (with security scan).';
    }

    public function usage(): string
    {
        return 'plugin:install <slug> [--zip=path] [--url=..] [--version=..] [--activate]';
    }

    /** @param list<string> $args @param array<string, string|bool> $options */
    public function handle(array $args, array $options, Output $out): int
    {
        $slug = $args[0] ?? '';
        $zip = (string) ($this->option($options, 'zip', ''));
        $url = (string) ($this->option($options, 'url', ''));

        if ($zip !== '') {
            if (!is_file($zip)) {
                $out->error("ZIP file not found: {$zip}");
                return 1;
            }
            $result = $this->installer->installFromZip($zip);
        } elseif ($url !== '') {
            $result = $this->installer->installFromUrl($url);
        } else {
            if ($slug === '') {
                $out->error('Provide a marketplace slug, --zip=path or --url=..');
                return 1;
            }
            $version = $this->option($options, 'version');
            $result = $this->installer->installFromMarketplace($slug, is_string($version) ? $version : null);
        }

        foreach ((array) ($result['warnings'] ?? []) as $warning) {
            $out->warning((string) $warning);
        }
        if (empty($result['success'])) {
            $out->error((string) ($result['message'] ?? 'Install failed.'));
            return 1;
        }
        $out->success((string) ($result['message'] ?? ('Installed [' . ($result['slug'] ?? $slug) . '].')));

        if ($this->flag($options, 'activate') && ($result['slug'] ?? '') !== '') {
            $out->info('Run `irj plugin:activate ' . $result['slug'] . '` to activate it.');
        }

        return 0;
    }
}
