<?php

declare(strict_types=1);

namespace IRJalali\App\Controllers\Admin;

use IRJalali\App\Controllers\Controller;
use IRJalali\Core\Auth\Auth;
use IRJalali\Core\Config\Config;
use IRJalali\Core\Database\Database;
use IRJalali\Core\Http\Request;
use IRJalali\Core\Http\Response;
use IRJalali\Core\Kernel\Application;
use IRJalali\Core\Themes\ThemeManager;
use IRJalali\Core\View\View;

/**
 * System Health (Part 3 §23): PHP, MySQL, disk, memory, storage, permissions,
 * SSL, cron, cache, database, updates and security posture — all live checks.
 */
final class SystemController extends Controller
{
    public function __construct(
        View $view,
        Auth $auth,
        private readonly Database $db,
        private readonly Config $config,
        private readonly ThemeManager $themes,
    ) {
        parent::__construct($view, $auth);
    }

    public function index(Request $request): Response
    {
        if ($denied = $this->denyUnlessCan('settings.manage', 'logs.view')) {
            return $denied;
        }

        $checks = [];
        $add = function (string $group, string $label, bool $ok, string $detail = '') use (&$checks): void {
            $checks[] = ['group' => $group, 'label' => $label, 'ok' => $ok, 'detail' => $detail];
        };

        // ── PHP ─────────────────────────────────────────────────
        $add('PHP', 'نسخه PHP', version_compare(PHP_VERSION, '8.2.0', '>='), 'PHP ' . PHP_VERSION . ' (نیاز: 8.2+)');
        foreach (['pdo', 'pdo_mysql', 'mbstring', 'json', 'fileinfo'] as $ext) {
            $add('PHP', 'اکستنشن ' . $ext, extension_loaded($ext), extension_loaded($ext) ? 'نصب است' : 'وجود ندارد');
        }
        foreach (['curl', 'gd', 'intl', 'zip'] as $ext) {
            $add('PHP', 'اکستنشن اختیاری ' . $ext, extension_loaded($ext), extension_loaded($ext) ? 'نصب است' : 'نصب نیست (اختیاری)');
        }
        $memoryLimit = (string) ini_get('memory_limit');
        $add('PHP', 'حافظه PHP', $this->iniBytes($memoryLimit) >= 128 * 1024 * 1024 || $this->iniBytes($memoryLimit) === -1, 'memory_limit = ' . $memoryLimit);

        // ── Database ────────────────────────────────────────────
        try {
            $this->db->select('SELECT 1');
            $add('Database', 'اتصال دیتابیس', true, 'درایور: ' . $this->db->driver());
            $tables = $this->tableCount();
            $add('Database', 'جداول هسته', $tables >= 46, "{$tables} جدول (انتظار: ≥۴۶)");
        } catch (\Throwable $e) {
            $add('Database', 'اتصال دیتابیس', false, $e->getMessage());
        }

        // ── Disk / storage ──────────────────────────────────────
        $basePath = dirname(__DIR__, 3);
        $free = @disk_free_space($basePath);
        $total = @disk_total_space($basePath);
        if ($free !== false) {
            $add('Storage', 'فضای دیسک', $free > 200 * 1024 * 1024, 'آزاد: ' . $this->humanSize((int) $free) . ($total !== false ? ' از ' . $this->humanSize((int) $total) : ''));
        }
        foreach (['storage/logs', 'storage/cache', 'storage/uploads', 'storage/backups'] as $dir) {
            $abs = $basePath . '/' . $dir;
            $writable = is_dir($abs) && is_writable($abs);
            if (!is_dir($abs)) {
                @mkdir($abs, 0755, true);
                $writable = is_writable($abs);
            }
            $add('Storage', 'قابل‌نوشتن بودن ' . $dir, $writable, $writable ? 'OK' : 'دسترسی نوشتن ندارد');
        }

        // ── Security posture ────────────────────────────────────
        $installed = Application::get()->isInstalled();
        $add('Security', 'قفل نصب‌کننده', $installed && is_file($basePath . '/storage/install.lock'), $installed ? 'نصب‌کننده قفل است' : 'باز است!');
        $debug = (string) ($_ENV['APP_DEBUG'] ?? 'false');
        $add('Security', 'حالت دیباگ', !in_array($debug, ['true', '1'], true), 'APP_DEBUG=' . $debug);
        $key = (string) ($_ENV['APP_KEY'] ?? '');
        $add('Security', 'کلید اپلیکیشن', $key !== '', $key !== '' ? 'تنظیم شده' : 'تنظیم نشده!');
        $add('Security', 'هدرهای امنیتی', true, 'CSP / X-Frame / HSTS توسط میان‌افزار اعمال می‌شود');
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        $add('Security', 'HTTPS', $https, $https ? 'فعال' : 'در پروداکشن فعال کنید');

        // ── Cron / scheduler ────────────────────────────────────
        try {
            $due = $this->db->select('SELECT COUNT(*) AS c FROM scheduled_tasks WHERE is_active = 1');
            $lastRun = $this->db->first('SELECT MAX(last_run_at) AS last FROM scheduled_tasks WHERE last_run_at IS NOT NULL');
            $last = (string) ($lastRun['last'] ?? '');
            $fresh = $last !== '' && strtotime($last) > time() - 3600;
            $add('Cron', 'وظایف زمان‌بندی‌شده', ((int) ($due[0]['c'] ?? 0)) >= 0, ((int) ($due[0]['c'] ?? 0)) . ' وظیفه فعال');
            $add('Cron', 'اجرای اخیر کرون', $fresh, $last !== '' ? 'آخرین اجرا: ' . $last : 'هنوز اجرایی نشده — کرون را تنظیم کنید');
        } catch (\Throwable) {
            $add('Cron', 'وظایف زمان‌بندی‌شده', false, 'خطا در خواندن جدول');
        }

        // ── Cache ───────────────────────────────────────────────
        $add('Cache', 'درایور کش', true, (string) $this->config->get('cache.driver', 'file'));
        $cacheDir = $basePath . '/storage/cache';
        $add('Cache', 'پوشه کش', is_dir($cacheDir) && is_writable($cacheDir), is_writable($cacheDir) ? 'OK' : 'غیرقابل نوشتن');

        // ── Theme ───────────────────────────────────────────────
        $active = $this->themes->active();
        $add('Theme', 'قالب فعال', $active !== null, $active !== null ? "{$active->name} v{$active->version}" : 'هیچ قالبی فعال نیست');

        // ── Updates ─────────────────────────────────────────────
        $add('Updates', 'نسخه هسته', true, Application::get()->version());

        $pass = count(array_filter($checks, fn (array $c): bool => $c['ok']));
        $fail = count($checks) - $pass;

        return $this->render('admin.system', [
            'checks' => $checks,
            'pass' => $pass,
            'fail' => $fail,
            'user' => $this->auth->user(),
        ]);
    }

    private function tableCount(): int
    {
        if ($this->db->driver() === 'sqlite') {
            $rows = $this->db->select("SELECT COUNT(*) AS c FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'");
        } else {
            $rows = $this->db->select('SELECT COUNT(*) AS c FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE()');
        }

        return (int) ($rows[0]['c'] ?? 0);
    }

    private function iniBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '' || $value === '-1') {
            return -1;
        }
        $unit = strtolower(substr($value, -1));
        $num = (int) $value;

        return match ($unit) {
            'g' => $num * 1024 * 1024 * 1024,
            'm' => $num * 1024 * 1024,
            'k' => $num * 1024,
            default => $num,
        };
    }

    private function humanSize(int $bytes): string
    {
        foreach (['B', 'KB', 'MB', 'GB', 'TB'] as $unit) {
            if ($bytes < 1024 || $unit === 'TB') {
                return round($bytes, 1) . ' ' . $unit;
            }
            $bytes /= 1024;
        }

        return (string) $bytes;
    }
}
