<?php

declare(strict_types=1);

namespace IRJalali\Plugins\IrPwa;

use IRJalali\App\Repositories\OptionRepository;
use IRJalali\Core\Http\Request;
use IRJalali\Core\Http\Response;
use IRJalali\Core\Kernel\Application;
use IRJalali\Core\Plugins\PluginContext;
use IRJalali\Core\Plugins\PluginServiceProvider;
use IRJalali\Core\Sdk\IRJalali;

/**
 * IR-PWA (Part 3): Progressive Web App support.
 * Dynamic web manifest, a service worker (cache-first for static assets,
 * network-first for HTML with offline fallback) and install icons — served
 * through plugin routes and injected via the `front.head_meta` extension
 * point. No core edits.
 */
final class Plugin extends PluginServiceProvider
{
    public function boot(PluginContext $context): void
    {
        $sdk = IRJalali::for('ir-pwa');

        $sdk->registerSettings([
            ['key' => 'theme_color', 'type' => 'text', 'label' => 'رنگ تم (هگز)', 'default' => '#0d9488'],
            ['key' => 'offline_text', 'type' => 'text', 'label' => 'متن صفحه آفلاین', 'default' => 'شما آفلاین هستید. اتصال اینترنت را بررسی کنید.'],
        ]);

        $sdk->registerRoute(['GET'], '/manifest.webmanifest', fn (Request $request): Response => $this->manifest());
        $sdk->registerRoute(['GET'], '/sw.js', fn (Request $request): Response => $this->serviceWorker());
        $sdk->registerRoute(['GET'], '/pwa/icon-512.png', fn (Request $request): Response => $this->icon('icon-512.png'));
        $sdk->registerRoute(['GET'], '/pwa/icon-192.png', fn (Request $request): Response => $this->icon('icon-512.png'));

        // Inject manifest link + theme color + SW registration into theme heads.
        $sdk->filter('front.head_meta', function (string $html, array $data, $ctx): string {
            $color = $this->safeSetting('theme_color', '#0d9488');

            return $html
                . '<link rel="manifest" href="/manifest.webmanifest">'
                . '<meta name="theme-color" content="' . e($color) . '">'
                . '<link rel="icon" type="image/png" href="/pwa/icon-192.png">'
                . '<script>if("serviceWorker" in navigator){window.addEventListener("load",function(){navigator.serviceWorker.register("/sw.js").catch(function(){})});}</script>';
        }, 5);

        $sdk->registerAdminPage('pwa', 'وب‌اپ (PWA)', fn (Request $request): string => $this->adminPage($request), '📱');
    }

    private function manifest(): Response
    {
        $title = $this->siteOption('site_title', 'IR-Jalali');
        $color = $this->safeSetting('theme_color', '#0d9488');
        $json = [
            'name' => $title,
            'short_name' => mb_substr($title, 0, 12),
            'description' => $this->siteOption('tagline', ''),
            'lang' => 'fa',
            'dir' => 'rtl',
            'start_url' => '/',
            'scope' => '/',
            'display' => 'standalone',
            'background_color' => '#ffffff',
            'theme_color' => $color,
            'icons' => [
                ['src' => '/pwa/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any maskable'],
                ['src' => '/pwa/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable'],
            ],
        ];

        return Response::json($json)
            ->withHeader('Content-Type', 'application/manifest+json; charset=utf-8');
    }

    private function serviceWorker(): Response
    {
        $offlineText = $this->safeSetting('offline_text', 'شما آفلاین هستید.');
        $version = 'irj-v1';
        $js = <<<JS
/* IR-Jalali service worker: cache-first static assets, network-first pages. */
const CACHE = '{$version}';
const STATIC_CACHE = '{$version}-static';
const PAGE_CACHE = '{$version}-pages';

self.addEventListener('install', (event) => {
  event.waitUntil(caches.open(STATIC_CACHE).then((c) => c.addAll(['/pwa/icon-192.png'])).catch(() => {}));
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => Promise.all(
      keys.filter((k) => k.startsWith('irj-') && k !== STATIC_CACHE && k !== PAGE_CACHE)
          .map((k) => caches.delete(k))
    )).then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (req.method !== 'GET') return;
  const url = new URL(req.url);
  if (url.origin !== self.location.origin) return;
  if (url.pathname.startsWith('/admin')) return;

  const isStatic = /^\/(assets|theme-assets|pwa)\//.test(url.pathname)
    || /\.(css|js|png|jpg|jpeg|webp|svg|woff2?)$/.test(url.pathname);

  if (isStatic) {
    event.respondWith(
      caches.open(STATIC_CACHE).then(async (cache) => {
        const hit = await cache.match(req);
        if (hit) return hit;
        try {
          const res = await fetch(req);
          if (res.ok) cache.put(req, res.clone());
          return res;
        } catch (e) {
          return new Response('', { status: 404 });
        }
      })
    );
    return;
  }

  if (req.headers.get('accept')?.includes('text/html')) {
    event.respondWith(
      fetch(req)
        .then((res) => {
          const copy = res.clone();
          caches.open(PAGE_CACHE).then((c) => c.put(req, copy)).catch(() => {});
          return res;
        })
        .catch(async () => {
          const cached = await caches.match(req);
          if (cached) return cached;
          return new Response(
            '<!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><title>آفلاین</title></head>' +
            '<body style="font-family:Tahoma;text-align:center;padding:60px"><h1>📡</h1><p>{$offlineText}</p></body></html>',
            { status: 503, headers: { 'Content-Type': 'text/html; charset=utf-8' } }
          );
        })
    );
  }
});
JS;

        return Response::text($js, 200)
            ->withHeader('Content-Type', 'application/javascript; charset=utf-8')
            ->withHeader('Cache-Control', 'no-cache');
    }

    private function icon(string $file): Response
    {
        $path = $this->context()->plugin->path . '/assets/' . basename($file);
        if (!is_file($path)) {
            return Response::text('Icon missing', 404);
        }

        return new Response((string) file_get_contents($path), 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=604800',
        ]);
    }

    private function adminPage(Request $request): string
    {
        $sdk = IRJalali::for('ir-pwa');
        if ($request->isMethod('POST')) {
            $sdk->saveSetting('theme_color', mb_substr(trim($request->str('theme_color')), 0, 20));
            $sdk->saveSetting('offline_text', mb_substr(trim($request->str('offline_text')), 0, 200));
        }
        $csrf = Application::get()->make(\IRJalali\Core\Security\Csrf::class)->field();

        ob_start(); ?>
        <h2 style="margin-top:0">📱 وب‌اپ پیش‌رونده (PWA)</h2>
        <p class="muted">مانیفست: <span dir="ltr"><a href="/manifest.webmanifest" target="_blank">/manifest.webmanifest</a></span> — سرویس‌ورکر: <span dir="ltr"><a href="/sw.js" target="_blank">/sw.js</a></span></p>
        <form method="post" action="/admin/plugin/ir-pwa--pwa" class="panel" style="border:1px solid var(--border)">
          <?= $csrf ?>
          <div class="grid2">
            <div>
              <label>رنگ تم</label>
              <input type="text" name="theme_color" dir="ltr" value="<?= e($sdk->setting('theme_color', '#0d9488')) ?>">
            </div>
            <div>
              <label>متن صفحه آفلاین</label>
              <input type="text" name="offline_text" value="<?= e($sdk->setting('offline_text', '')) ?>">
            </div>
          </div>
          <button class="btn small" type="submit">ذخیره</button>
        </form>
        <?php return (string) ob_get_clean();
    }

    private function siteOption(string $key, string $default): string
    {
        try {
            return (string) Application::get()->make(OptionRepository::class)->get($key, $default);
        } catch (\Throwable) {
            return $default;
        }
    }

    private function safeSetting(string $key, string $default): string
    {
        try {
            $v = IRJalali::for('ir-pwa')->setting($key, $default);

            return $v === '' ? $default : (string) $v;
        } catch (\Throwable) {
            return $default;
        }
    }
}
