<?php

declare(strict_types=1);

namespace IRJalali\Plugins\IrSecurity;

use IRJalali\Core\Database\Database;
use IRJalali\Core\Http\Request;
use IRJalali\Core\Kernel\Application;
use IRJalali\Core\Plugins\PluginContext;
use IRJalali\Core\Plugins\PluginServiceProvider;
use IRJalali\Core\Sdk\IRJalali;
use IRJalali\Core\Security\Csrf;

/**
 * IR-Security (Part 3 §16): firewall + monitoring layer. Builds on the core
 * RateLimiter/SecurityHeaders/audit trail and adds an IP firewall (wired to
 * the core `security.block_ip` extension point), request signature screening
 * and a security dashboard — no core edits.
 */
final class Plugin extends PluginServiceProvider
{
    /** Cheap request-signature screening (path traversal, obvious SQLi/XSS). */
    private const SIGNATURES = [
        '<script', '<iframe', 'javascript:', 'onerror=', 'onload=',
        "union select", "' or '1'='1", '" or "1"="1', "1=1--",
        '../..', '..\\..', '/etc/passwd', 'cmd=', 'eval(', 'base64_decode(',
    ];

    public function boot(PluginContext $context): void
    {
        $sdk = IRJalali::for('ir-security');

        $sdk->registerSettings([
            ['key' => 'enable_firewall', 'type' => 'checkbox', 'label' => 'فعال‌سازی فایروال', 'default' => '1'],
            ['key' => 'enable_signatures', 'type' => 'checkbox', 'label' => 'غربال امضای درخواست‌ها', 'default' => '1'],
            ['key' => 'signature_mode', 'type' => 'select', 'label' => 'حالت امضا (مسدود / فقط لاگ)', 'default' => 'log'],
        ]);

        // Wire the firewall into the core's block-ip extension point.
        $sdk->filter('security.block_ip', function ($blocked, string $ip, Request $request) {
            if ($blocked === true) {
                return true;
            }

            return $this->firewallCheck($ip, $request);
        }, 10);

        $sdk->registerAdminPage('security', 'امنیت', fn (Request $request): string => $this->adminPage($request), '🛡️', 'settings.manage');
    }

    private function firewallCheck(string $ip, Request $request): bool
    {
        try {
            $sdk = IRJalali::for('ir-security');
            if ($sdk->setting('enable_firewall', '1') !== '1') {
                return false;
            }

            $db = Application::get()->make(Database::class);
            $now = date('Y-m-d H:i:s');

            // Allow-list takes precedence over everything.
            $allow = $db->first(
                'SELECT id FROM ir_security_rules WHERE rule_type = ? AND ip = ? AND (expires_at IS NULL OR expires_at > ?)',
                ['allow', $ip, $now]
            );
            if ($allow !== null) {
                return false;
            }

            $block = $db->first(
                'SELECT id FROM ir_security_rules WHERE rule_type = ? AND ip = ? AND (expires_at IS NULL OR expires_at > ?)',
                ['block', $ip, $now]
            );
            if ($block !== null) {
                return true;
            }

            if ($sdk->setting('enable_signatures', '1') === '1' && $this->matchesSignature($request)) {
                $mode = (string) $sdk->setting('signature_mode', 'log');
                $this->logEvent('signature.match', $ip, $request);
                if ($mode === 'block') {
                    $db->insert('ir_security_rules', [
                        'rule_type' => 'block',
                        'ip' => $ip,
                        'reason' => 'auto: signature',
                        'expires_at' => date('Y-m-d H:i:s', strtotime('+1 hour')),
                        'created_at' => $now,
                    ]);

                    return true;
                }
            }

            return false;
        } catch (\Throwable) {
            // Security layer must never take the site down; fail open on error.
            return false;
        }
    }

    private function matchesSignature(Request $request): bool
    {
        $hay = strtolower($request->path() . ' ' . http_build_query(is_array($_GET ?? null) ? $_GET : []));
        foreach (self::SIGNATURES as $sig) {
            if (str_contains($hay, $sig)) {
                return true;
            }
        }

        return false;
    }

    private function logEvent(string $event, string $ip, Request $request): void
    {
        try {
            Application::get()->make(Database::class)->insert('security_logs', [
                'event' => $event,
                'user_id' => null,
                'ip' => mb_substr($ip, 0, 45),
                'user_agent' => mb_substr($request->userAgent(), 0, 255),
                'details_json' => json_encode(['path' => $request->path()], JSON_UNESCAPED_UNICODE),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable) {
        }
    }

    private function adminPage(Request $request): string
    {
        $db = Application::get()->make(Database::class);
        $sdk = IRJalali::for('ir-security');
        $csrf = Application::get()->make(Csrf::class)->field();
        $message = null;

        if ($request->isMethod('POST')) {
            $action = $request->str('action');
            if ($action === 'add_rule') {
                $type = $request->str('rule_type') === 'allow' ? 'allow' : 'block';
                $ip = trim($request->str('ip'));
                if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP) !== false) {
                    $expires = $request->str('ttl') !== '' ? date('Y-m-d H:i:s', time() + (int) $request->str('ttl') * 3600) : null;
                    $db->delete('ir_security_rules', 'rule_type = ? AND ip = ?', [$type, $ip]);
                    $db->insert('ir_security_rules', [
                        'rule_type' => $type,
                        'ip' => $ip,
                        'reason' => mb_substr($request->str('reason'), 0, 255),
                        'expires_at' => $expires,
                        'created_at' => date('Y-m-d H:i:s'),
                    ]);
                    $message = 'قانون ثبت شد.';
                } else {
                    $message = 'آدرس IP نامعتبر است.';
                }
            } elseif ($action === 'delete_rule') {
                $db->delete('ir_security_rules', 'id = ?', [(int) $request->str('id')]);
                $message = 'قانون حذف شد.';
            } elseif ($action === 'save') {
                $sdk->saveSetting('enable_firewall', $request->str('enable_firewall') === '1' ? '1' : '0');
                $sdk->saveSetting('enable_signatures', $request->str('enable_signatures') === '1' ? '1' : '0');
                $mode = $request->str('signature_mode') === 'block' ? 'block' : 'log';
                $sdk->saveSetting('signature_mode', $mode);
                $message = 'تنظیمات ذخیره شد.';
            }
        }

        $rules = $db->select('SELECT * FROM ir_security_rules ORDER BY id DESC LIMIT 100');
        $recent = [];
        try {
            $recent = $db->select('SELECT event, ip, user_agent, created_at FROM security_logs ORDER BY id DESC LIMIT 30');
        } catch (\Throwable) {
        }

        ob_start(); ?>
        <h2 style="margin-top:0">🛡️ امنیت</h2>
        <?php if ($message !== null): ?><p class="flash-ok"><?= e($message) ?></p><?php endif; ?>

        <form method="post" action="/admin/plugin/ir-security--security" class="panel" style="border:1px solid var(--border)">
          <?= $csrf ?><input type="hidden" name="action" value="save">
          <div class="grid2">
            <div>
              <label><input type="checkbox" name="enable_firewall" value="1" <?= $sdk->setting('enable_firewall', '1') === '1' ? 'checked' : '' ?>> فعال‌سازی فایروال</label>
              <label><input type="checkbox" name="enable_signatures" value="1" <?= $sdk->setting('enable_signatures', '1') === '1' ? 'checked' : '' ?>> غربال امضای درخواست‌ها</label>
            </div>
            <div>
              <label>حالت امضا</label>
              <select name="signature_mode">
                <option value="log" <?= $sdk->setting('signature_mode', 'log') === 'log' ? 'selected' : '' ?>>فقط لاگ</option>
                <option value="block" <?= $sdk->setting('signature_mode', 'log') === 'block' ? 'selected' : '' ?>>مسدودسازی موقت</option>
              </select>
            </div>
          </div>
          <button class="btn small" type="submit">ذخیره</button>
        </form>

        <form method="post" action="/admin/plugin/ir-security--security" class="panel" style="border:1px solid var(--border)">
          <?= $csrf ?><input type="hidden" name="action" value="add_rule">
          <h3>افزودن قانون فایروال</h3>
          <div class="toolbar">
            <select name="rule_type"><option value="block">مسدود</option><option value="allow">مجاز</option></select>
            <input type="text" name="ip" placeholder="1.2.3.4" dir="ltr" required>
            <input type="text" name="reason" placeholder="دلیل">
            <select name="ttl"><option value="">دائمی</option><option value="1">۱ ساعت</option><option value="24">۲۴ ساعت</option><option value="168">۷ روز</option></select>
            <button class="btn small" type="submit">افزودن</button>
          </div>
        </form>

        <div class="panel" style="border:1px solid var(--border)">
          <h3>قوانین فایروال</h3>
          <?php if ($rules === []): ?><p class="muted">قانونی ثبت نشده است.</p><?php else: ?>
            <table class="tbl">
              <tr><th>نوع</th><th>IP</th><th>دلیل</th><th>انقضا</th><th></th></tr>
              <?php foreach ($rules as $r): ?>
                <tr>
                  <td><span class="badge <?= $r['rule_type'] === 'block' ? 'red' : 'green' ?>"><?= e($r['rule_type']) ?></span></td>
                  <td dir="ltr"><?= e($r['ip']) ?></td>
                  <td><?= e((string) ($r['reason'] ?? '')) ?></td>
                  <td class="muted"><?= e((string) ($r['expires_at'] ?? 'دائمی')) ?></td>
                  <td>
                    <form method="post" action="/admin/plugin/ir-security--security" style="display:inline">
                      <?= $csrf ?><input type="hidden" name="action" value="delete_rule"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                      <button class="btn small" type="submit" data-confirm="حذف شود؟">حذف</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </table>
          <?php endif; ?>
        </div>

        <div class="panel" style="border:1px solid var(--border)">
          <h3>رویدادهای امنیتی اخیر</h3>
          <?php if ($recent === []): ?><p class="muted">رویدادی ثبت نشده است.</p><?php else: ?>
            <table class="tbl">
              <tr><th>رویداد</th><th>IP</th><th>عامل کاربر</th><th>زمان</th></tr>
              <?php foreach ($recent as $l): ?>
                <tr>
                  <td><?= e($l['event']) ?></td>
                  <td dir="ltr"><?= e((string) ($l['ip'] ?? '')) ?></td>
                  <td class="muted" style="font-size:11px;max-width:260px;overflow:hidden;text-overflow:ellipsis"><?= e((string) ($l['user_agent'] ?? '')) ?></td>
                  <td class="muted" style="font-size:11px"><?= e($l['created_at']) ?></td>
                </tr>
              <?php endforeach; ?>
            </table>
          <?php endif; ?>
        </div>
        <?php return (string) ob_get_clean();
    }
}
