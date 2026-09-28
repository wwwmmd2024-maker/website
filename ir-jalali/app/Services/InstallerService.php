<?php

declare(strict_types=1);

namespace IRJalali\App\Services;

use IRJalali\Core\Database\Connection;
use IRJalali\Core\Database\Database;
use IRJalali\Core\Database\MigrationRunner;
use IRJalali\Core\Filesystem\Filesystem;
use IRJalali\Core\Logging\Logger;
use IRJalali\Database\Seeds\CoreSeeder;

/**
 * Runs every installer step: requirements, DB test, .env writing,
 * migrations, seeding, admin creation and locking.
 */
final class InstallerService
{
    public function __construct(
        private readonly Filesystem $fs,
        private readonly Logger $logger,
    ) {
    }

    /** @return array{ok: bool, checks: list<array{label: string, ok: bool, detail: string}>} */
    public function requirements(): array
    {
        $checks = [];
        $phpOk = version_compare(PHP_VERSION, '8.2.0', '>=');
        $checks[] = ['label' => 'نسخه PHP (حداقل 8.2)', 'ok' => $phpOk, 'detail' => PHP_VERSION];

        foreach (['pdo', 'pdo_mysql', 'mbstring', 'json', 'fileinfo'] as $ext) {
            $loaded = extension_loaded($ext);
            $checks[] = ['label' => "اکستنشن {$ext}", 'ok' => $loaded, 'detail' => $loaded ? 'فعال' : 'غیرفعال'];
        }
        foreach (['curl', 'gd', 'intl', 'zip'] as $ext) {
            $loaded = extension_loaded($ext);
            $checks[] = ['label' => "اکستنشن {$ext} (اختیاری)", 'ok' => true, 'detail' => $loaded ? 'فعال' : 'غیرفعال — برخی قابلیت‌ها محدود می‌شوند'];
        }

        foreach (['storage', 'storage/logs', 'storage/cache', 'storage/uploads'] as $dir) {
            $path = $this->fs->path($dir);
            $writable = is_dir($path) && is_writable($path);
            $checks[] = ['label' => "قابل نوشتن بودن {$dir}", 'ok' => $writable, 'detail' => $writable ? 'بله' : 'خیر — سطح دسترسی را بررسی کنید'];
        }

        $envPath = $this->fs->path('.env');
        $envWritable = !file_exists($envPath) || is_writable($envPath);
        if (!file_exists($envPath)) {
            $envWritable = is_writable(dirname($envPath));
        }
        $checks[] = ['label' => 'امکان ساخت فایل .env', 'ok' => $envWritable, 'detail' => $envWritable ? 'بله' : 'خیر'];

        $ok = true;
        foreach ($checks as $check) {
            if (str_contains($check['label'], '(اختیاری)')) {
                continue;
            }
            if (!$check['ok']) {
                $ok = false;
                break;
            }
        }

        return ['ok' => $ok, 'checks' => $checks];
    }

    /** @param array<string, mixed> $input */
    public function testConnection(array $input): bool
    {
        return Connection::test([
            'driver' => 'mysql',
            'host' => $input['host'] ?? '127.0.0.1',
            'port' => (int) ($input['port'] ?? 3306),
            'database' => $input['database'] ?? '',
            'username' => $input['username'] ?? '',
            'password' => $input['password'] ?? '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ]);
    }

    /**
     * Full installation run. Throws on failure (transactional where possible).
     *
     * @param array<string, mixed> $db
     * @param array<string, mixed> $admin
     * @param array<string, mixed> $site
     * @return array{admin_url: string}
     */
    public function install(array $db, array $admin, array $site): array
    {
        $this->writeEnv($db, $site);

        $connection = Connection::fromConfig([
            'driver' => 'mysql',
            'host' => $db['host'],
            'port' => (int) $db['port'],
            'database' => $db['database'],
            'username' => $db['username'],
            'password' => $db['password'] ?? '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ]);
        $database = new Database($connection, $this->logger);

        $migrated = (new MigrationRunner($database, $this->logger))
            ->run([$this->fs->path('database/Migrations')]);
        $this->logger->channel('install')->info('Migrations executed', ['count' => $migrated]);

        (new CoreSeeder($database))->run();

        // Site row + options.
        $now = date('Y-m-d H:i:s');
        $database->table('sites')->where('is_default', 1)->update([
            'name' => $site['title'],
            'locale' => $site['language'],
            'timezone' => $site['timezone'],
            'updated_at' => $now,
        ]);
        $options = [
            'site_title' => $site['title'],
            'tagline' => $site['tagline'] ?? '',
            'language' => $site['language'],
            'timezone' => $site['timezone'],
            'website_mode' => 'general',
            'website_type' => 'custom',
            'setup_completed' => '0',
        ];
        foreach ($options as $key => $value) {
            $database->insert('options', [
                'key' => $key,
                'value' => (string) $value,
                'autoload' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Admin user.
        $hasher = new \IRJalali\Core\Security\Hasher();
        $userId = $database->insert('users', [
            'uuid' => $this->uuid(),
            'username' => $admin['username'],
            'email' => $admin['email'],
            'password_hash' => $hasher->hash($admin['password']),
            'display_name' => $admin['display_name'] ?? $admin['username'],
            'status' => 'active',
            'locale' => $site['language'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $role = $database->table('roles')->where('slug', 'super_admin')->first();
        $database->insert('user_roles', ['user_id' => $userId, 'role_id' => $role['id']]);

        // Core scheduled tasks.
        foreach ([
            ['name' => 'انتشار نوشته‌های زمان‌بندی‌شده', 'hook' => 'core.publish_scheduled', 'schedule' => 'minutely'],
            ['name' => 'پاک‌سازی کش', 'hook' => 'core.cache_cleanup', 'schedule' => 'daily'],
        ] as $task) {
            $database->insert('scheduled_tasks', [
                'name' => $task['name'],
                'hook' => $task['hook'],
                'schedule' => $task['schedule'],
                'payload_json' => '[]',
                'next_run_at' => $now,
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $this->logger->channel('install')->info('Installation completed', ['admin' => $admin['username']]);
        $this->lock();

        return ['admin_url' => '/admin'];
    }

    /** @param array<string, mixed> $db @param array<string, mixed> $site */
    private function writeEnv(array $db, array $site): void
    {
        $key = 'base64:' . base64_encode(random_bytes(32));
        $lines = [
            'APP_NAME="IR-Jalali"',
            'APP_ENV=production',
            'APP_DEBUG=false',
            'APP_KEY=' . $key,
            'APP_URL=' . ($site['url'] ?? 'http://localhost'),
            'APP_TIMEZONE=' . $site['timezone'],
            'APP_LOCALE=' . $site['language'],
            'APP_CALENDAR=jalali',
            'ADMIN_PATH=admin',
            '',
            'DB_DRIVER=mysql',
            'DB_HOST=' . $db['host'],
            'DB_PORT=' . (int) $db['port'],
            'DB_DATABASE=' . $db['database'],
            'DB_USERNAME=' . $db['username'],
            'DB_PASSWORD="' . str_replace('"', '', (string) ($db['password'] ?? '')) . '"',
            '',
            'CACHE_DRIVER=file',
            'CACHE_TTL=3600',
            '',
            'LOGIN_MAX_ATTEMPTS=5',
            'LOGIN_DECAY_SECONDS=300',
            'UPLOAD_MAX_KB=10240',
            '',
            'MAIL_DRIVER=log',
        ];
        if (!$this->fs->put('.env', implode("\n", $lines) . "\n")) {
            throw new \RuntimeException('Unable to write .env file.');
        }
        @chmod($this->fs->path('.env'), 0600);
    }

    public function lock(): void
    {
        $this->fs->put('storage/install.lock', json_encode([
            'installed_at' => date('c'),
            'version' => '1.0.0',
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    private function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
