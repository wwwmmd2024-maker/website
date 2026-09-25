<?php

declare(strict_types=1);

namespace IRJalali\App\Console\Commands;

use IRJalali\App\Services\InstallerService;
use IRJalali\Core\Console\Command;
use IRJalali\Core\Console\Output;
use IRJalali\Core\Kernel\Application;

final class InstallCommand extends Command
{
    public function __construct(
        private readonly Application $app,
        private readonly InstallerService $installer,
    ) {
    }

    public function name(): string
    {
        return 'install';
    }

    public function description(): string
    {
        return 'Install IR-Jalali (writes .env, runs migrations, seeds, creates admin).';
    }

    public function usage(): string
    {
        return 'install [--db-host=..] [--db-port=..] [--db-name=..] [--db-user=..] [--db-pass=..]'
            . ' [--admin-user=..] [--admin-email=..] [--admin-pass=..] [--title=..] [--url=..] [--lang=..] [--timezone=..]';
    }

    /** @param list<string> $args @param array<string, string|bool> $options */
    public function handle(array $args, array $options, Output $out): int
    {
        if ($this->app->isInstalled()) {
            $out->error('Already installed (storage/install.lock exists). Remove the lock to reinstall.');
            return 1;
        }

        foreach ($this->installer->requirements() as $check => $passed) {
            if (!$passed) {
                $out->error("Requirement failed: {$check}");
                return 1;
            }
        }

        $str = fn (string $key, string $prompt, string $default = ''): string =>
            (string) ($this->option($options, $key) ?? $out->ask($prompt, $default));

        $db = [
            'host' => $str('db-host', 'Database host', '127.0.0.1'),
            'port' => $str('db-port', 'Database port', '3306'),
            'database' => $str('db-name', 'Database name'),
            'username' => $str('db-user', 'Database username'),
            'password' => (string) ($this->option($options, 'db-pass') ?? $out->secret('Database password')),
        ];
        if ($db['database'] === '' || $db['username'] === '') {
            $out->error('Database name and username are required.');
            return 1;
        }
        if (!$this->installer->testConnection($db)) {
            $out->error('Could not connect to the database with the given credentials.');
            return 1;
        }
        $out->success('Database connection OK.');

        $admin = [
            'username' => $str('admin-user', 'Admin username', 'admin'),
            'email' => $str('admin-email', 'Admin email', 'admin@example.com'),
            'password' => (string) ($this->option($options, 'admin-pass') ?? $out->secret('Admin password (min 8 chars)')),
        ];
        if (strlen($admin['password']) < 8) {
            $out->error('Admin password must be at least 8 characters.');
            return 1;
        }
        $site = [
            'title' => $str('title', 'Site title', 'سایت من'),
            'tagline' => '',
            'language' => $str('lang', 'Language (fa_IR/en_US)', 'fa_IR'),
            'timezone' => $str('timezone', 'Timezone', 'Asia/Tehran'),
            'url' => $str('url', 'Site URL', 'http://localhost'),
        ];

        $result = $this->installer->install($db, $admin, $site);
        $out->success('Installed. Admin panel: ' . ($result['admin_url'] ?? '/admin'));

        return 0;
    }
}
