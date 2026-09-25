<?php

declare(strict_types=1);

namespace IRJalali\App\Controllers\Admin;

use IRJalali\App\Controllers\Controller;
use IRJalali\Core\Auth\Auth;
use IRJalali\Core\Http\Request;
use IRJalali\Core\Http\Response;
use IRJalali\Core\Logging\Logger;
use IRJalali\Core\Marketplace\MarketplaceClient;
use IRJalali\Core\Plugins\PluginInstaller;
use IRJalali\Core\Themes\ThemeManager;
use IRJalali\Core\View\View;

/**
 * Theme Manager (Part 2 §15–17, §30): list, preview meta, activate,
 * upload/validate/install ZIP, remove. Content is never owned by themes.
 */
final class ThemesController extends Controller
{
    public function __construct(
        View $view,
        Auth $auth,
        private readonly ThemeManager $themes,
        private readonly MarketplaceClient $marketplace,
        private readonly PluginInstaller $pluginInstaller,
        private readonly Logger $logger,
    ) {
        parent::__construct($view, $auth);
    }

    public function index(Request $request): Response
    {
        if ($denied = $this->denyUnlessCan('themes.manage')) {
            return $denied;
        }

        $activeSlug = $this->themes->activeSlug();
        $items = [];
        foreach ($this->themes->all() as $slug => $theme) {
            $compat = $this->themes->checkCompatibility($theme);
            $items[] = [
                'slug' => $slug,
                'name' => $theme->name,
                'version' => $theme->version,
                'author' => $theme->author,
                'description' => $theme->description,
                'active' => $slug === $activeSlug,
                'child' => $theme->isChild(),
                'parent' => $theme->isChild() ? $theme->parent : null,
                'screenshot' => $theme->screenshot() !== null ? $this->themes->assetUrl($slug, (string) $theme->screenshot()) : null,
                'compatible' => $compat['ok'],
                'compat_error' => $compat['error'] ?? null,
                'has_update' => $this->hasUpdate($slug, $theme->version),
            ];
        }

        return $this->render('admin.themes', [
            'items' => $items,
            'activeSlug' => $activeSlug,
            'user' => $this->auth->user(),
        ]);
    }

    public function upload(Request $request): Response
    {
        if ($denied = $this->denyUnlessCan('themes.manage')) {
            return $denied;
        }
        $file = $request->file('package');
        if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $this->withFlash('error', 'فایل قالب انتخاب نشد.');

            return $this->redirect('/admin/themes');
        }
        $tmp = sys_get_temp_dir() . '/irj-theme-upload-' . bin2hex(random_bytes(6)) . '.zip';
        if (!move_uploaded_file($file['tmp_name'], $tmp)) {
            $this->withFlash('error', 'انتقال فایل ناموفق بود.');

            return $this->redirect('/admin/themes');
        }

        try {
            // Themes carry executable PHP too — run the same security scan plugins get.
            $report = $this->pluginInstaller->scan($tmp);
            if (!$report->safe()) {
                $this->withFlash('error', 'اسکن امنیتی قالب ناموفق بود: ' . implode(' | ', array_slice($report->errors, 0, 3)));

                return $this->redirect('/admin/themes');
            }
            $result = $this->themes->installFromZip($tmp);
            if ($result['ok']) {
                $this->withFlash('success', 'قالب نصب شد. اکنون می‌توانید آن را فعال کنید.');
            } else {
                $this->withFlash('error', $result['error'] ?? 'نصب قالب ناموفق بود.');
            }
        } finally {
            @unlink($tmp);
        }

        return $this->redirect('/admin/themes');
    }

    public function activate(Request $request): Response
    {
        if ($denied = $this->denyUnlessCan('themes.manage')) {
            return $denied;
        }
        $slug = (string) $request->route('slug', '');
        $result = $this->themes->activate($slug);
        if ($result['ok']) {
            $this->withFlash('success', 'قالب فعال شد. محتوای سایت مستقل از قالب حفظ می‌شود.');
            $this->logger->channel('admin')->info('Theme activated', ['slug' => $slug, 'actor' => $this->auth->id()]);
        } else {
            $this->withFlash('error', $result['error'] ?? 'فعال‌سازی ناموفق بود.');
        }

        return $this->redirect('/admin/themes');
    }

    public function remove(Request $request): Response
    {
        if ($denied = $this->denyUnlessCan('themes.manage')) {
            return $denied;
        }
        $slug = (string) $request->route('slug', '');
        $result = $this->themes->remove($slug);
        $this->withFlash($result['ok'] ? 'success' : 'error', $result['ok'] ? 'قالب حذف شد.' : ($result['error'] ?? 'حذف ناموفق بود.'));

        return $this->redirect('/admin/themes');
    }

    private function hasUpdate(string $slug, string $current): bool
    {
        try {
            $release = $this->marketplace->latestThemeRelease($slug);
        } catch (\Throwable) {
            return false;
        }

        return $release !== null && version_compare((string) ($release['version'] ?? '0'), $current, '>');
    }
}
