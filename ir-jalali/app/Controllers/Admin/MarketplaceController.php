<?php

declare(strict_types=1);

namespace IRJalali\App\Controllers\Admin;

use IRJalali\App\Controllers\Controller;
use IRJalali\Core\Auth\Auth;
use IRJalali\Core\Http\Request;
use IRJalali\Core\Http\Response;
use IRJalali\Core\Marketplace\MarketplaceClient;
use IRJalali\Core\Plugins\PluginInstaller;
use IRJalali\Core\Plugins\PluginManager;
use IRJalali\Core\Themes\ThemeManager;
use IRJalali\Core\View\View;

/**
 * Marketplace (Part 2 §34–35): browse/search plugins & themes from the
 * configured provider (local packages directory or a remote API) and
 * install with one click. Items expose id/name/slug/version/author/
 * description/requirements/compatibility/changelog/download/license.
 */
final class MarketplaceController extends Controller
{
    public function __construct(
        View $view,
        Auth $auth,
        private readonly MarketplaceClient $marketplace,
        private readonly PluginInstaller $installer,
        private readonly PluginManager $plugins,
        private readonly ThemeManager $themes,
    ) {
        parent::__construct($view, $auth);
    }

    public function index(Request $request): Response
    {
        if ($denied = $this->denyUnlessCan('plugins.manage', 'themes.manage')) {
            return $denied;
        }
        $type = $request->str('type', 'plugin') === 'theme' ? 'theme' : 'plugin';
        $query = mb_substr(trim($request->str('q', '')), 0, 100);

        try {
            $items = $type === 'theme'
                ? $this->marketplace->searchThemes($query)
                : $this->marketplace->searchPlugins($query);
        } catch (\Throwable) {
            $items = [];
        }

        $installedPlugins = array_keys($this->plugins->discover());
        $installedThemes = array_keys($this->themes->all());

        $cards = [];
        foreach ($items as $item) {
            $slug = (string) ($item['slug'] ?? '');
            $cards[] = [
                'slug' => $slug,
                'name' => (string) ($item['name'] ?? $slug),
                'version' => (string) ($item['version'] ?? ''),
                'author' => (string) ($item['author'] ?? ''),
                'description' => (string) ($item['description'] ?? ''),
                'category' => (string) ($item['category'] ?? 'general'),
                'downloads' => (int) ($item['downloads'] ?? 0),
                'license' => (string) ($item['license'] ?? 'free'),
                'installed' => $type === 'theme' ? in_array($slug, $installedThemes, true) : in_array($slug, $installedPlugins, true),
            ];
        }

        return $this->render('admin.marketplace', [
            'cards' => $cards,
            'type' => $type,
            'query' => $query,
            'user' => $this->auth->user(),
        ]);
    }

    public function install(Request $request): Response
    {
        if ($denied = $this->denyUnlessCan('plugins.manage', 'themes.manage')) {
            return $denied;
        }
        $slug = preg_replace('/[^a-z0-9_\-]/i', '', $request->str('slug'));
        $type = $request->str('type', 'plugin') === 'theme' ? 'theme' : 'plugin';
        $activate = $request->input('activate') !== null;

        if ($type === 'plugin') {
            $result = $this->installer->oneClickInstall($slug, false);
            if ($result['success'] && $activate) {
                try {
                    $this->plugins->discover();
                    $plugin = $this->plugins->plugin($slug);
                    if ($plugin !== null && !$plugin->isActive()) {
                        $this->plugins->activate($slug, $plugin->capabilities);
                        $result['message'] .= ' افزونه فعال شد.';
                    }
                } catch (\Throwable $e) {
                    $result['message'] .= ' خطا در فعال‌سازی: ' . $e->getMessage();
                }
            }
            $this->withFlash($result['success'] ? 'success' : 'error', $result['message']);

            return $this->redirect('/admin/marketplace?type=plugin');
        }

        // Theme install via marketplace download.
        $release = $this->marketplace->latestThemeRelease($slug);
        if ($release === null) {
            $this->withFlash('error', 'بسته در مخزن یافت نشد.');

            return $this->redirect('/admin/marketplace?type=theme');
        }
        $tmp = sys_get_temp_dir() . '/irj-mk-theme-' . bin2hex(random_bytes(6)) . '.zip';
        try {
            if (!$this->marketplace->download((string) $release['download_url'], $tmp)) {
                $this->withFlash('error', 'دانلود قالب ناموفق بود.');

                return $this->redirect('/admin/marketplace?type=theme');
            }
            if (!empty($release['checksum']) && (hash_file('sha256', $tmp) ?: '') !== strtolower((string) $release['checksum'])) {
                $this->withFlash('error', 'Checksum قالب معتبر نیست.');

                return $this->redirect('/admin/marketplace?type=theme');
            }
            $result = $this->themes->installFromZip($tmp);
            $this->withFlash($result['ok'] ? 'success' : 'error', $result['ok'] ? 'قالب نصب شد.' : ($result['error'] ?? 'نصب ناموفق بود.'));
        } finally {
            @unlink($tmp);
        }

        return $this->redirect('/admin/marketplace?type=theme');
    }
}
