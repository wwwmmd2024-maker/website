<?php

declare(strict_types=1);

namespace IRJalali\Plugins\IrCache;

use IRJalali\Core\Cache\CacheInterface;
use IRJalali\Core\Http\Request;
use IRJalali\Core\Http\Response;
use IRJalali\Core\Kernel\Application;
use IRJalali\Core\Plugins\PluginContext;
use IRJalali\Core\Plugins\PluginServiceProvider;
use IRJalali\Core\Sdk\IRJalali;
use IRJalali\Core\Security\Csrf;

/**
 * IR-Cache (Part 1 §19, Part 3 §34).
 *
 * Page Cache: serves cached HTML for guest GET requests via the `http.before`
 * extension point and stores rendered pages via `http.response`. Logged-in
 * users, admin/API/install routes and non-200 responses are never cached.
 *
 * Fragment Cache: plugins/themes call
 *   \IRJalali\Plugins\IrCache\FragmentCache::render('key', $ttl, fn() => …)
 * to cache expensive partials on any cache driver (file by default, Redis
 * optional when configured — the system works without it).
 */
final class Plugin extends PluginServiceProvider
{
    private const PREFIX = 'pagecache:';

    public function boot(PluginContext $context): void
    {
        $sdk = IRJalali::for('ir-cache');

        // Serve a cached page before routing (guest GET HTML only).
        $sdk->filter('http.before', function ($cached, Request $request) {
            if ($cached instanceof Response || !$this->pageCacheEnabled()) {
                return $cached;
            }
            if (!$this->cacheable($request)) {
                return null;
            }
            $entry = $this->cache()->get($this->key($request));
            if (!is_array($entry) || !isset($entry['html'], $entry['headers'])) {
                return null;
            }

            return new Response((string) $entry['html'], 200, (array) $entry['headers'])
                ->withHeader('X-IRJ-Cache', 'HIT');
        }, 1);

        // Store freshly rendered pages.
        $sdk->filter('http.response', function (Response $response, Request $request): Response {
            if (!$this->pageCacheEnabled() || !$this->cacheable($request)) {
                return $response;
            }
            // Never re-store (or relabel) a response already served from cache.
            if (($response->getHeaders()['X-IRJ-Cache'] ?? '') === 'HIT') {
                return $response;
            }
            if ($response->getStatus() !== 200 || $response->getContent() === '') {
                return $response;
            }
            // CSRF tokens are per-session: caching a page that embeds one would
            // hand visitor A's token to visitor B (forms then fail with 419 and
            // the token leaks). Such pages are never cached.
            if (str_contains($response->getContent(), 'name="_token"')) {
                return $response;
            }
            $headers = [];
            foreach ($response->getHeaders() as $name => $value) {
                if (in_array(strtolower((string) $name), ['content-type', 'cache-control'], true)) {
                    $headers[(string) $name] = $value;
                }
            }
            $headers['Content-Type'] ??= 'text/html; charset=utf-8';
            $ttl = max(60, min(86400, (int) $this->sdk()->setting('ttl', 600)));
            $this->cache()->set($this->key($request), [
                'html' => $response->getContent(),
                'headers' => $headers,
                'cached_at' => date('c'),
            ], $ttl);

            return $response->withHeader('X-IRJ-Cache', 'MISS');
        }, 90);

        $sdk->registerAdminPage('cache', 'مدیریت کش', fn (Request $request): string => $this->adminPage($request), '⚡');

        $sdk->registerSettings([
            ['key' => 'enabled', 'type' => 'checkbox', 'label' => 'کش صفحات فعال', 'default' => '1'],
            ['key' => 'ttl', 'type' => 'number', 'label' => 'عمر کش (ثانیه)', 'default' => '600'],
        ]);

        // Scheduled purge of stale entries (defense in depth; FileCache TTLs too).
        $sdk->cron('purge', 'پاکسازی کش منقضی‌شده', 'hourly', function (): void {
            // FileCache expires entries lazily; touch the index to prune files.
            $this->cache()->clearPrefix('pagecache:expired:');
        });
    }

    public static function flush(): int
    {
        try {
            return Application::get()->make(CacheInterface::class)->clearPrefix(self::PREFIX);
        } catch (\Throwable) {
            return 0;
        }
    }

    private function adminPage(Request $request): string
    {
        $sdk = $this->sdk();
        $flushed = null;
        if ($request->isMethod('POST')) {
            if ($request->str('action') === 'flush') {
                $flushed = self::flush();
            } else {
                $sdk->saveSetting('enabled', $request->input('enabled') ? '1' : '0');
                $sdk->saveSetting('ttl', (string) max(60, min(86400, (int) $request->input('ttl', 600))));
            }
        }
        $enabled = $sdk->setting('enabled', '1') === '1';
        $ttl = (int) $sdk->setting('ttl', 600);
        $csrf = Application::get()->make(Csrf::class)->field();

        ob_start(); ?>
        <h2 style="margin-top:0">⚡ مدیریت کش</h2>
        <?php if ($flushed !== null): ?><p class="flash-ok">کش پاک شد (<?= e((string) $flushed) ?> ورودی).</p><?php endif; ?>
        <form method="post" action="/admin/plugin/ir-cache--cache">
          <?= $csrf ?>
          <label style="display:flex;gap:8px;align-items:center;font-weight:400">
            <input type="checkbox" name="enabled" value="1" <?= $enabled ? 'checked' : '' ?>> کش کامل صفحات برای بازدیدکنندگان مهمان
          </label>
          <label>عمر کش (ثانیه)</label>
          <input type="number" name="ttl" value="<?= e((string) $ttl) ?>" min="60" max="86400" style="max-width:180px" dir="ltr">
          <button class="btn small" type="submit">ذخیره</button>
        </form>
        <form method="post" action="/admin/plugin/ir-cache--cache" style="margin-top:14px" data-confirm="تمام صفحات کش‌شده حذف شوند؟">
          <?= $csrf ?>
          <input type="hidden" name="action" value="flush">
          <button class="btn small danger" type="submit">پاک‌سازی کامل کش صفحات</button>
        </form>
        <p class="muted" style="margin-top:14px;font-size:12.5px">کاربران واردشده، بخش مدیریت، API و صفحات خطادار هرگز کش نمی‌شوند. کش فرگمنت از طریق <span dir="ltr">FragmentCache::render()</span> در دسترس قالب‌ها و افزونه‌هاست.</p>
        <?php return (string) ob_get_clean();
    }

    private function cacheable(Request $request): bool
    {
        if ($request->method() !== 'GET') {
            return false;
        }
        $path = $request->path();
        foreach (['/admin', '/api/', '/install', '/setup', '/login', '/logout'] as $prefix) {
            if ($path === rtrim($prefix, '/') || str_starts_with($path, $prefix)) {
                return false;
            }
        }
        // A session cookie means possibly logged-in — never cache.
        if (isset($_COOKIE['IRJSESSION'])) {
            return false;
        }

        return true;
    }

    private function key(Request $request): string
    {
        $query = is_array($_GET ?? null) ? http_build_query($_GET) : '';

        return self::PREFIX . sha1($request->path() . '?' . $query);
    }

    private function pageCacheEnabled(): bool
    {
        try {
            return $this->sdk()->setting('enabled', '1') === '1';
        } catch (\Throwable) {
            return false;
        }
    }

    private function cache(): CacheInterface
    {
        return Application::get()->make(CacheInterface::class);
    }

    private function sdk(): IRJalali
    {
        return IRJalali::for('ir-cache');
    }
}
