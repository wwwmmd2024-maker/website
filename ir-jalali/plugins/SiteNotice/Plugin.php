<?php

declare(strict_types=1);

namespace IRJalali\Plugins\SiteNotice;

use IRJalali\Core\Builder\RenderContext;
use IRJalali\Core\Database\Database;
use IRJalali\Core\Http\Request;
use IRJalali\Core\Http\Response;
use IRJalali\Core\Kernel\Application;
use IRJalali\Core\Plugins\PluginContext;
use IRJalali\Core\Plugins\PluginServiceProvider;
use IRJalali\Core\Sdk\IRJalali;
use IRJalali\Core\Security\Csrf;

/**
 * Reference plugin for the Plugin API: block, widget-less admin page,
 * public route, settings, cron cleanup and a versioned migration.
 */
final class Plugin extends PluginServiceProvider
{
    public function boot(PluginContext $context): void
    {
        $sdk = IRJalali::for('site-notice');

        $sdk->registerBlock([
            'slug' => 'site/notice',
            'title' => 'اعلان سایت',
            'category' => 'general',
            'icon' => '📢',
            'description' => 'نمایش پیام اعلان ذخیره‌شده در تنظیمات افزونه.',
            'render' => function (array $data, RenderContext $ctx): string {
                $sdk = IRJalali::for('site-notice');
                if ($sdk->setting('enabled', '1') !== '1') {
                    return '';
                }
                $message = trim((string) $sdk->setting('message', ''));
                if ($message === '') {
                    return '';
                }
                if (!$ctx->isPreview) {
                    $this->logView();
                }
                $align = in_array($data['align'] ?? 'center', ['right', 'center', 'left'], true) ? $data['align'] : 'center';

                return '<div class="ij-notice ij-notice-' . $align . '" role="status">'
                    . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</div>';
            },
        ]);

        $sdk->registerAdminPage('settings', 'اعلان سایت', fn (Request $request): string => $this->settingsPage($request), '📢');

        $sdk->registerRoute(['GET'], '/site-notice', function (): Response {
            $sdk = IRJalali::for('site-notice');

            return Response::json([
                'enabled' => $sdk->setting('enabled', '1') === '1',
                'message' => (string) $sdk->setting('message', ''),
            ]);
        });

        $sdk->registerSettings([
            ['key' => 'enabled', 'type' => 'checkbox', 'label' => 'نمایش اعلان', 'default' => '1'],
            ['key' => 'message', 'type' => 'text', 'label' => 'متن اعلان', 'default' => ''],
        ]);

        $sdk->cron('cleanup', 'پاکسازی بازدیدهای قدیمی اعلان', 'daily', function (): void {
            try {
                Application::get()->make(Database::class)->delete(
                    'site_notice_views',
                    'viewed_at < :cutoff',
                    ['cutoff' => date('Y-m-d H:i:s', time() - 90 * 86400)]
                );
            } catch (\Throwable) {
                // Table missing (uninstalled?) — nothing to clean.
            }
        });
    }

    public function activate(): void
    {
        $ctx = $this->context();
        if ($ctx->setting('message', '') === '') {
            $ctx->saveSetting('message', 'به سایت ما خوش آمدید 🎉');
        }
        if ($ctx->setting('enabled', '') === '') {
            $ctx->saveSetting('enabled', '1');
        }
    }

    public function uninstall(): void
    {
        try {
            $db = Application::get()->make(Database::class);
            $db->query('DROP TABLE IF EXISTS site_notice_views');
        } catch (\Throwable) {
            // Best effort.
        }
    }

    private function settingsPage(Request $request): string
    {
        $sdk = IRJalali::for('site-notice');
        $saved = false;
        if ($request->isMethod('POST')) {
            $sdk->saveSetting('message', mb_substr($request->str('message', ''), 0, 500));
            $sdk->saveSetting('enabled', $request->input('enabled') ? '1' : '0');
            $saved = true;
        }

        $message = (string) $sdk->setting('message', '');
        $enabled = $sdk->setting('enabled', '1') === '1';
        $views = $this->viewCount();
        $csrf = Application::get()->make(Csrf::class)->field();

        ob_start(); ?>
        <h2 style="margin-top:0">📢 اعلان سایت</h2>
        <?php if ($saved): ?><p class="flash-ok">تنظیمات ذخیره شد.</p><?php endif; ?>
        <p style="color:var(--muted)">بازدیدهای ثبت‌شده (۹۰ روز اخیر): <strong><?= number_format($views) ?></strong></p>
        <form method="post" action="/admin/plugin/site-notice--settings">
          <?= $csrf ?>
          <label style="display:block;margin:12px 0 4px">متن اعلان</label>
          <input type="text" name="message" value="<?= e($message) ?>" maxlength="500" style="width:100%;max-width:520px">
          <label style="display:block;margin:12px 0">
            <input type="checkbox" name="enabled" value="1" <?= $enabled ? 'checked' : '' ?>> نمایش اعلان در سایت
          </label>
          <button type="submit" class="btn">ذخیره</button>
        </form>
        <p style="color:var(--muted)">نکته: برای نمایش، بلاک «اعلان سایت» را در ویرایشگر به صفحه اضافه کنید.</p>
        <?php return (string) ob_get_clean();
    }

    private function logView(): void
    {
        try {
            Application::get()->make(Database::class)->insert('site_notice_views', [
                'viewed_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable) {
            // Never break the frontend for analytics.
        }
    }

    private function viewCount(): int
    {
        try {
            $rows = Application::get()->make(Database::class)->select(
                'SELECT COUNT(*) AS c FROM site_notice_views WHERE viewed_at >= :cutoff',
                ['cutoff' => date('Y-m-d H:i:s', time() - 90 * 86400)]
            );

            return (int) ($rows[0]['c'] ?? 0);
        } catch (\Throwable) {
            return 0;
        }
    }
}
