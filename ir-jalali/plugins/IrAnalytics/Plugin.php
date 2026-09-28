<?php

declare(strict_types=1);

namespace IRJalali\Plugins\IrAnalytics;

use IRJalali\Core\Database\Database;
use IRJalali\Core\Http\Request;
use IRJalali\Core\Http\Response;
use IRJalali\Core\Kernel\Application;
use IRJalali\Core\Plugins\PluginContext;
use IRJalali\Core\Plugins\PluginServiceProvider;
use IRJalali\Core\Sdk\IRJalali;
use IRJalali\Core\Security\Csrf;

/**
 * IR-Analytics (Part 3 §14): lightweight, self-hosted visitor analytics.
 * Records front-page hits on the `http.response` extension point (no core
 * edits), aggregates them per day, and renders a dashboard in the admin.
 */
final class Plugin extends PluginServiceProvider
{
    /** Paths never counted (admin/API/assets/auth). */
    private const EXCLUDE_PREFIXES = [
        '/admin', '/api/', '/assets/', '/install', '/setup',
        '/login', '/logout', '/theme-assets', '/__builder',
    ];

    public function boot(PluginContext $context): void
    {
        $sdk = IRJalali::for('ir-analytics');

        $sdk->registerSettings([
            ['key' => 'track_bots', 'type' => 'checkbox', 'label' => 'ثبت بازدید ربات‌ها', 'default' => '0'],
            ['key' => 'retention_days', 'type' => 'number', 'label' => 'نگهداری لاگ خام (روز)', 'default' => '90'],
        ]);

        // Record every successful front-end response.
        $sdk->filter('http.response', function ($response, Request $request) {
            if ($response instanceof Response) {
                $this->track($request, $response);
            }

            return $response;
        }, 100);

        $sdk->registerAdminPage('analytics', 'آمار بازدید', fn (Request $request): string => $this->adminPage($request), '📊');

        // Nightly rollup + pruning of the raw table.
        $sdk->cron('rollup', 'جمع‌بندی آمار بازدید', 'daily', function (): void {
            $this->prune();
        });
    }

    private function track(Request $request, Response $response): void
    {
        try {
            if (!$request->isMethod('GET') || $response->getStatus() !== 200) {
                return;
            }
            $path = '/' . ltrim($request->path(), '/');
            foreach (self::EXCLUDE_PREFIXES as $prefix) {
                if (str_starts_with($path, $prefix)) {
                    return;
                }
            }
            if ($path === '/' && $request->path() === '') {
                $path = '/';
            }

            $sdk = IRJalali::for('ir-analytics');
            $ua = $request->userAgent();
            $isBot = $this->looksLikeBot($ua);
            if ($isBot && $sdk->setting('track_bots', '0') !== '1') {
                return;
            }

            $db = Application::get()->make(Database::class);
            $now = date('Y-m-d H:i:s');
            $ipHash = hash('sha256', $request->ip() . '|' . date('Y-m-d'));
            $referrer = $this->normalizeReferrer($request->header('referer'));

            $db->insert('ir_analytics_views', [
                'path' => mb_substr($path, 0, 191),
                'referrer' => $referrer !== '' ? mb_substr($referrer, 0, 255) : null,
                'ip_hash' => $ipHash,
                'user_agent' => mb_substr($ua, 0, 255) ?: null,
                'is_bot' => $isBot ? 1 : 0,
                'viewed_at' => $now,
            ]);

            // Upsert the daily aggregate.
            $day = date('Y-m-d');
            $existing = $db->first('SELECT views, uniq FROM ir_analytics_daily WHERE day = ? AND path = ?', [$day, $path]);
            $isNewVisitor = $db->first(
                'SELECT id FROM ir_analytics_views WHERE path = ? AND ip_hash = ? AND viewed_at >= ? AND viewed_at < ? LIMIT 1',
                [$path, $ipHash, $day . ' 00:00:00', $now]
            ) === null;
            if ($existing === null) {
                $db->insert('ir_analytics_daily', [
                    'day' => $day, 'path' => mb_substr($path, 0, 191),
                    'views' => 1, 'uniq' => 1,
                ]);
            } else {
                $db->table('ir_analytics_daily')->where('day', $day)->where('path', $path)->update([
                    'views' => (int) $existing['views'] + 1,
                    'uniq' => (int) $existing['uniq'] + ($isNewVisitor ? 1 : 0),
                ]);
            }
        } catch (\Throwable) {
            // Analytics must never break the response.
        }
    }

    private function looksLikeBot(string $ua): bool
    {
        if ($ua === '') {
            return true;
        }
        $ua = strtolower($ua);
        foreach (['bot', 'crawler', 'spider', 'slurp', 'curl', 'wget', 'python-requests', 'headless', 'lighthouse', 'pingdom'] as $needle) {
            if (str_contains($ua, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function normalizeReferrer(string $referrer): string
    {
        $referrer = trim($referrer);
        if ($referrer === '') {
            return '';
        }
        $parts = parse_url($referrer);
        if (!is_array($parts) || !isset($parts['host'])) {
            return '';
        }
        $host = strtolower((string) $parts['host']);
        // Strip own host → treat as internal/direct.
        $own = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        if ($own !== '' && $host === $own) {
            return '';
        }

        return $host . (($parts['path'] ?? '') !== '/' ? ($parts['path'] ?? '') : '');
    }

    /** Remove raw rows older than the retention window. */
    private function prune(): void
    {
        try {
            $sdk = IRJalali::for('ir-analytics');
            $days = max(7, (int) $sdk->setting('retention_days', '90'));
            $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));
            Application::get()->make(Database::class)->delete('ir_analytics_views', 'viewed_at < ?', [$cutoff]);
        } catch (\Throwable) {
        }
    }

    private function adminPage(Request $request): string
    {
        $db = Application::get()->make(Database::class);
        $csrf = Application::get()->make(Csrf::class)->field();
        $sdk = IRJalali::for('ir-analytics');

        if ($request->isMethod('POST') && $request->str('action') === 'save') {
            $sdk->saveSetting('track_bots', $request->str('track_bots') === '1' ? '1' : '0');
            $sdk->saveSetting('retention_days', (string) max(7, (int) $request->str('retention_days', '90')));
        }

        $days = 14;
        $range = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $range[] = date('Y-m-d', strtotime("-{$i} days"));
        }
        $placeholders = implode(',', array_fill(0, count($range), '?'));
        $rows = $db->select(
            "SELECT day, SUM(views) views, SUM(uniq) uniq FROM ir_analytics_daily WHERE day IN ({$placeholders}) GROUP BY day",
            $range
        );
        $byDay = array_fill_keys($range, ['views' => 0, 'uniq' => 0]);
        foreach ($rows as $r) {
            $byDay[$r['day']] = ['views' => (int) $r['views'], 'uniq' => (int) $r['uniq']];
        }
        $max = max(1, ...array_values(array_map(fn ($d) => $d['views'], $byDay)));

        $topPages = $db->select(
            'SELECT path, SUM(views) views FROM ir_analytics_daily WHERE day >= ? GROUP BY path ORDER BY views DESC LIMIT 10',
            [date('Y-m-d', strtotime('-30 days'))]
        );
        $referrers = $db->select(
            "SELECT referrer, COUNT(*) hits FROM ir_analytics_views WHERE referrer IS NOT NULL AND viewed_at >= ? GROUP BY referrer ORDER BY hits DESC LIMIT 10",
            [date('Y-m-d H:i:s', strtotime('-30 days'))]
        );

        $today = $byDay[date('Y-m-d')]['views'] ?? 0;
        $week = array_sum(array_map(fn ($d) => $d['views'], array_slice($byDay, -7)));

        ob_start(); ?>
        <h2 style="margin-top:0">📊 آمار بازدید</h2>
        <div class="cards" style="display:flex;gap:16px;margin-bottom:16px">
          <div class="panel" style="flex:1;border:1px solid var(--border);padding:16px;text-align:center">
            <div style="font-size:28px;font-weight:700"><?= (int) $today ?></div><div class="muted">بازدید امروز</div>
          </div>
          <div class="panel" style="flex:1;border:1px solid var(--border);padding:16px;text-align:center">
            <div style="font-size:28px;font-weight:700"><?= (int) $week ?></div><div class="muted">بازدید ۷ روز اخیر</div>
          </div>
          <div class="panel" style="flex:1;border:1px solid var(--border);padding:16px;text-align:center">
            <div style="font-size:28px;font-weight:700"><?= count($topPages) ?></div><div class="muted">صفحه فعال</div>
          </div>
        </div>

        <div class="panel" style="border:1px solid var(--border);padding:16px">
          <h3>روند ۱۴ روز اخیر</h3>
          <div style="display:flex;align-items:flex-end;gap:4px;height:120px" dir="ltr">
            <?php foreach ($byDay as $day => $d): ?>
              <?php $h = (int) round(($d['views'] / $max) * 100); ?>
              <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:2px">
                <div title="<?= e($day) ?>: <?= (int) $d['views'] ?> بازدید، <?= (int) $d['uniq'] ?> یکتا"
                     style="width:100%;background:var(--brand,#0d9488);height:<?= max(2, $h) ?>%;border-radius:2px 2px 0 0;opacity:<?= $d['views'] ? '1' : '.25' ?>"></div>
                <span style="font-size:9px;color:var(--muted,#888)"><?= e(substr($day, 5)) ?></span>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="grid2">
          <div class="panel" style="border:1px solid var(--border)">
            <h3>صفحات پربازدید (۳۰ روز)</h3>
            <?php if ($topPages === []): ?><p class="muted">داده‌ای نیست.</p><?php else: ?>
              <table class="tbl">
                <tr><th>مسیر</th><th>بازدید</th></tr>
                <?php foreach ($topPages as $p): ?>
                  <tr><td dir="ltr"><?= e($p['path']) ?></td><td><?= (int) $p['views'] ?></td></tr>
                <?php endforeach; ?>
              </table>
            <?php endif; ?>
          </div>
          <div class="panel" style="border:1px solid var(--border)">
            <h3>منابع ورود (۳۰ روز)</h3>
            <?php if ($referrers === []): ?><p class="muted">داده‌ای نیست.</p><?php else: ?>
              <table class="tbl">
                <tr><th>منبع</th><th>ورود</th></tr>
                <?php foreach ($referrers as $r): ?>
                  <tr><td dir="ltr"><?= e($r['referrer']) ?></td><td><?= (int) $r['hits'] ?></td></tr>
                <?php endforeach; ?>
              </table>
            <?php endif; ?>
          </div>
        </div>

        <form method="post" action="/admin/plugin/ir-analytics--analytics" class="panel" style="border:1px solid var(--border)">
          <?= $csrf ?><input type="hidden" name="action" value="save">
          <div class="grid2">
            <div>
              <label><input type="checkbox" name="track_bots" value="1" <?= $sdk->setting('track_bots', '0') === '1' ? 'checked' : '' ?>> ثبت بازدید ربات‌ها</label>
            </div>
            <div>
              <label>نگهداری لاگ خام (روز)</label>
              <input type="number" name="retention_days" min="7" value="<?= e($sdk->setting('retention_days', '90')) ?>">
            </div>
          </div>
          <button class="btn small" type="submit">ذخیره</button>
        </form>
        <?php return (string) ob_get_clean();
    }
}
