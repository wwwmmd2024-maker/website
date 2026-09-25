<?php

declare(strict_types=1);

namespace IRJalali\App\Controllers\Admin;

use IRJalali\App\Controllers\Controller;
use IRJalali\App\Services\NotificationService;
use IRJalali\Core\Auth\Auth;
use IRJalali\Core\Http\Request;
use IRJalali\Core\Http\Response;
use IRJalali\Core\Kernel\Application;
use IRJalali\Core\Logging\Logger;
use IRJalali\Core\Marketplace\MarketplaceClient;
use IRJalali\Core\Plugins\PluginManager;
use IRJalali\Core\Plugins\PluginUpdater;
use IRJalali\Core\Themes\ThemeManager;
use IRJalali\Core\Updates\SiteBackupService;
use IRJalali\Core\View\View;

/**
 * Update Center (Part 2 §31–32): checks core/theme/plugin updates against the
 * marketplace, takes an AUTOMATIC full-site backup before applying anything,
 * then updates with per-item rollback on failure.
 */
final class UpdatesController extends Controller
{
    public function __construct(
        View $view,
        Auth $auth,
        private readonly PluginManager $plugins,
        private readonly PluginUpdater $updater,
        private readonly ThemeManager $themes,
        private readonly MarketplaceClient $marketplace,
        private readonly SiteBackupService $backups,
        private readonly NotificationService $notifications,
        private readonly Logger $logger,
    ) {
        parent::__construct($view, $auth);
    }

    public function index(Request $request): Response
    {
        if ($denied = $this->denyUnlessCan('settings.manage')) {
            return $denied;
        }

        $pluginUpdates = [];
        foreach ($this->plugins->discover() as $slug => $plugin) {
            try {
                $release = $this->marketplace->latestPluginRelease($slug);
            } catch (\Throwable) {
                $release = null;
            }
            if ($release !== null && version_compare((string) $release['version'], $plugin->version, '>')) {
                $pluginUpdates[] = [
                    'kind' => 'plugin', 'slug' => $slug, 'name' => $plugin->name,
                    'current' => $plugin->version, 'latest' => (string) $release['version'],
                    'changelog' => (string) ($release['changelog'] ?? ''),
                ];
            }
        }

        $themeUpdates = [];
        foreach ($this->themes->all() as $slug => $theme) {
            try {
                $release = $this->marketplace->latestThemeRelease($slug);
            } catch (\Throwable) {
                $release = null;
            }
            if ($release !== null && version_compare((string) $release['version'], $theme->version, '>')) {
                $themeUpdates[] = [
                    'kind' => 'theme', 'slug' => $slug, 'name' => $theme->name,
                    'current' => $theme->version, 'latest' => (string) $release['version'],
                    'changelog' => (string) ($release['changelog'] ?? ''),
                ];
            }
        }

        return $this->render('admin.updates', [
            'coreVersion' => Application::get()->version(),
            'coreUpdate' => $this->coreRelease(),
            'pluginUpdates' => $pluginUpdates,
            'themeUpdates' => $themeUpdates,
            'user' => $this->auth->user(),
        ]);
    }

    /** Apply one pending update (with automatic full backup first). */
    public function apply(Request $request): Response
    {
        if ($denied = $this->denyUnlessCan('settings.manage')) {
            return $denied;
        }
        $kind = (string) $request->route('kind', '');
        $slug = preg_replace('/[^a-z0-9_\-]/i', '', (string) $request->route('slug', ''));

        try {
            $backupPath = $this->backups->create('update:' . $kind . '/' . $slug);
            $this->logger->channel('updates')->info('Pre-update backup created', ['path' => $backupPath]);
        } catch (\Throwable $e) {
            $this->withFlash('error', 'پشتیبان‌گیری خودکار ناموفق بود؛ به‌روزرسانی متوقف شد: ' . $e->getMessage());

            return $this->redirect('/admin/updates');
        }

        if ($kind === 'plugin') {
            $result = $this->updater->update($slug);
            $this->withFlash($result['success'] ? 'success' : 'error', $result['message']);
            $this->notifications->pushToAdmins(
                $result['success'] ? 'plugin.update' : 'system.error',
                $result['success'] ? 'افزونه به‌روزرسانی شد' : 'به‌روزرسانی افزونه ناموفق بود',
                $result['message'],
                '/admin/updates'
            );
        } elseif ($kind === 'theme') {
            $result = $this->updateTheme($slug);
            $this->withFlash($result['ok'] ? 'success' : 'error', $result['message']);
        } else {
            $this->withFlash('error', 'نوع به‌روزرسانی نامشخص است.');
        }

        return $this->redirect('/admin/updates');
    }

    /** @return array{ok: bool, message: string} */
    private function updateTheme(string $slug): array
    {
        $theme = $this->themes->get($slug);
        if ($theme === null) {
            return ['ok' => false, 'message' => 'قالب یافت نشد.'];
        }
        $release = $this->marketplace->latestThemeRelease($slug);
        if ($release === null || !version_compare((string) $release['version'], $theme->version, '>')) {
            return ['ok' => false, 'message' => 'به‌روزرسانی جدیدی برای این قالب موجود نیست.'];
        }
        $tmp = sys_get_temp_dir() . '/irj-upd-theme-' . bin2hex(random_bytes(6)) . '.zip';
        try {
            if (!$this->marketplace->download((string) $release['download_url'], $tmp)) {
                return ['ok' => false, 'message' => 'دانلود بسته قالب ناموفق بود.'];
            }
            if (!empty($release['checksum']) && (hash_file('sha256', $tmp) ?: '') !== strtolower((string) $release['checksum'])) {
                return ['ok' => false, 'message' => 'Checksum بسته قالب معتبر نیست.'];
            }
            /** @var \IRJalali\Core\Updates\BackupService $componentBackups */
            $componentBackups = Application::get()->make(\IRJalali\Core\Updates\BackupService::class);
            $backupPath = $componentBackups->backupTheme($slug, $theme->version, $theme->path);
            try {
                /** @var \IRJalali\Core\Plugins\PluginInstaller $installer */
                $installer = Application::get()->make(\IRJalali\Core\Plugins\PluginInstaller::class);
                $report = $installer->scan($tmp);
                if (!$report->safe()) {
                    throw new \RuntimeException('Security scan failed.');
                }
                // In-place update keeps activation state (works for the active theme too).
                $installer->extractOver($tmp, $theme->path);
                $this->themes->refresh();
                $updated = $this->themes->get($slug);
                if ($updated === null) {
                    throw new \RuntimeException('Updated theme manifest missing.');
                }
                $compat = $this->themes->checkCompatibility($updated);
                if (!$compat['ok']) {
                    throw new \RuntimeException($compat['error'] ?? 'Incompatible new version.');
                }
            } catch (\Throwable $e) {
                $componentBackups->restoreTheme($theme->path, $backupPath);
                $this->themes->refresh();

                return ['ok' => false, 'message' => 'به‌روزرسانی قالب ناموفق بود و نسخه قبلی بازگردانده شد: ' . $e->getMessage()];
            }

            return ['ok' => true, 'message' => "قالب به نسخه {$release['version']} به‌روزرسانی شد."];
        } finally {
            @unlink($tmp);
        }
    }

    /** @return array{version: string}|null */
    private function coreRelease(): ?array
    {
        try {
            $release = $this->marketplace->latestPluginRelease('ir-jalali-core');
        } catch (\Throwable) {
            return null;
        }
        if ($release === null || !version_compare((string) ($release['version'] ?? '0'), Application::get()->version(), '>')) {
            return null;
        }

        return ['version' => (string) $release['version']];
    }
}
