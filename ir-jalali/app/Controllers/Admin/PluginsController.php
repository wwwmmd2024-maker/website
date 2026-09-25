<?php

declare(strict_types=1);

namespace IRJalali\App\Controllers\Admin;

use IRJalali\App\Controllers\Controller;
use IRJalali\App\Services\NotificationService;
use IRJalali\Core\Auth\Auth;
use IRJalali\Core\Http\Request;
use IRJalali\Core\Http\Response;
use IRJalali\Core\Logging\Logger;
use IRJalali\Core\Marketplace\MarketplaceClient;
use IRJalali\Core\Plugins\PluginInstaller;
use IRJalali\Core\Plugins\PluginManager;
use IRJalali\Core\Plugins\PluginUpdater;
use IRJalali\Core\View\View;

/**
 * Plugin Manager (Part 2 §22–29): list, upload/scan/install, activate with
 * dependency resolution, deactivate, one-click update (with automatic
 * backup + rollback) and full uninstall.
 */
final class PluginsController extends Controller
{
    public function __construct(
        View $view,
        Auth $auth,
        private readonly PluginManager $plugins,
        private readonly PluginInstaller $installer,
        private readonly PluginUpdater $updater,
        private readonly MarketplaceClient $marketplace,
        private readonly NotificationService $notifications,
        private readonly Logger $logger,
    ) {
        parent::__construct($view, $auth);
    }

    public function index(Request $request): Response
    {
        if ($denied = $this->denyUnlessCan('plugins.manage')) {
            return $denied;
        }

        $items = [];
        foreach ($this->plugins->discover() as $slug => $plugin) {
            $mustUse = $this->plugins->isMustUse($plugin);
            $items[] = [
                'slug' => $slug,
                'name' => $plugin->name,
                'version' => $plugin->version,
                'author' => (string) ($plugin->manifest['author'] ?? ''),
                'description' => (string) ($plugin->manifest['description'] ?? ''),
                'active' => $plugin->isActive(),
                'must_use' => $mustUse,
                'capabilities' => $plugin->capabilities,
                'dependencies' => (array) ($plugin->manifest['dependencies'] ?? []),
                'broken' => $this->dependencyReport($slug)['broken'],
                'adminPages' => array_values(array_filter(
                    $this->plugins->adminPages(),
                    fn (array $p): bool => ($p['plugin'] ?? '') === $slug
                )),
                'release' => $this->latestRelease($slug, $plugin->version),
            ];
        }

        return $this->render('admin.plugins', [
            'items' => $items,
            'user' => $this->auth->user(),
        ]);
    }

    public function upload(Request $request): Response
    {
        if ($denied = $this->denyUnlessCan('plugins.manage')) {
            return $denied;
        }
        $file = $request->file('package');
        if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $this->withFlash('error', 'فایل بسته انتخاب نشد.');

            return $this->redirect('/admin/plugins');
        }

        $result = $this->installer->installFromUpload($file);
        $this->withFlash($result['success'] ? 'success' : 'error', $result['message']);
        if ($result['success']) {
            $this->notifications->pushToAdmins('plugin.update', 'افزونه نصب شد', "افزونه {$result['slug']} با موفقیت نصب شد.", '/admin/plugins');
        }

        return $this->redirect('/admin/plugins');
    }

    public function activate(Request $request): Response
    {
        if ($denied = $this->denyUnlessCan('plugins.manage')) {
            return $denied;
        }
        $slug = (string) $request->route('slug', '');
        $deps = $this->dependencyReport($slug);
        if ($deps['broken'] !== []) {
            $this->withFlash('error', 'وابستگی‌های نصب‌نشده: ' . implode('، ', $deps['broken']) . ' — ابتدا آن‌ها را نصب و فعال کنید.');

            return $this->redirect('/admin/plugins');
        }

        try {
            $plugin = $this->plugins->plugin($slug);
            if ($plugin === null) {
                throw new \RuntimeException('Plugin not found.');
            }
            // Admin activation grants every capability the manifest declares.
            $this->plugins->activate($slug, $plugin->capabilities);
            $this->withFlash('success', "افزونه «{$plugin->name}» فعال شد.");
            $this->logger->channel('admin')->info('Plugin activated', ['slug' => $slug, 'actor' => $this->auth->id()]);
        } catch (\Throwable $e) {
            $this->withFlash('error', 'فعال‌سازی ناموفق بود: ' . $e->getMessage());
        }

        return $this->redirect('/admin/plugins');
    }

    public function deactivate(Request $request): Response
    {
        if ($denied = $this->denyUnlessCan('plugins.manage')) {
            return $denied;
        }
        $slug = (string) $request->route('slug', '');
        try {
            $this->plugins->deactivate($slug);
            $this->withFlash('success', 'افزونه غیرفعال شد. داده‌ها حفظ می‌شوند.');
        } catch (\Throwable $e) {
            $this->withFlash('error', $e->getMessage());
        }

        return $this->redirect('/admin/plugins');
    }

    public function update(Request $request): Response
    {
        if ($denied = $this->denyUnlessCan('plugins.manage')) {
            return $denied;
        }
        $slug = (string) $request->route('slug', '');
        $result = $this->updater->update($slug);
        $this->withFlash($result['success'] ? 'success' : 'error', $result['message']);

        return $this->redirect('/admin/plugins');
    }

    public function uninstall(Request $request): Response
    {
        if ($denied = $this->denyUnlessCan('plugins.manage')) {
            return $denied;
        }
        $slug = (string) $request->route('slug', '');
        $plugin = $this->plugins->plugin($slug);
        try {
            $this->plugins->uninstall($slug);
            // Remove the plugin directory itself (full uninstall).
            if ($plugin !== null && is_dir($plugin->path)) {
                $this->removeDir($plugin->path);
            }
            $this->plugins->discover();
            $this->withFlash('success', 'افزونه به‌طور کامل حذف شد.');
            $this->notifications->pushToAdmins('plugin.update', 'افزونه حذف شد', "افزونه {$slug} حذف شد.", '/admin/plugins');
        } catch (\Throwable $e) {
            $this->withFlash('error', $e->getMessage());
        }

        return $this->redirect('/admin/plugins');
    }

    /** @return array{missing: list<string>, inactive: list<string>, broken: list<string>} */
    private function dependencyReport(string $slug): array
    {
        $plugin = $this->plugins->plugin($slug);
        if ($plugin === null) {
            return ['missing' => [], 'inactive' => [], 'broken' => []];
        }
        $missing = [];
        $inactive = [];
        foreach ((array) ($plugin->manifest['dependencies'] ?? []) as $dep) {
            $depPlugin = $this->plugins->plugin((string) $dep);
            if ($depPlugin === null) {
                $missing[] = (string) $dep;
            } elseif (!$depPlugin->isActive()) {
                $inactive[] = (string) $dep;
            }
        }

        return ['missing' => $missing, 'inactive' => $inactive, 'broken' => array_merge($missing, $inactive)];
    }

    /** @return array{version: string, has_update: bool}|null */
    private function latestRelease(string $slug, string $current): ?array
    {
        try {
            $release = $this->marketplace->latestPluginRelease($slug);
        } catch (\Throwable) {
            return null;
        }
        if ($release === null) {
            return null;
        }

        return [
            'version' => (string) ($release['version'] ?? ''),
            'has_update' => version_compare((string) ($release['version'] ?? '0'), $current, '>'),
        ];
    }

    private function removeDir(string $dir): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $file) {
            $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
        }
        @rmdir($dir);
    }
}
