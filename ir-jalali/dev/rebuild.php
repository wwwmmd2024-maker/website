<?php

declare(strict_types=1);

/**
 * DEV-ONLY: rebuild a fresh database (migrations + seeds + options + admin +
 * cron + activate official plugins). Never ship to production.
 * Usage: php dev/rebuild.php
 */

$base = dirname(__DIR__);
require $base . '/core/Kernel/Autoloader.php';
\IRJalali\Core\Kernel\Autoloader::register($base);

$app = \IRJalali\Core\Kernel\Application::boot($base);
$db = $app->make(\IRJalali\Core\Database\Database::class);
$logger = $app->make(\IRJalali\Core\Logging\Logger::class);

$n = (new \IRJalali\Core\Database\MigrationRunner($db, $logger))->run([$base . '/database/Migrations']);
echo "MIGRATED: {$n}\n";
(new \IRJalali\Database\Seeds\CoreSeeder($db))->run();
echo "SEEDED\n";

$now = date('Y-m-d H:i:s');
$defaults = [
    'site_title' => 'سایت من',
    'tagline' => 'قدرت‌گرفته از IR-Jalali',
    'language' => 'fa_IR',
    'timezone' => 'Asia/Tehran',
    'website_mode' => 'general',
    'setup_completed' => '1',
    'posts_per_page' => '9',
    'show_on_front' => 'posts',
];
foreach ($defaults as $key => $value) {
    if ($db->table('options')->where('key', $key)->first() === null) {
        $db->insert('options', ['key' => $key, 'value' => (string) $value, 'autoload' => 1, 'created_at' => $now, 'updated_at' => $now]);
    }
}

if ($db->table('users')->where('username', 'admin')->first() === null) {
    $hasher = new \IRJalali\Core\Security\Hasher();
    $uid = $db->insert('users', [
        'uuid' => bin2hex(random_bytes(16)),
        'username' => 'admin',
        'email' => 'admin@example.com',
        'password_hash' => $hasher->hash('Admin12345!'),
        'display_name' => 'admin',
        'status' => 'active',
        'locale' => 'fa_IR',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $role = $db->table('roles')->where('slug', 'super_admin')->first();
    $db->insert('user_roles', ['user_id' => $uid, 'role_id' => $role['id']]);
    echo "ADMIN: {$uid}\n";
}

foreach ([
    ['انتشار نوشته‌های زمان‌بندی‌شده', 'core.publish_scheduled', 'minutely'],
    ['پاک‌سازی کش', 'core.cache_cleanup', 'daily'],
] as [$name, $hook, $sched]) {
    if ($db->table('scheduled_tasks')->where('hook', $hook)->first() === null) {
        $db->insert('scheduled_tasks', [
            'name' => $name, 'hook' => $hook, 'schedule' => $sched, 'payload_json' => '[]',
            'next_run_at' => $now, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now,
        ]);
    }
}

// Activate every discovered plugin (official modules + examples).
$manager = $app->make(\IRJalali\Core\Plugins\PluginManager::class);
foreach ($manager->discover() as $slug => $plugin) {
    if (!$plugin->isActive()) {
        $manager->activate($slug, $plugin->capabilities);
        echo "ACTIVATED: {$slug}\n";
    }
}

echo 'TABLES: ' . count($db->select($db->driver() === 'sqlite'
    ? "SELECT name FROM sqlite_master WHERE type = 'table'"
    : 'SHOW TABLES')) . "\n";
echo "DONE\n";
