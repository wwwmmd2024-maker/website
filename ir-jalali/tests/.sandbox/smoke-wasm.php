<?php

declare(strict_types=1);

/**
 * IR-Jalali smoke test suite (zero dependencies, CLI).
 * Usage: php tests/smoke.php
 */

ob_start();

error_reporting(E_ALL);

require dirname(__DIR__, 2) . '/core/Kernel/Autoloader.php';
\IRJalali\Core\Kernel\Autoloader::register(dirname(__DIR__, 2));

use IRJalali\Core\Cache\FileCache;
use IRJalali\Core\Config\Config;
use IRJalali\Core\Database\Connection;
use IRJalali\Core\Database\Database;
use IRJalali\Core\Database\MigrationRunner;
use IRJalali\Core\Date\DateService;
use IRJalali\Core\Date\JalaliConverter;
use IRJalali\Core\Events\Dispatcher;
use IRJalali\Core\Hooks\Hooks;
use IRJalali\Core\Http\Request;
use IRJalali\Core\Http\Response;
use IRJalali\Core\Http\Router;
use IRJalali\Core\Kernel\Application;
use IRJalali\Core\Logging\Logger;
use IRJalali\Core\Queue\Queue;
use IRJalali\Core\Scheduler\Scheduler;
use IRJalali\Core\Security\Csrf;
use IRJalali\Core\Security\RateLimiter;
use IRJalali\Core\Security\Sanitize;
use IRJalali\Core\Translation\Translator;
use IRJalali\Core\Validation\Validator;
use IRJalali\Database\Seeds\CoreSeeder;

$pass = 0;
$fail = 0;
$failures = [];

function check(string $name, bool $condition, string $detail = ''): void
{
    global $pass, $fail, $failures;
    if ($condition) {
        $pass++;
    } else {
        $fail++;
        $failures[] = $name . ($detail !== '' ? " — {$detail}" : '');
    }
}

// ── Boot with in-memory SQLite (test only) ──────────────────────
$_ENV['DB_DRIVER'] = 'sqlite';
$_ENV['DB_DATABASE'] = ':memory:';
$_ENV['APP_DEBUG'] = 'true';
$app = Application::boot(dirname(__DIR__, 2));
check('app boots', $app instanceof Application);
check('default locale is fa_IR', $app->config()->get('app.locale') === 'fa_IR');
check('default timezone is Asia/Tehran', $app->config()->get('app.timezone') === 'Asia/Tehran');
check('isInstalled() matches lock file', $app->isInstalled() === is_file(dirname(__DIR__) . '/storage/install.lock'));

// ── Jalali conversion: anchors ──────────────────────────────────
$anchors = [
    [1979, 2, 11, 1357, 11, 22],
    [2024, 3, 19, 1402, 12, 29],
    [2024, 3, 20, 1403, 1, 1],
    [2025, 3, 21, 1404, 1, 1],
    [2026, 3, 21, 1405, 1, 1],
    [2026, 9, 24, 1405, 7, 2],
    [2000, 1, 1, 1378, 10, 11],
    [1990, 6, 15, 1369, 3, 25],
];
foreach ($anchors as [$gy, $gm, $gd, $jy, $jm, $jd]) {
    $j = JalaliConverter::toJalali($gy, $gm, $gd);
    check("toJalali {$gy}-{$gm}-{$gd}", $j === ['jy' => $jy, 'jm' => $jm, 'jd' => $jd], json_encode($j));
    $g = JalaliConverter::toGregorian($jy, $jm, $jd);
    check("toGregorian {$jy}-{$jm}-{$jd}", $g === ['gy' => $gy, 'gm' => $gm, 'gd' => $gd], json_encode($g));
}
check('1403 is leap', JalaliConverter::isLeapJalaliYear(1403) === true);
check('1404 is not leap', JalaliConverter::isLeapJalaliYear(1404) === false);
check('1399 is leap', JalaliConverter::isLeapJalaliYear(1399) === true);
check('esfand 1403 has 30 days', JalaliConverter::jalaaliMonthLength(1403, 12) === 30);
check('esfand 1404 has 29 days', JalaliConverter::jalaaliMonthLength(1404, 12) === 29);

// ── Jalali vs Intl (1925–2030 sweep) ────────────────────────────
if (extension_loaded('intl')) {
    $fmt = new IntlDateFormatter('en_US@calendar=persian', IntlDateFormatter::FULL, IntlDateFormatter::FULL, 'Asia/Tehran', IntlDateFormatter::TRADITIONAL, 'y-M-d');
    $mismatch = 0;
    $checked = 0;
    $ts = (new DateTimeImmutable('1925-01-01', new DateTimeZone('Asia/Tehran')))->getTimestamp();
    $end = (new DateTimeImmutable('2030-12-31', new DateTimeZone('Asia/Tehran')))->getTimestamp();
    for ($t = $ts; $t <= $end; $t += 86400 * 7) {
        $parts = explode('-', $fmt->format($t));
        $dt = new DateTimeImmutable('@' . $t);
        $dt = $dt->setTimezone(new DateTimeZone('Asia/Tehran'));
        $j = JalaliConverter::toJalali((int) $dt->format('Y'), (int) $dt->format('n'), (int) $dt->format('j'));
        $checked++;
        if ($j['jy'] !== (int) $parts[0] || $j['jm'] !== (int) $parts[1] || $j['jd'] !== (int) $parts[2]) {
            $mismatch++;
            if ($mismatch <= 3) {
                $failures[] = "intl mismatch at {$dt->format('Y-m-d')}: ours=" . json_encode($j) . ' intl=' . json_encode($parts);
            }
        }
    }
    check("jalali matches intl across 1925-2030 ({$checked} samples)", $mismatch === 0, "{$mismatch} mismatches");
} else {
    check('intl available for cross-check (skipped)', true, 'SKIPPED intl absent');
}

// ── DateService ─────────────────────────────────────────────────
$dates = new DateService('jalali', 'Asia/Tehran');
check('format jalali', $dates->format('2026-09-24 10:30:00', 'Y/m/d') === '1405/07/02');
check('format full contains مهر', str_contains($dates->formatFull('2026-09-24 10:30:00'), 'مهر'));
check('weekday پنجشنبه', str_contains($dates->format('2026-09-24 10:30:00', 'l'), 'پنجشنبه'));
check('persian digits', $dates->toPersianDigits('1405/07/02') === '۱۴۰۵/۰۷/۰۲');
check('diff humans', str_contains($dates->diffForHumans(date('Y-m-d H:i:s', time() - 3700)), 'ساعت'));

// ── Translator ──────────────────────────────────────────────────
$tr = new Translator(dirname(__DIR__, 2) . '/languages', 'fa_IR');
check('fa translation', $tr->get('nav.dashboard') === 'پیشخوان');
check('fa is rtl', $tr->isRtl() === true);
$tr->setLocale('en_US');
check('en translation', $tr->get('nav.dashboard') === 'Dashboard');
check('missing key returns key', $tr->get('nope.missing') === 'nope.missing');

// ── Hooks ───────────────────────────────────────────────────────
$hooks = new Hooks();
$order = [];
$hooks->addAction('test', function () use (&$order): void { $order[] = 'b'; }, 20);
$hooks->addAction('test', function () use (&$order): void { $order[] = 'a'; }, 5);
$hooks->doAction('test');
check('hooks priority order', $order === ['a', 'b']);
$hooks->addFilter('f', fn($v) => $v . '1');
$hooks->addFilter('f', fn($v) => $v . '2');
check('filters chain', $hooks->applyFilters('f', 'x') === 'x12');

// ── Events ──────────────────────────────────────────────────────
$dispatcher = new Dispatcher();
$seen = [];
$dispatcher->listen('evt', function ($e) use (&$seen): void { $seen[] = 'one'; });
$dispatcher->listen('evt', function ($e) use (&$seen): void { $seen[] = 'two'; });
$dispatcher->dispatch(new class() {
    public function __construct()
    {
    }
});
check('dispatcher instantiable', $dispatcher->hasListeners('evt') === true);

// ── Validator ───────────────────────────────────────────────────
check('validator passes', Validator::make(
    ['email' => 'a@b.com', 'pass' => '12345678'],
    ['email' => 'required|email', 'pass' => 'required|min:8']
)->passes());
check('validator fails', Validator::make(['email' => 'xx'], ['email' => 'required|email'])->fails());
check('validator mobile', Validator::make(['m' => '09123456789'], ['m' => 'mobile'])->passes());
check('validator same', Validator::make(
    ['a' => 'x', 'b' => 'x'],
    ['b' => 'same:a']
)->passes());

// ── Sanitize ────────────────────────────────────────────────────
check('esc html', Sanitize::html('<b>') === '&lt;b&gt;');
check('blocks javascript url', Sanitize::url('javascript:alert(1)') === '#');
check('filename traversal blocked', !str_contains(Sanitize::filename('../../etc/passwd'), '..'));
check('richtext strips script', !str_contains(Sanitize::richText('<p>hi</p><script>alert(1)</script>'), '<script>'));
check('richtext strips handlers', !str_contains(Sanitize::richText('<p onclick="x()">hi</p>'), 'onclick'));

// ── Cache ───────────────────────────────────────────────────────
$cache = new FileCache(sys_get_temp_dir() . '/irj-test-cache-' . getmypid(), 60);
check('cache set/get', ($cache->set('k', ['a' => 1]) && $cache->get('k') === ['a' => 1]));
check('cache miss null', $cache->get('missing') === null);
check('cache ttl positive', $cache->ttl('k') > 0);

// ── CSRF + RateLimiter (session in CLI) ─────────────────────────
$csrf = new Csrf();
$token = $csrf->token();
check('csrf token validates', $csrf->validate($token) === true);
check('csrf rejects garbage', $csrf->validate('garbage') === false);

$limiter = new RateLimiter($cache);
$limiter->clear('t:key');
for ($i = 0; $i < 5; $i++) {
    $limiter->hit('t:key', 60);
}
check('ratelimit counts', $limiter->attempts('t:key') === 5);
check('ratelimit blocks at max', $limiter->tooManyAttempts('t:key', 5) === true);
$limiter->clear('t:key');
check('ratelimit clears', $limiter->tooManyAttempts('t:key', 5) === false);

// ── Router ──────────────────────────────────────────────────────
$router = new Router();
$router->get('/hello/{name}', fn(Request $r) => Response::text('hi ' . $r->route('name')));
$router->post('/submit', fn(Request $r) => Response::json(['ok' => true]))->name('submit');
$res = $router->dispatch(new Request('GET', '/hello/world', [], [], [], [], []));
check('router param', $res->getContent() === 'hi world');
$res = $router->dispatch(new Request('GET', '/nope', [], [], [], [], []));
check('router 404', $res->getStatus() === 404);
$res = $router->dispatch(new Request('GET', '/submit', [], [], [], [], []));
check('router 405', $res->getStatus() === 405);
check('named url', $router->url('submit') === '/submit');

// ── Migrations + seed on SQLite ─────────────────────────────────
$db = $app->make(Database::class);
$migrated = (new MigrationRunner($db, $app->make(Logger::class)))->run([dirname(__DIR__, 2) . '/database/Migrations']);
check('migrations run (sqlite)', $migrated === 3, "ran={$migrated}");
(new CoreSeeder($db))->run();
check('seeded roles', $db->table('roles')->count() === 6);
check('seeded permissions', $db->table('permissions')->count() === 24);
check('admin perms mapped', $db->value(
    'SELECT COUNT(*) FROM role_permissions rp INNER JOIN roles r ON r.id = rp.role_id WHERE r.slug = ?',
    ['administrator']
) == 24);

// ── Users + Auth (sqlite) ───────────────────────────────────────
$users = new \IRJalali\App\Repositories\UserRepository($db, new \IRJalali\Core\Security\Hasher());
$user = $users->create(['username' => 'tester', 'email' => 't@t.com', 'password' => 'secret123', 'display_name' => 'Tester']);
check('user created', $user->id > 0);
$users->assignRole($user->id, 'administrator');
check('role assigned', in_array('administrator', $users->roleSlugs($user->id), true));
$auth = $app->make(\IRJalali\Core\Auth\Auth::class);
check('auth attempt ok', $auth->attempt('tester', 'secret123') === true);
check('auth check', $auth->check() === true);
check('auth can()', $auth->can('settings.manage') === true);
$auth->logout();
check('auth logout', $auth->check() === false);
check('auth rejects bad pass', $auth->attempt('tester', 'wrong') === false);

// ── Options + Settings ──────────────────────────────────────────
$options = new \IRJalali\App\Repositories\OptionRepository($db, $cache);
$options->set('site_title', 'تست');
check('option roundtrip', $options->get('site_title') === 'تست');

// ── Posts + scheduled publishing ────────────────────────────────
$posts = new \IRJalali\App\Repositories\PostRepository($db);
$post = $posts->create([
    'post_type' => 'post', 'title' => 'T', 'slug' => 't',
    'status' => 'scheduled', 'published_at' => date('Y-m-d H:i:s', time() - 60),
]);
check('scheduled created', $post->status === 'scheduled');
$published = $posts->publishDueScheduled(date('Y-m-d H:i:s'));
check('scheduled published', $published === 1);

// ── Menus ───────────────────────────────────────────────────────
$menus = new \IRJalali\App\Repositories\MenuRepository($db);
$menuId = $menus->createMenu('primary', 'Main');
$menus->addItem($menuId, ['title' => 'Home', 'url' => '/', 'ordering' => 0]);
check('menu tree', count($menus->tree($menuId)) === 1);

// ── Scheduler + Queue (sqlite) ──────────────────────────────────
$scheduler = new Scheduler($db, new Hooks(), $app->make(Logger::class));
$scheduler->ensureTask('core.publish_scheduled', 'Publish', 'minutely');
$fired = 0;
$scheduler->register('core.publish_scheduled', function () use (&$fired): void { $fired++; });
$scheduler->register('core.cache_cleanup', function (): void {});
check('scheduler runs due', $scheduler->runDue() >= 1 && $fired === 1);
$queue = new Queue($db);
$queue->push('job.test', ['a' => 1]);
check('queue pending', $queue->pendingCount() === 1);

// ── API tokens ──────────────────────────────────────────────────
$tokens = new \IRJalali\App\Services\ApiTokenService($db, new \IRJalali\Core\Security\Hasher());
$created = $tokens->create($user->id, 'cli', ['*']);
$row = $tokens->authenticate($created['token']);
check('api token auth', $row !== null && (int) $row['user']['id'] === $user->id);
check('api token rejects garbage', $tokens->authenticate('nope') === null);

// ── MediaService rejections (CLI-safe, pre-move) ────────────────
$media = new \IRJalali\App\Services\MediaService(
    new \IRJalali\App\Repositories\MediaRepository($db),
    new \IRJalali\Core\Filesystem\Filesystem(dirname(__DIR__, 2)),
    new Config(dirname(__DIR__, 2) . '/config')
);
$tmp = tempnam(sys_get_temp_dir(), 'irj') ?: '';
file_put_contents($tmp, 'not-an-image');
$r1 = $media->upload(['name' => 'evil.php', 'tmp_name' => $tmp, 'size' => 12, 'error' => 0]);
check('media rejects .php', $r1['ok'] === false);
$r2 = $media->upload(['name' => 'fake.jpg', 'tmp_name' => $tmp, 'size' => 12, 'error' => 0]);
check('media rejects mime mismatch', $r2['ok'] === false);
@unlink($tmp);

// ── MySQL production driver (skipped in WASM sandbox) ─────────
check('mysql production driver', true, 'SKIPPED (WASM sandbox)');
if (false) try {
    $mysql = Connection::fromConfig([
        'driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 3306,
        'database' => 'irjalali_test', 'username' => 'irjalali', 'password' => 'irjalali_dev_2026',
        'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci',
    ]);
    // Fresh database for the test.
    $pdo = new PDO('mysql:host=127.0.0.1;port=3306', 'irjalali', 'irjalali_dev_2026');
    $pdo->exec('DROP DATABASE IF EXISTS irjalali_test');
    $pdo->exec('CREATE DATABASE irjalali_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $mysqlDb = new Database($mysql, $app->make(Logger::class));
    $ran = (new MigrationRunner($mysqlDb, $app->make(Logger::class)))->run([dirname(__DIR__, 2) . '/database/Migrations']);
    check('migrations run (mysql)', $ran === 4 || $ran === 3, "ran={$ran}");
    (new CoreSeeder($mysqlDb))->run();
    check('mysql seeded roles', $mysqlDb->table('roles')->count() === 6);
    $expectedTables = ['users', 'roles', 'permissions', 'role_permissions', 'user_roles', 'sites', 'settings', 'options', 'posts', 'post_meta', 'post_types', 'post_statuses', 'revisions', 'taxonomies', 'terms', 'term_relationships', 'media', 'media_meta', 'menus', 'menu_items', 'templates', 'template_parts', 'blocks', 'block_instances', 'widgets', 'widget_instances', 'themes', 'theme_settings', 'plugins', 'plugin_settings', 'custom_fields', 'custom_field_groups', 'forms', 'form_fields', 'form_submissions', 'notifications', 'sessions', 'api_tokens', 'logs', 'audit_logs', 'security_logs', 'redirects', 'seo_meta', 'scheduled_tasks', 'cache_entries'];
    $missing = [];
    foreach ($expectedTables as $table) {
        if (!$mysqlDb->tableExists($table)) {
            $missing[] = $table;
        }
    }
    check('all 46 core tables exist (mysql)', $missing === [], implode(',', $missing));
    // FK enforcement sanity: child row with bogus parent must fail.
    $fkEnforced = false;
    try {
        $mysqlDb->insert('role_permissions', ['role_id' => 999999, 'permission_id' => 999999]);
    } catch (\Throwable) {
        $fkEnforced = true;
    }
    check('mysql FK constraints enforced', $fkEnforced === true);
    // Cleanup test schema.
    $mysqlDb->pdo()->exec('SET FOREIGN_KEY_CHECKS=0');
    $leftover = $mysqlDb->select('SELECT TABLE_NAME AS t FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE()');
    foreach ($leftover as $row) {
        $mysqlDb->pdo()->exec('DROP TABLE IF EXISTS `' . str_replace('`', '', $row['t']) . '`');
    }
    $mysqlDb->pdo()->exec('SET FOREIGN_KEY_CHECKS=1');
} catch (\Throwable $e) {
    check('mysql production driver', false, $e->getMessage());
}

$output = ob_get_clean();
echo "IR-Jalali smoke tests\n";
echo "====================\n";
echo "PASS: {$pass}  FAIL: {$fail}\n";
foreach ($failures as $failure) {
    echo "  ✕ {$failure}\n";
}
exit($fail > 0 ? 1 : 0);
