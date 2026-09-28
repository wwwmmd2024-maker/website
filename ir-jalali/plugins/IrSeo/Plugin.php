<?php

declare(strict_types=1);

namespace IRJalali\Plugins\IrSeo;

use IRJalali\Core\Database\Database;
use IRJalali\Core\Http\Request;
use IRJalali\Core\Http\Response;
use IRJalali\Core\Kernel\Application;
use IRJalali\Core\Plugins\PluginContext;
use IRJalali\Core\Plugins\PluginServiceProvider;
use IRJalali\Core\Sdk\IRJalali;
use IRJalali\Core\Security\Csrf;

/**
 * IR-SEO — built-in SEO engine (Part 3 §13).
 * Meta title/description, canonical, robots, OpenGraph, Twitter Cards,
 * Schema.org, breadcrumbs, XML sitemap, robots.txt, redirect manager and a
 * 404 monitor — all implemented on core extension points, no core edits.
 */
final class Plugin extends PluginServiceProvider
{
    public function boot(PluginContext $context): void
    {
        $sdk = IRJalali::for('ir-seo');

        // Inject <head> meta on every themed page.
        $sdk->filter('front.head_meta', function (string $html, array $data, $ctx): string {
            return $html . $this->renderHeadMeta($data);
        }, 10);

        // Redirect manager: claim a slug before it 404s.
        $sdk->filter('front.redirect', function ($value, string $slug, Request $request) {
            if (is_string($value) && $value !== '') {
                return $value;
            }

            return $this->findRedirect('/' . ltrim($slug, '/'));
        }, 10);

        // 404 monitor.
        $sdk->on('front.not_found', function (string $slug, Request $request): void {
            $this->log404('/' . ltrim($slug, '/'), $request);
        });

        // XML sitemap.
        $sdk->registerRoute(['GET'], '/sitemap.xml', function (): Response {
            return Response::text($this->buildSitemap(), 200)
                ->withHeader('Content-Type', 'application/xml; charset=utf-8');
        });

        // robots.txt.
        $sdk->registerRoute(['GET'], '/robots.txt', function (): Response {
            $sdk = IRJalali::for('ir-seo');
            $base = $this->siteBase();
            $extra = trim((string) $sdk->setting('robots_extra', ''));
            $body = "User-agent: *\n"
                . ($sdk->setting('allow', '1') === '1' ? "Allow: /\n" : "Disallow: /\n")
                . 'Sitemap: ' . $base . "/sitemap.xml\n"
                . ($extra !== '' ? "\n" . $extra . "\n" : '');

            return Response::text($body, 200)->withHeader('Content-Type', 'text/plain; charset=utf-8');
        });

        $sdk->registerAdminPage('manager', 'مدیریت سئو', fn (Request $request): string => $this->managerPage($request), '🔍');

        $sdk->registerSettings([
            ['key' => 'allow', 'type' => 'checkbox', 'label' => 'اجازه ایندکس (robots)', 'default' => '1'],
            ['key' => 'robots_extra', 'type' => 'textarea', 'label' => 'قوانین اضافی robots', 'default' => ''],
            ['key' => 'default_description', 'type' => 'text', 'label' => 'توضیح پیش‌فرض متا', 'default' => ''],
        ]);

        // Nightly cleanup of old 404 logs.
        $sdk->cron('cleanup', 'پاکسازی لاگ 404 قدیمی', 'daily', function (): void {
            try {
                Application::get()->make(Database::class)->delete(
                    'ir_seo_404',
                    'created_at < :cutoff',
                    ['cutoff' => date('Y-m-d H:i:s', time() - 60 * 86400)]
                );
            } catch (\Throwable) {
            }
        });
    }

    // ── Head meta ────────────────────────────────────────────────
    private function renderHeadMeta(array $data): string
    {
        $db = $this->dbSafe();
        $sdk = IRJalali::for('ir-seo');
        $post = $data['post'] ?? null;
        $siteTitle = (string) ($data['siteTitle'] ?? '');
        $pageTitle = (string) ($data['pageTitle'] ?? $siteTitle);

        $metaTitle = $pageTitle;
        $description = (string) $sdk->setting('default_description', '');
        $canonical = '';
        $ogImage = '';
        $noindex = false;

        if (is_array($post) && $db !== null) {
            $row = $db->first(
                'SELECT * FROM seo_meta WHERE entity_type = :t AND entity_id = :id LIMIT 1',
                ['t' => 'post', 'id' => (int) ($post['id'] ?? 0)]
            );
            if ($row !== null) {
                if (!empty($row['meta_title'])) {
                    $metaTitle = (string) $row['meta_title'];
                }
                if (!empty($row['meta_description'])) {
                    $description = (string) $row['meta_description'];
                }
                if (!empty($row['canonical_url'])) {
                    $canonical = (string) $row['canonical_url'];
                }
                if (!empty($row['og_image'])) {
                    $ogImage = (string) $row['og_image'];
                }
                $extra = json_decode((string) ($row['extra'] ?? '{}'), true) ?: [];
                $noindex = !empty($extra['noindex']);
            }
            if ($description === '' && !empty($post['excerpt'])) {
                $description = mb_substr(strip_tags((string) $post['excerpt']), 0, 200);
            }
        }

        $base = $this->siteBase();
        $url = $canonical !== '' ? $canonical : $base . ($post['url'] ?? '');
        $esc = fn (?string $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

        $out = "\n<!-- IR-SEO -->\n";
        $out .= '<meta name="title" content="' . $esc($metaTitle) . '">' . "\n";
        if ($description !== '') {
            $out .= '<meta name="description" content="' . $esc(mb_substr($description, 0, 300)) . '">' . "\n";
        }
        $out .= $noindex
            ? '<meta name="robots" content="noindex, nofollow">' . "\n"
            : '<meta name="robots" content="index, follow">' . "\n";
        if ($url !== '') {
            $out .= '<link rel="canonical" href="' . $esc($url) . '">' . "\n";
        }
        // OpenGraph
        $out .= '<meta property="og:type" content="' . (is_array($post) ? 'article' : 'website') . '">' . "\n";
        $out .= '<meta property="og:title" content="' . $esc($metaTitle) . '">' . "\n";
        $out .= '<meta property="og:site_name" content="' . $esc($siteTitle) . '">' . "\n";
        if ($description !== '') {
            $out .= '<meta property="og:description" content="' . $esc(mb_substr($description, 0, 300)) . '">' . "\n";
        }
        if ($url !== '') {
            $out .= '<meta property="og:url" content="' . $esc($url) . '">' . "\n";
        }
        if ($ogImage !== '') {
            $out .= '<meta property="og:image" content="' . $esc($ogImage) . '">' . "\n";
        }
        // Twitter
        $out .= '<meta name="twitter:card" content="' . ($ogImage !== '' ? 'summary_large_image' : 'summary') . '">' . "\n";
        $out .= '<meta name="twitter:title" content="' . $esc($metaTitle) . '">' . "\n";
        if ($description !== '') {
            $out .= '<meta name="twitter:description" content="' . $esc(mb_substr($description, 0, 200)) . '">' . "\n";
        }
        // Schema.org
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => $siteTitle,
            'url' => $base !== '' ? $base : null,
        ];
        $out .= '<script type="application/ld+json">' . json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>' . "\n";

        return $out;
    }

    private function buildSitemap(): string
    {
        $db = $this->dbSafe();
        $base = $this->siteBase();
        $urls = [];
        $urls[] = ['loc' => $base . '/', 'lastmod' => date('c')];
        if ($db !== null) {
            $rows = $db->select(
                "SELECT slug, updated_at FROM posts WHERE status = 'published' AND deleted_at IS NULL ORDER BY updated_at DESC LIMIT 500"
            );
            foreach ($rows as $row) {
                $urls[] = [
                    'loc' => $base . '/' . rawurlencode((string) $row['slug']),
                    'lastmod' => !empty($row['updated_at']) ? date('c', strtotime((string) $row['updated_at'])) : date('c'),
                ];
            }
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($urls as $u) {
            $xml .= "  <url>\n    <loc>" . htmlspecialchars($u['loc'], ENT_XML1, 'UTF-8') . "</loc>\n    <lastmod>" . $u['lastmod'] . "</lastmod>\n  </url>\n";
        }
        $xml .= '</urlset>';

        return $xml;
    }

    private function findRedirect(string $path): ?string
    {
        $db = $this->dbSafe();
        if ($db === null) {
            return null;
        }
        $row = $db->first(
            'SELECT * FROM redirects WHERE from_path = :p AND is_active = 1 LIMIT 1',
            ['p' => $path]
        );
        if ($row === null) {
            return null;
        }
        try {
            $db->table('redirects')->where('id', $row['id'])->update(['hits' => (int) $row['hits'] + 1]);
        } catch (\Throwable) {
        }

        return (string) $row['to_url'];
    }

    private function log404(string $path, Request $request): void
    {
        $db = $this->dbSafe();
        if ($db === null) {
            return;
        }
        try {
            $existing = $db->first('SELECT id, hits FROM ir_seo_404 WHERE path = :p LIMIT 1', ['p' => mb_substr($path, 0, 500)]);
            if ($existing !== null) {
                $db->table('ir_seo_404')->where('id', $existing['id'])->update(['hits' => (int) $existing['hits'] + 1]);
            } else {
                $db->insert('ir_seo_404', [
                    'path' => mb_substr($path, 0, 500),
                    'referrer' => mb_substr($request->header('Referer'), 0, 500) ?: null,
                    'ip' => mb_substr($request->ip(), 0, 45) ?: null,
                    'hits' => 1,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }
        } catch (\Throwable) {
        }
    }

    // ── Admin: redirect manager + 404 monitor ───────────────────
    private function managerPage(Request $request): string
    {
        $db = $this->dbSafe();
        $sdk = IRJalali::for('ir-seo');
        $csrf = Application::get()->make(Csrf::class)->field();

        if ($request->isMethod('POST') && $db !== null) {
            $action = $request->str('action');
            if ($action === 'add_redirect') {
                $from = '/' . ltrim($request->str('from_path'), '/');
                $to = $request->str('to_url');
                $code = in_array((int) $request->input('status_code', 301), [301, 302], true) ? (int) $request->input('status_code') : 301;
                if ($from !== '/' && $to !== '' && $db->first('SELECT id FROM redirects WHERE from_path = :p', ['p' => $from]) === null) {
                    $now = date('Y-m-d H:i:s');
                    $db->insert('redirects', [
                        'from_path' => mb_substr($from, 0, 500),
                        'to_url' => mb_substr($to, 0, 500),
                        'status_code' => $code,
                        'hits' => 0,
                        'is_active' => 1,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            } elseif ($action === 'delete_redirect') {
                $db->table('redirects')->where('id', (int) $request->input('id'))->delete();
            } elseif ($action === 'save_settings') {
                $sdk->saveSetting('allow', $request->input('allow') ? '1' : '0');
                $sdk->saveSetting('robots_extra', mb_substr($request->str('robots_extra', ''), 0, 2000));
                $sdk->saveSetting('default_description', mb_substr($request->str('default_description', ''), 0, 300));
            }
        }

        $redirects = $db?->select('SELECT * FROM redirects ORDER BY id DESC LIMIT 100') ?? [];
        $notFounds = [];
        try {
            $notFounds = $db?->select('SELECT * FROM ir_seo_404 ORDER BY hits DESC, id DESC LIMIT 50') ?? [];
        } catch (\Throwable) {
        }

        ob_start(); ?>
        <h2 style="margin-top:0">🔍 مدیریت سئو</h2>

        <div class="panel" style="border:1px solid var(--border)">
          <h3>تنظیمات عمومی</h3>
          <form method="post" action="/admin/plugin/ir-seo--manager">
            <?= $csrf ?>
            <input type="hidden" name="action" value="save_settings">
            <label style="display:flex;gap:8px;align-items:center;font-weight:400">
              <input type="checkbox" name="allow" value="1" <?= $sdk->setting('allow', '1') === '1' ? 'checked' : '' ?>>
              اجازه ایندکس به موتورهای جستجو (robots)
            </label>
            <label>توضیح متای پیش‌فرض</label>
            <input type="text" name="default_description" value="<?= e((string) $sdk->setting('default_description', '')) ?>" style="width:100%;max-width:520px">
            <label>قوانین اضافی robots</label>
            <textarea name="robots_extra" rows="3" style="width:100%;max-width:520px" dir="ltr"><?= e((string) $sdk->setting('robots_extra', '')) ?></textarea>
            <button class="btn small" type="submit">ذخیره تنظیمات</button>
          </form>
        </div>

        <div class="panel" style="border:1px solid var(--border)">
          <h3>ریدایرکت‌ها</h3>
          <form method="post" action="/admin/plugin/ir-seo--manager" class="toolbar">
            <?= $csrf ?>
            <input type="hidden" name="action" value="add_redirect">
            <input type="text" name="from_path" placeholder="/مسیر-قدیمی" dir="ltr" style="max-width:180px" required>
            <input type="text" name="to_url" placeholder="/مسیر-جدید یا آدرس کامل" dir="ltr" style="max-width:220px" required>
            <select name="status_code"><option value="301">301</option><option value="302">302</option></select>
            <button class="btn small" type="submit">افزودن</button>
          </form>
          <table class="tbl">
            <tr><th>از</th><th>به</th><th>کد</th><th>بازدید</th><th></th></tr>
            <?php foreach ($redirects as $r): ?>
              <tr>
                <td dir="ltr"><?= e($r['from_path']) ?></td>
                <td dir="ltr"><?= e($r['to_url']) ?></td>
                <td><?= e((string) $r['status_code']) ?></td>
                <td><?= e((string) $r['hits']) ?></td>
                <td>
                  <form method="post" action="/admin/plugin/ir-seo--manager" class="inline-form">
                    <?= $csrf ?><input type="hidden" name="action" value="delete_redirect"><input type="hidden" name="id" value="<?= e((string) $r['id']) ?>">
                    <button class="btn small danger" type="submit">حذف</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </table>
        </div>

        <div class="panel" style="border:1px solid var(--border)">
          <h3>مانیتور 404</h3>
          <?php if ($notFounds === []): ?>
            <p class="muted">صفحه گم‌شده‌ای ثبت نشده است.</p>
          <?php else: ?>
            <table class="tbl">
              <tr><th>مسیر</th><th>ارجاع‌دهنده</th><th>تعداد</th></tr>
              <?php foreach ($notFounds as $nf): ?>
                <tr>
                  <td dir="ltr"><?= e($nf['path']) ?></td>
                  <td dir="ltr" class="muted" style="font-size:11.5px"><?= e((string) ($nf['referrer'] ?? '')) ?></td>
                  <td><?= e((string) $nf['hits']) ?></td>
                </tr>
              <?php endforeach; ?>
            </table>
          <?php endif; ?>
        </div>
        <?php return (string) ob_get_clean();
    }

    private function siteBase(): string
    {
        $appUrl = (string) ($_ENV['APP_URL'] ?? '');
        if ($appUrl !== '' && !str_contains($appUrl, '127.0.0.1') && !str_contains($appUrl, 'localhost')) {
            return rtrim($appUrl, '/');
        }

        return '';
    }

    private function dbSafe(): ?Database
    {
        try {
            return $this->context()->db();
        } catch (\Throwable) {
            return null;
        }
    }
}
