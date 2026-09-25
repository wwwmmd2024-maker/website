<?php

declare(strict_types=1);

namespace IRJalali\Core\Themes;

use IRJalali\App\Repositories\OptionRepository;
use IRJalali\Core\Database\Database;
use IRJalali\Core\Hooks\Hooks;
use IRJalali\Core\Logging\Logger;

/**
 * Discovers, validates, activates and renders themes.
 * Content NEVER lives in themes — switching themes cannot lose data.
 */
final class ThemeManager
{
    /** @var array<string, Theme>|null */
    private ?array $cache = null;

    /** @var array<string, true> */
    private array $functionsLoaded = [];

    public function __construct(
        private readonly string $themesPath,
        private readonly OptionRepository $options,
        private readonly Database $db,
        private readonly Hooks $hooks,
        private readonly Logger $logger,
    ) {
    }

    /** @return array<string, Theme> slug => theme */
    public function all(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }
        $themes = [];
        foreach (glob($this->themesPath . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $slug = basename($dir);
            if (!preg_match('/^[a-z0-9_\-]+$/i', $slug)) {
                continue;
            }
            $manifestFile = $dir . '/theme.json';
            if (!is_file($manifestFile)) {
                continue;
            }
            $manifest = json_decode((string) file_get_contents($manifestFile), true);
            if (!is_array($manifest)) {
                continue;
            }
            $check = $this->validateManifest($slug, $manifest);
            if (!$check['ok']) {
                $this->logger->channel('themes')->warning('Invalid theme skipped', ['theme' => $slug, 'errors' => $check['errors']]);
                continue;
            }
            $themes[$slug] = Theme::fromManifest($slug, $dir, $manifest);
        }
        $this->cache = $themes;

        return $themes;
    }

    public function get(string $slug): ?Theme
    {
        return $this->all()[$slug] ?? null;
    }

    public function refresh(): void
    {
        $this->cache = null;
    }

    public function activeSlug(): string
    {
        $slug = (string) $this->options->get('active_theme', 'ir-default');
        if ($this->get($slug) === null) {
            return 'ir-default';
        }

        return $slug;
    }

    public function active(): ?Theme
    {
        return $this->get($this->activeSlug());
    }

    /** @return array{ok: bool, error?: string} */
    public function activate(string $slug): array
    {
        $theme = $this->get($slug);
        if ($theme === null) {
            return ['ok' => false, 'error' => 'Theme not found.'];
        }
        if ($theme->isChild() && $this->get($theme->parent) === null) {
            return ['ok' => false, 'error' => "Parent theme [{$theme->parent}] is missing."];
        }
        $compat = $this->checkCompatibility($theme);
        if (!$compat['ok']) {
            return $compat;
        }

        $this->options->set('active_theme', $slug);
        $now = date('Y-m-d H:i:s');
        $this->db->table('themes')->where('is_active', 1)->update(['is_active' => 0]);
        $row = $this->db->table('themes')->where('slug', $slug)->first();
        if ($row === null) {
            $this->db->insert('themes', [
                'slug' => $slug, 'name' => $theme->name, 'version' => $theme->version,
                'author' => $theme->author, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now,
            ]);
        } else {
            $this->db->table('themes')->where('id', $row['id'])->update([
                'name' => $theme->name, 'version' => $theme->version, 'is_active' => 1, 'updated_at' => $now,
            ]);
        }
        $this->hooks->doAction('theme.activated', $slug);

        return ['ok' => true];
    }

    /**
     * Install a theme from an uploaded ZIP (Part 2 §30).
     * Validates theme.json + compatibility, then copies into themes/.
     *
     * @return array{ok: bool, slug?: string, error?: string}
     */
    public function installFromZip(string $zipPath): array
    {
        if (!is_file($zipPath)) {
            return ['ok' => false, 'error' => 'بسته یافت نشد.'];
        }
        $stage = sys_get_temp_dir() . '/irj-theme-' . bin2hex(random_bytes(6));
        if (!mkdir($stage, 0755, true)) {
            return ['ok' => false, 'error' => 'ساخت پوشه موقت ناموفق بود.'];
        }
        try {
            $zip = new \ZipArchive();
            if ($zip->open($zipPath) !== true) {
                return ['ok' => false, 'error' => 'فایل ZIP نامعتبر است.'];
            }
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = (string) $zip->getNameIndex($i);
                if (str_contains($name, '..') || str_starts_with($name, '/')) {
                    $zip->close();

                    return ['ok' => false, 'error' => 'بسته حاوی مسیر ناامن است.'];
                }
            }
            if (!$zip->extractTo($stage)) {
                $zip->close();

                return ['ok' => false, 'error' => 'استخراج بسته ناموفق بود.'];
            }
            $zip->close();

            $root = is_file($stage . '/theme.json') ? $stage : null;
            if ($root === null) {
                foreach (glob($stage . '/*', GLOB_ONLYDIR) ?: [] as $candidate) {
                    if (is_file($candidate . '/theme.json')) {
                        $root = $candidate;
                        break;
                    }
                }
            }
            if ($root === null) {
                return ['ok' => false, 'error' => 'فایل theme.json در بسته یافت نشد.'];
            }

            $manifest = json_decode((string) file_get_contents($root . '/theme.json'), true);
            if (!is_array($manifest)) {
                return ['ok' => false, 'error' => 'مانیفست theme.json نامعتبر است.'];
            }
            $slug = (string) ($manifest['slug'] ?? basename($root));
            if (!preg_match('/^[a-z0-9_\-]+$/i', $slug)) {
                return ['ok' => false, 'error' => 'شناسه قالب نامعتبر است.'];
            }
            $check = $this->validateManifest($slug, $manifest);
            if (!$check['ok']) {
                return ['ok' => false, 'error' => implode(' ', array_slice($check['errors'], 0, 2))];
            }
            $theme = Theme::fromManifest($slug, $root, $manifest);
            $compat = $this->checkCompatibility($theme);
            if (!$compat['ok']) {
                return ['ok' => false, 'error' => $compat['error'] ?? 'ناسازگار'];
            }
            $dest = $this->themesPath . '/' . $slug;
            if (is_dir($dest)) {
                return ['ok' => false, 'error' => 'قالبی با این شناسه از قبل نصب است؛ ابتدا آن را حذف کنید.'];
            }
            $this->copyDir($root, $dest);
            if (!is_file($dest . '/theme.json')) {
                $this->removeDir($dest);

                return ['ok' => false, 'error' => 'انتقال فایل‌ها ناموفق بود.'];
            }
            $this->refresh();
            $this->logger->channel('themes')->info('Theme installed', ['slug' => $slug]);

            return ['ok' => true, 'slug' => $slug];
        } finally {
            $this->removeDir($stage);
        }
    }

    /** Remove an installed theme directory (active theme is protected). */
    public function remove(string $slug): array
    {
        $theme = $this->get($slug);
        if ($theme === null) {
            return ['ok' => false, 'error' => 'قالب یافت نشد.'];
        }
        if ($this->activeSlug() === $slug) {
            return ['ok' => false, 'error' => 'قالب فعال را نمی‌توان حذف کرد؛ ابتدا قالب دیگری را فعال کنید.'];
        }
        $this->removeDir($theme->path);
        $this->db->table('themes')->where('slug', $slug)->delete();
        $this->refresh();
        $this->logger->channel('themes')->info('Theme removed', ['slug' => $slug]);

        return ['ok' => true];
    }

    private function copyDir(string $from, string $to): void
    {
        if (!is_dir($to)) {
            mkdir($to, 0755, true);
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($from, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        $baseLen = strlen(rtrim($from, '/') . '/');
        foreach ($iterator as $item) {
            $target = $to . '/' . substr($item->getPathname(), $baseLen);
            if ($item->isDir()) {
                if (!is_dir($target)) {
                    mkdir($target, 0755, true);
                }
            } else {
                copy($item->getPathname(), $target);
            }
        }
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $file) {
            $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
        }
        @rmdir($dir);
    }

    /** @return array{ok: bool, error?: string} */
    public function checkCompatibility(Theme $theme): array
    {
        $requires = (string) ($theme->requires['ir-jalali'] ?? '');
        if ($requires === '') {
            return ['ok' => true];
        }
        if (!\IRJalali\Core\Version\Compatibility::satisfies(\IRJalali\Core\Kernel\Application::get()->version(), $requires)) {
            return ['ok' => false, 'error' => "Theme requires IR-Jalali {$requires}."];
        }

        return ['ok' => true];
    }

    /** @return array{ok: bool, errors: list<string>} */
    public function validateManifest(string $slug, array $manifest): array
    {
        $errors = [];
        if (empty($manifest['name']) || !is_string($manifest['name'])) {
            $errors[] = 'Missing theme name.';
        }
        if (isset($manifest['slug']) && $manifest['slug'] !== $slug) {
            $errors[] = 'Manifest slug must match directory name.';
        }
        if (isset($manifest['version']) && !preg_match('/^\d+\.\d+\.\d+/', (string) $manifest['version'])) {
            $errors[] = 'Version must be semantic (MAJOR.MINOR.PATCH).';
        }
        if (isset($manifest['parent']) && !is_string($manifest['parent'])) {
            $errors[] = 'Parent must be a theme slug string.';
        }

        return ['ok' => $errors === [], 'errors' => $errors];
    }

    /**
     * Resolve a template file with child → parent fallback.
     * $relative like 'templates/page.php' or 'parts/header.php'.
     */
    public function file(string $slug, string $relative): ?string
    {
        $relative = ltrim($relative, '/');
        if (str_contains($relative, '..') || !preg_match('#^(templates|parts)/[a-z0-9_\-]+\.php$#i', $relative)) {
            return null;
        }
        $chain = $this->chain($slug);
        foreach ($chain as $theme) {
            $file = $theme->path . '/' . $relative;
            if (is_file($file)) {
                return $file;
            }
        }

        return null;
    }

    /** @return list<Theme> child-first chain */
    public function chain(string $slug): array
    {
        $chain = [];
        $seen = [];
        $current = $this->get($slug);
        while ($current !== null && !isset($seen[$current->slug]) && count($chain) < 5) {
            $seen[$current->slug] = true;
            $chain[] = $current;
            $current = $current->parent !== null ? $this->get($current->parent) : null;
        }

        return $chain;
    }

    /**
     * Render a theme template file with sandboxed variables.
     *
     * @param array<string, mixed> $data
     */
    public function renderFile(string $file, array $data = []): string
    {
        $e = fn(mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $data['e'] = $e;
        extract($data, EXTR_SKIP);
        ob_start();
        require $file;

        return (string) ob_get_clean();
    }

    /**
     * Load the theme's functions.php once per request (child then parent).
     * The file runs with $theme, $hooks and $app in scope.
     */
    public function loadFunctions(string $slug): void
    {
        foreach (array_reverse($this->chain($slug)) as $theme) {
            if (isset($this->functionsLoaded[$theme->slug])) {
                continue;
            }
            $this->functionsLoaded[$theme->slug] = true;
            $file = $theme->path . '/functions.php';
            if (is_file($file)) {
                $hooks = $this->hooks;
                $app = \IRJalali\Core\Kernel\Application::get();
                try {
                    require $file;
                } catch (\Throwable $e) {
                    $this->logger->channel('themes')->error('Theme functions.php failed', ['theme' => $theme->slug, 'error' => $e->getMessage()]);
                }
            }
        }
    }

    /** Public asset URL served via the /theme-assets proxy route. */
    public function assetUrl(string $slug, string $asset): string
    {
        return '/theme-assets/' . $slug . '/' . ltrim($asset, '/');
    }

    /**
     * Resolve a theme asset to a real file (child → parent). Null when missing
     * or when the path tries to escape the theme directory.
     */
    public function assetFile(string $slug, string $asset): ?string
    {
        $asset = ltrim(str_replace('\\', '/', $asset), '/');
        if ($asset === '' || str_contains($asset, '..') || str_starts_with($asset, '/')) {
            return null;
        }
        if (!preg_match('#^[a-z0-9_\-./]+$#i', $asset)) {
            return null;
        }
        $ext = strtolower(pathinfo($asset, PATHINFO_EXTENSION));
        if (!in_array($ext, ['css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'avif', 'svg', 'woff', 'woff2', 'map'], true)) {
            return null;
        }
        foreach ($this->chain($slug) as $theme) {
            $file = $theme->path . '/' . $asset;
            $real = realpath($file);
            if ($real !== false && is_file($real) && str_starts_with($real, realpath($theme->path) . '/')) {
                return $real;
            }
        }

        return null;
    }
}
