<?php

declare(strict_types=1);

/**
 * Integration check: boot on :memory: SQLite, migrate+seed, then ACTIVATE and
 * BOOT every official plugin, verify tables/CPTs/blocks/routes/admin pages,
 * and validate the local marketplace catalog/packages.
 */
error_reporting(E_ALL);
require dirname(__DIR__) . '/core/Kernel/Autoloader.php';
\IRJalali\Core\Kernel\Autoloader::register(dirname(__DIR__));
$_ENV['DB_DRIVER'] = 'sqlite';
$_ENV['DB_DATABASE'] = ':memory:';
$_ENV['APP_DEBUG'] = 'true';

$app = \IRJalali\Core\Kernel\Application::boot(dirname(__DIR__));
$db = $app->make(\IRJalali\Core\Database\Database::class);
(new \IRJalali\Core\Database\MigrationRunner($db, $app->make(\IRJalali\Core\Logging\Logger::class)))
    ->run([dirname(__DIR__) . '/database/Migrations']);
(new \IRJalali\Database\Seeds\CoreSeeder($db))->run();

$failures = 0;
function check(string $label, bool $ok): void
{
    global $failures;
    if (!$ok) {
        $failures++;
    }
    echo ($ok ? 'OK   ' : 'FAIL ') . $label . "\n";
}

/** @var \IRJalali\Core\Plugins\PluginManager $manager */
$manager = $app->make(\IRJalali\Core\Plugins\PluginManager::class);
$all = $manager->discover();
$expected = ['ir-demo', 'ir-seo', 'ir-cache', 'ir-smtp', 'ir-analytics', 'ir-security', 'ir-commerce', 'ir-booking', 'ir-membership', 'ir-lms', 'ir-directory', 'site-notice'];
foreach ($expected as $slug) {
    check("discovered {$slug}", isset($all[$slug]));
}

// Activate every discovered plugin with its declared capabilities.
foreach ($all as $slug => $plugin) {
    try {
        $manager->activate($slug, $plugin->capabilities);
        check("activated {$slug}", $manager->plugin($slug)?->isActive() === true);
    } catch (\Throwable $e) {
        check("activated {$slug} :: " . $e->getMessage(), false);
    }
}

// Plugin migrations created their tables.
foreach ([
    'ir_demo_stats', 'ir_seo_404', 'ir_smtp_queue', 'ir_smtp_log',
    'ir_analytics_views', 'ir_analytics_daily', 'ir_security_rules',
    'ir_commerce_orders', 'ir_commerce_order_items', 'ir_booking_appointments',
    'ir_membership_plans', 'ir_membership_subscriptions',
    'ir_lms_enrollments', 'ir_lms_progress',
] as $table) {
    check("table {$table}", $db->tableExists($table));
}

// CPT registrations persisted.
$cpts = array_column($db->select('SELECT slug FROM post_types'), 'slug');
foreach (['demo-item', 'product', 'service', 'course', 'lesson', 'listing'] as $cpt) {
    check("post type {$cpt}", in_array($cpt, $cpts, true));
}

// Blocks & widgets registered in-process.
$blocks = $app->make(\IRJalali\Core\Blocks\BlockRegistry::class);
foreach (['ir/demo-card', 'ir/products', 'ir/courses'] as $blockSlug) {
    check("block {$blockSlug}", $blocks->get($blockSlug) !== null);
}
$widgets = $app->make(\IRJalali\Core\Widgets\WidgetRegistry::class);
foreach (['ir/demo-info', 'ir/cart-summary'] as $widgetSlug) {
    check("widget {$widgetSlug}", $widgets->get($widgetSlug) !== null);
}

// Block render actually produces HTML.
try {
    $ctx = new \IRJalali\Core\Builder\RenderContext(null, null);
    $html = $blocks->render('ir/demo-card', ['title' => 'T', 'body' => 'B'], $ctx);
    check('block ir/demo-card renders', str_contains($html, 'T') && str_contains($html, 'B'));
} catch (\Throwable $e) {
    check('block ir/demo-card renders :: ' . $e->getMessage(), false);
}

// Routes + admin pages registered.
$routes = $manager->routes();
check('plugin routes registered (' . count($routes) . ')', count($routes) >= 20);
$pages = $manager->adminPages();
check('plugin admin pages registered (' . count($pages) . ')', count($pages) >= 8);
$paths = array_column($routes, 'path');
foreach (['/shop', '/cart', '/checkout', '/booking', '/directory', '/plans', '/register', '/courses', '/demo/hello', '/sitemap.xml', '/robots.txt'] as $path) {
    check("route {$path}", in_array($path, $paths, true));
}

// Cron tasks persisted.
$tasks = array_column($db->select('SELECT hook FROM scheduled_tasks'), 'hook');
check('cron tasks scheduled (' . count($tasks) . ')', count($tasks) >= 8);

// Settings schema registered (registerSettings keeps schema in-memory per context).
$withSchema = 0;
foreach ($manager->contexts() as $ctx) {
    if ($ctx->settingsSchema() !== []) {
        $withSchema++;
    }
}
check('plugin settings schemas registered (' . $withSchema . ')', $withSchema >= 5);

// CLI commands registered.
$commands = $manager->commands();
check('CLI commands registered (' . count($commands) . ')', isset($commands['demo:stats']) && isset($commands['commerce:orders']) && isset($commands['lms:stats']));

// Marketplace: catalog + packages + checksums.
$provider = new \IRJalali\Core\Marketplace\LocalMarketplaceProvider(dirname(__DIR__) . '/marketplace');
$items = $provider->search('plugin');
check('marketplace catalog items (' . count($items) . ')', count($items) === 12);
$release = $provider->latestRelease('plugin', 'ir-seo');
check('marketplace latestRelease ir-seo', $release !== null && $release['checksum'] !== '');
if ($release !== null) {
    $tmp = sys_get_temp_dir() . '/irj-dl-test.zip';
    check('marketplace download', $provider->download($release['download_url'], $tmp) && is_file($tmp) && hash_file('sha256', $tmp) === $release['checksum']);
    @unlink($tmp);
}

// Installer scan (read-only) of a packaged zip.
try {
    $installer = $app->make(\IRJalali\Core\Plugins\PluginInstaller::class);
    $report = $installer->scan(dirname(__DIR__) . '/marketplace/packages/ir-commerce-1.0.0.zip');
    check('installer scan ir-commerce zip (safe, ' . $report->files . ' files)', $report->safe());
    if (!$report->safe()) {
        foreach (array_slice($report->errors, 0, 8) as $err) {
            echo "       scan-error: {$err}\n";
        }
    }
} catch (\Throwable $e) {
    check('installer scan ir-commerce zip :: ' . $e->getMessage(), false);
}

echo $failures > 0 ? "\nFAILURES: {$failures}\n" : "\nALL PLUGIN CHECKS PASSED\n";
exit($failures > 0 ? 1 : 0);
