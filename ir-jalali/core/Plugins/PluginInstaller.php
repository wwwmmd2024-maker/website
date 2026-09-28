<?php

declare(strict_types=1);

namespace IRJalali\Core\Plugins;

use IRJalali\Core\Config\Config;
use IRJalali\Core\Kernel\Application;
use IRJalali\Core\Kernel\Container;
use IRJalali\Core\Logging\Logger;
use IRJalali\Core\Marketplace\MarketplaceClient;

/**
 * Install pipeline: source → validate → scan → stage → activate-ready.
 * Install NEVER auto-activates: the admin reviews capabilities first.
 */
final class PluginInstaller
{
    public function __construct(private readonly Container $app)
    {
    }

    /** @param array{name: string, tmp_name: string, size: int, error: int} $file */
    public function installFromUpload(array $file): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return $this->fail('آپلود ناموفق بود.');
        }
        if (($file['size'] ?? 0) > 30 * 1024 * 1024) {
            return $this->fail('حجم فایل بیش از ۳۰ مگابایت است.');
        }
        if (!str_ends_with(strtolower((string) ($file['name'] ?? '')), '.zip')) {
            return $this->fail('فقط فایل ZIP پذیرفته می‌شود.');
        }
        $tmp = $this->tmpFile();
        if (!move_uploaded_file($file['tmp_name'], $tmp) && !copy($file['tmp_name'], $tmp)) {
            return $this->fail('ذخیره فایل آپلودشده ناموفق بود.');
        }

        try {
            return $this->installFromZip($tmp);
        } finally {
            @unlink($tmp);
        }
    }

    public function installFromUrl(string $url): array
    {
        $tmp = $this->tmpFile();
        try {
            /** @var HttpClient $http */
            $http = $this->app->make(HttpClient::class);
            if (!$http->download($url, $tmp, 30 * 1024 * 1024)) {
                return $this->fail('دانلود بسته ناموفق بود.');
            }

            return $this->installFromZip($tmp);
        } catch (\Throwable $e) {
            return $this->fail('دانلود بسته ناموفق بود: ' . $e->getMessage());
        } finally {
            @unlink($tmp);
        }
    }

    public function installFromMarketplace(string $slug, ?string $version = null): array
    {
        /** @var MarketplaceClient $marketplace */
        $marketplace = $this->app->make(MarketplaceClient::class);
        $release = $marketplace->latestPluginRelease($slug, $version);
        if ($release === null) {
            return $this->fail('بسته در مخزن یافت نشد.');
        }
        $tmp = $this->tmpFile();
        try {
            if (!$marketplace->download($release['download_url'], $tmp)) {
                return $this->fail('دانلود بسته ناموفق بود.');
            }
            if ($release['checksum'] !== '' && (hash_file('sha256', $tmp) ?: '') !== strtolower($release['checksum'])) {
                return $this->fail('Checksum بسته معتبر نیست.');
            }

            return $this->installFromZip($tmp);
        } finally {
            @unlink($tmp);
        }
    }

    /**
     * One-click install: marketplace → files (+ optional activation).
     *
     * @return array{success: bool, slug: string, message: string, warnings: list<string>}
     */
    public function oneClickInstall(string $slug, bool $activate = false, array $granted = []): array
    {
        $result = $this->installFromMarketplace($slug);
        if (!$result['success'] || !$activate) {
            return $result;
        }
        try {
            /** @var PluginManager $manager */
            $manager = $this->app->make(PluginManager::class);
            $manager->activate($result['slug'], $granted);
            $result['message'] .= ' افزونه فعال شد.';
        } catch (\Throwable $e) {
            $result['message'] .= ' خطا در فعال‌سازی: ' . $e->getMessage();
        }

        return $result;
    }

    /** @return array{success: bool, slug: string, message: string, warnings: list<string>} */
    public function installFromZip(string $zipPath): array
    {
        $stage = $this->tmpDir();
        try {
            $this->extractSafely($zipPath, $stage);
            $root = $this->findManifestRoot($stage);
            if ($root === null) {
                return $this->fail('فایل plugin.json در بسته یافت نشد.');
            }
            try {
                $manifest = PluginManifestLoader::load($root . '/plugin.json');
            } catch (\Throwable $e) {
                return $this->fail('مانیفست نامعتبر است: ' . $e->getMessage());
            }

            $coreVersion = (string) $this->app->make(Config::class)->get('versions.core', '1.1.0');
            if (!PluginManifestLoader::checkCoreConstraint($manifest, $coreVersion)) {
                $need = (string) ($manifest['requires']['core'] ?? '?');

                return $this->fail("این افزونه به هسته {$need} نیاز دارد (نسخه فعلی {$coreVersion}).");
            }
            if (isset($manifest['requires']['php']) && !\IRJalali\Core\Version\Compatibility::satisfies(PHP_VERSION, (string) $manifest['requires']['php'])) {
                return $this->fail('نسخه PHP سازگار نیست (نیاز: ' . $manifest['requires']['php'] . ').');
            }

            $report = $this->scanDirectory($root);
            if (!$report->safe()) {
                return [
                    'success' => false, 'slug' => (string) $manifest['slug'],
                    'message' => 'اسکن امنیتی ناموفق بود: ' . implode(' | ', array_slice($report->errors, 0, 3)),
                    'warnings' => $report->warnings,
                ];
            }

            // Entry class must exist and extend the provider base.
            $studly = PluginManifestLoader::studly((string) $manifest['slug']);
            if (!is_file($root . '/Plugin.php')) {
                return $this->fail('فایل Plugin.php (کلاس ورودی) یافت نشد.');
            }

            $dest = $this->pluginsPath() . '/' . $studly;
            if (is_dir($dest)) {
                return $this->fail('افزونه‌ای با همین نام از قبل نصب شده است؛ ابتدا آن را حذف کنید.');
            }
            // Copy (not rename): staging (tmp) and plugins/ may live on
            // different filesystems where rename() cannot work.
            try {
                $this->copyDir($root, $dest);
            } catch (\Throwable) {
                $this->removeDir($dest);

                return $this->fail('انتقال فایل‌ها به پوشه افزونه‌ها ناموفق بود.');
            }
            if (!is_file($dest . '/plugin.json')) {
                $this->removeDir($dest);

                return $this->fail('انتقال فایل‌ها به پوشه افزونه‌ها ناموفق بود.');
            }

            $this->app->make(Logger::class)->info('plugin.installed', ['slug' => $manifest['slug']]);

            return [
                'success' => true,
                'slug' => (string) $manifest['slug'],
                'message' => 'نصب شد. برای استفاده، افزونه را فعال کنید.',
                'warnings' => $report->warnings,
            ];
        } finally {
            $this->removeDir($stage);
        }
    }

    /** Scan a ZIP without installing (update pipeline uses this). */
    public function scan(string $zipPath): ScanReport
    {
        $stage = $this->tmpDir();
        try {
            $this->extractSafely($zipPath, $stage);

            return $this->scanDirectory($stage);
        } finally {
            $this->removeDir($stage);
        }
    }

    /** Extract a verified ZIP over an existing install dir (update pipeline). */
    public function extractOver(string $zipPath, string $targetPath): void
    {
        $stage = $this->tmpDir();
        try {
            $this->extractSafely($zipPath, $stage);
            $root = $this->findManifestRoot($stage) ?? $stage;
            $this->removeDirContents($targetPath);
            $this->copyDir($root, $targetPath);
        } finally {
            $this->removeDir($stage);
        }
    }

    private function scanDirectory(string $dir): ScanReport
    {
        /** @var PluginSecurityScanner $scanner */
        $scanner = $this->app->make(PluginSecurityScanner::class);
        $result = $scanner->scan($dir);
        $lintErrors = method_exists($scanner, 'lint') ? $scanner->lint($dir) : [];
        $errors = [...$result['errors'], ...array_map(fn (string $f): string => $f . ': syntax error (php -l).', $lintErrors)];

        return new ScanReport(array_values(array_unique($errors)), $result['warnings'], $result['files']);
    }

    private function extractSafely(string $zipPath, string $dest): void
    {
        $zip = new \ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new \RuntimeException('Cannot open ZIP archive.');
        }
        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = (string) $zip->getNameIndex($i);
                if ($name === '' || str_contains($name, '..') || str_starts_with($name, '/') || preg_match('#^[A-Za-z]:#', $name)) {
                    throw new \RuntimeException('Unsafe entry in ZIP: ' . $name);
                }
            }
            if (!$zip->extractTo($dest)) {
                throw new \RuntimeException('ZIP extraction failed.');
            }
        } finally {
            $zip->close();
        }
    }

    private function findManifestRoot(string $stage): ?string
    {
        if (is_file($stage . '/plugin.json')) {
            return $stage;
        }
        foreach (scandir($stage) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            if (is_dir($stage . '/' . $entry) && is_file($stage . '/' . $entry . '/plugin.json')) {
                return $stage . '/' . $entry;
            }
        }

        return null;
    }

    private function pluginsPath(): string
    {
        $configured = $this->app->make(Config::class)->get('plugins.path');

        return is_string($configured) && $configured !== ''
            ? $configured
            : $this->app->make(Application::class)->basePath('plugins');
    }

    private function tmpFile(): string
    {
        return sys_get_temp_dir() . '/irj-pkg-' . bin2hex(random_bytes(8)) . '.zip';
    }

    private function tmpDir(): string
    {
        $dir = sys_get_temp_dir() . '/irj-pkg-' . bin2hex(random_bytes(8));
        mkdir($dir, 0755, true);

        return $dir;
    }

    private function copyDir(string $from, string $to): void
    {
        if (!is_dir($to)) {
            mkdir($to, 0755, true);
        }
        foreach (scandir($from) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $src = $from . '/' . $entry;
            $dst = $to . '/' . $entry;
            if (is_dir($src) && !is_link($src)) {
                $this->copyDir($src, $dst);
            } elseif (is_file($src)) {
                copy($src, $dst);
            }
        }
    }

    private function removeDirContents(string $dir): void
    {
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . '/' . $entry;
            if (is_dir($path) && !is_link($path)) {
                $this->removeDir($path);
            } else {
                @unlink($path);
            }
        }
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $this->removeDirContents($dir);
        @rmdir($dir);
    }

    /** @return array{success: bool, slug: string, message: string, warnings: list<string>} */
    private function fail(string $message): array
    {
        return ['success' => false, 'slug' => '', 'message' => $message, 'warnings' => []];
    }
}
