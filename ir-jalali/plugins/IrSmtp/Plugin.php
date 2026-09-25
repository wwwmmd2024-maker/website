<?php

declare(strict_types=1);

namespace IRJalali\Plugins\IrSmtp;

use IRJalali\Core\Database\Database;
use IRJalali\Core\Http\Request;
use IRJalali\Core\Kernel\Application;
use IRJalali\Core\Plugins\PluginContext;
use IRJalali\Core\Plugins\PluginServiceProvider;
use IRJalali\Core\Sdk\IRJalali;
use IRJalali\Core\Security\Csrf;

/**
 * IR-SMTP (Part 3 §15): SMTP settings, email queue, templates and logs.
 * Other plugins obtain a sender through Plugin::mailer().
 */
final class Plugin extends PluginServiceProvider
{
    public function boot(PluginContext $context): void
    {
        $sdk = IRJalali::for('ir-smtp');

        $sdk->registerSettings([
            ['key' => 'driver', 'type' => 'select', 'label' => 'درایور', 'default' => 'smtp'],
            ['key' => 'host', 'type' => 'text', 'label' => 'هاست SMTP', 'default' => ''],
            ['key' => 'port', 'type' => 'number', 'label' => 'پورت', 'default' => '587'],
            ['key' => 'encryption', 'type' => 'select', 'label' => 'رمزنگاری', 'default' => 'tls'],
            ['key' => 'username', 'type' => 'text', 'label' => 'نام کاربری', 'default' => ''],
            ['key' => 'password', 'type' => 'password', 'label' => 'گذرواژه', 'default' => ''],
            ['key' => 'from_name', 'type' => 'text', 'label' => 'نام فرستنده', 'default' => 'IR-Jalali'],
            ['key' => 'from_email', 'type' => 'email', 'label' => 'ایمیل فرستنده', 'default' => ''],
        ]);

        $sdk->registerAdminPage('mail', 'پست الکترونیک', fn (Request $request): string => $this->adminPage($request), '✉️');

        // Flush the email queue every minute via the core scheduler.
        $sdk->cron('send_queue', 'ارسال ایمیل‌های در صف', 'minutely', function (): void {
            try {
                self::mailer()->flush(20);
            } catch (\Throwable) {
                // Settings may be incomplete; keep cron alive.
            }
        });
    }

    /** Public access point for other plugins/modules. */
    public static function mailer(): Mailer
    {
        $sdk = IRJalali::for('ir-smtp');

        return new Mailer(
            Application::get()->make(Database::class),
            fn (string $key, mixed $default): mixed => $sdk->setting($key, $default),
        );
    }

    private function adminPage(Request $request): string
    {
        $sdk = IRJalali::for('ir-smtp');
        $message = null;
        $db = Application::get()->make(Database::class);

        if ($request->isMethod('POST')) {
            $action = $request->str('action');
            if ($action === 'save') {
                foreach (['driver', 'host', 'port', 'encryption', 'username', 'password', 'from_name', 'from_email'] as $key) {
                    $value = (string) $request->input($key, '');
                    // Never blank an existing password when the field is left empty.
                    if ($key === 'password' && $value === '') {
                        continue;
                    }
                    $sdk->saveSetting($key, mb_substr($value, 0, 255));
                }
                $message = 'تنظیمات ذخیره شد.';
            } elseif ($action === 'test') {
                try {
                    self::mailer()->sendNow(
                        $request->str('test_to'),
                        'تست ایمیل — IR-Jalali',
                        '<p>این یک ایمیل آزمایشی از آیری‌جلالی است. اگر این پیام را می‌بینید، تنظیمات SMTP درست کار می‌کند.</p>'
                    );
                    $message = 'ایمیل تست ارسال شد.';
                } catch (\Throwable $e) {
                    $message = 'خطا در ارسال تست: ' . $e->getMessage();
                }
            } elseif ($action === 'flush') {
                [$sent, $failed] = self::mailer()->flush(50);
                $message = "صف پردازش شد: {$sent} ارسال، {$failed} ناموفق.";
            }
        }

        $queue = [];
        $logs = [];
        try {
            $queue = $db->select('SELECT * FROM ir_smtp_queue ORDER BY id DESC LIMIT 30');
            $logs = $db->select('SELECT * FROM ir_smtp_log ORDER BY id DESC LIMIT 30');
        } catch (\Throwable) {
        }
        $csrf = Application::get()->make(Csrf::class)->field();

        $get = fn (string $key, string $default = ''): string => (string) $sdk->setting($key, $default);

        ob_start(); ?>
        <h2 style="margin-top:0">✉️ پست الکترونیک (SMTP)</h2>
        <?php if ($message !== null): ?><p class="flash-ok"><?= e($message) ?></p><?php endif; ?>

        <form method="post" action="/admin/plugin/ir-smtp--mail" class="panel" style="border:1px solid var(--border)">
          <?= $csrf ?><input type="hidden" name="action" value="save">
          <div class="grid2">
            <div>
              <label>درایور</label>
              <select name="driver">
                <option value="smtp" <?= $get('driver', 'smtp') === 'smtp' ? 'selected' : '' ?>>SMTP</option>
                <option value="log" <?= $get('driver') === 'log' ? 'selected' : '' ?>>فقط لاگ (توسعه)</option>
              </select>
              <label>هاست</label><input type="text" name="host" value="<?= e($get('host')) ?>" dir="ltr">
              <label>پورت</label><input type="number" name="port" value="<?= e($get('port', '587')) ?>" dir="ltr">
              <label>رمزنگاری</label>
              <select name="encryption">
                <option value="tls" <?= $get('encryption', 'tls') === 'tls' ? 'selected' : '' ?>>TLS</option>
                <option value="ssl" <?= $get('encryption') === 'ssl' ? 'selected' : '' ?>>SSL</option>
                <option value="none" <?= $get('encryption') === 'none' ? 'selected' : '' ?>>بدون رمزنگاری</option>
              </select>
            </div>
            <div>
              <label>نام کاربری</label><input type="text" name="username" value="<?= e($get('username')) ?>" dir="ltr">
              <label>گذرواژه (برای عدم تغییر، خالی بگذارید)</label><input type="password" name="password" dir="ltr" autocomplete="new-password">
              <label>نام فرستنده</label><input type="text" name="from_name" value="<?= e($get('from_name', 'IR-Jalali')) ?>">
              <label>ایمیل فرستنده</label><input type="email" name="from_email" value="<?= e($get('from_email')) ?>" dir="ltr">
            </div>
          </div>
          <button class="btn small" type="submit">ذخیره تنظیمات</button>
        </form>

        <div class="toolbar">
          <form method="post" action="/admin/plugin/ir-smtp--mail" class="toolbar" style="margin:0">
            <?= $csrf ?><input type="hidden" name="action" value="test">
            <input type="email" name="test_to" placeholder="email@example.com" dir="ltr" required style="max-width:260px">
            <button class="btn small" type="submit" style="background:#0891b2">ارسال ایمیل تست</button>
          </form>
          <form method="post" action="/admin/plugin/ir-smtp--mail" style="margin:0">
            <?= $csrf ?><input type="hidden" name="action" value="flush">
            <button class="btn small" type="submit">پردازش صف</button>
          </form>
        </div>

        <div class="panel" style="border:1px solid var(--border)">
          <h3>صف ایمیل</h3>
          <?php if ($queue === []): ?><p class="muted">صف خالی است.</p><?php else: ?>
            <table class="tbl">
              <tr><th>گیرنده</th><th>موضوع</th><th>وضعیت</th><th>تلاش</th></tr>
              <?php foreach ($queue as $q): ?>
                <tr>
                  <td dir="ltr"><?= e($q['to_email']) ?></td>
                  <td><?= e($q['subject']) ?></td>
                  <td><span class="badge <?= $q['status'] === 'sent' ? 'green' : ($q['status'] === 'failed' ? 'red' : '') ?>"><?= e($q['status']) ?></span></td>
                  <td><?= e((string) $q['attempts']) ?></td>
                </tr>
              <?php endforeach; ?>
            </table>
          <?php endif; ?>
        </div>

        <div class="panel" style="border:1px solid var(--border)">
          <h3>لاگ ایمیل</h3>
          <?php if ($logs === []): ?><p class="muted">لاگی ثبت نشده است.</p><?php else: ?>
            <table class="tbl">
              <tr><th>گیرنده</th><th>موضوع</th><th>وضعیت</th><th>جزئیات</th><th>زمان</th></tr>
              <?php foreach ($logs as $l): ?>
                <tr>
                  <td dir="ltr"><?= e($l['to_email']) ?></td>
                  <td><?= e($l['subject']) ?></td>
                  <td><span class="badge <?= $l['status'] === 'sent' || $l['status'] === 'logged' ? 'green' : 'red' ?>"><?= e($l['status']) ?></span></td>
                  <td class="muted" dir="ltr" style="font-size:11px;max-width:300px;overflow:hidden;text-overflow:ellipsis"><?= e(mb_substr((string) ($l['detail'] ?? ''), 0, 140)) ?></td>
                  <td class="muted" style="font-size:11px"><?= e($l['created_at']) ?></td>
                </tr>
              <?php endforeach; ?>
            </table>
          <?php endif; ?>
        </div>
        <?php return (string) ob_get_clean();
    }
}
