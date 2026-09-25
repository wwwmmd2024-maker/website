<?php

declare(strict_types=1);

namespace IRJalali\Plugins\IrDemo;

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
 * IR-Demo — official reference plugin (Master Prompt Part 3 §43).
 * Exercises every Plugin API surface for real:
 * CPT + taxonomy + custom field + block + widget + admin page + REST
 * endpoint + settings + hook + filter + event + cron + migration + CLI command.
 */
final class Plugin extends PluginServiceProvider
{
    public function boot(PluginContext $context): void
    {
        $sdk = IRJalali::for('ir-demo');

        // 1. Custom Post Type (stored in core post_types; content lives in posts).
        $sdk->registerPostType('demo-item', [
            'name' => 'آیتم دمو',
            'icon' => '◈',
            'supports' => ['title', 'editor', 'excerpt', 'thumbnail'],
            'settings' => ['archive' => true, 'single' => true, 'rest_api' => true],
        ]);

        // 2. Taxonomy bound to the CPT.
        $sdk->registerTaxonomy('demo-category', [
            'name' => 'دسته‌های دمو',
            'post_types' => ['demo-item'],
            'hierarchical' => true,
        ]);

        // 3. Custom field attached to the CPT (rendered by the admin CPT form).
        $sdk->registerField([
            'key' => 'demo_price',
            'label' => 'قیمت دمو',
            'type' => 'number',
            'location' => [['param' => 'post_type', 'value' => 'demo-item']],
            'settings' => ['required' => false],
        ]);

        // 4. Block usable inside the Visual Builder.
        $sdk->registerBlock([
            'slug' => 'ir/demo-card',
            'title' => 'کارت دمو',
            'category' => 'content',
            'icon' => '▤',
            'description' => 'یک کارت ساده برای نمایش محتوای دمو.',
            'schema' => [
                ['key' => 'title', 'type' => 'text', 'label' => 'عنوان'],
                ['key' => 'body', 'type' => 'textarea', 'label' => 'متن'],
            ],
            'defaults' => ['title' => 'کارت دمو', 'body' => 'این یک بلاک نمونه است.'],
            'render' => function (array $data, RenderContext $ctx): string {
                $this->track('block_render');

                return '<div class="ij-demo-card" style="border:1px solid var(--border,#e3e7f2);border-radius:12px;padding:18px">'
                    . '<h3 style="margin:0 0 8px">' . htmlspecialchars((string) ($data['title'] ?? ''), ENT_QUOTES, 'UTF-8') . '</h3>'
                    . '<p style="margin:0;color:#7a84a6">' . nl2br(htmlspecialchars((string) ($data['body'] ?? ''), ENT_QUOTES, 'UTF-8')) . '</p>'
                    . '</div>';
            },
        ]);

        // 5. Sidebar widget.
        $sdk->registerWidget([
            'slug' => 'ir/demo-info',
            'title' => 'اطلاعات دمو',
            'description' => 'نمایش شمارنده رویدادهای دمو.',
            'icon' => 'ℹ️',
            'schema' => [['key' => 'title', 'type' => 'text', 'label' => 'عنوان']],
            'defaults' => ['title' => 'آمار دمو'],
            'render' => function (array $data, RenderContext $ctx): string {
                $count = $this->statCount();

                return '<div class="ij-demo-info">رویدادهای ثبت‌شده: <b>' . number_format($count) . '</b></div>';
            },
        ]);

        // 6. Admin page.
        $sdk->registerAdminPage('panel', 'پنل دمو', fn (Request $request): string => $this->adminPanel($request), '◈');

        // 7. Public route.
        $sdk->registerRoute(['GET'], '/demo/hello', function (): Response {
            $this->track('route_hit');

            return Response::json(['ok' => true, 'plugin' => 'ir-demo', 'message' => 'سلام از افزونه دمو']);
        });

        // 8. REST API endpoint (authenticated).
        $sdk->registerApiEndpoint('GET', 'demo/stats', function (): Response {
            $db = Application::get()->make(Database::class);
            $rows = $db->select('SELECT event, COUNT(*) AS c FROM ir_demo_stats GROUP BY event ORDER BY c DESC');

            return Response::json(['ok' => true, 'data' => $rows]);
        });

        // 9. Settings.
        $sdk->registerSettings([
            ['key' => 'demo_note', 'type' => 'text', 'label' => 'یادداشت دمو', 'default' => ''],
        ]);

        // 10. Hook + filter.
        $sdk->on('ir_demo.ping', function (): void {
            $this->track('hook_fired');
        });
        $sdk->filter('the_title', fn ($title) => $title, 10);

        // 11. Cron job.
        $sdk->cron('cleanup', 'پاکسازی آمار قدیمی دمو', 'daily', function (): void {
            try {
                Application::get()->make(Database::class)->delete(
                    'ir_demo_stats',
                    'created_at < :cutoff',
                    ['cutoff' => date('Y-m-d H:i:s', time() - 30 * 86400)]
                );
            } catch (\Throwable) {
                // Table may not exist before first activation on a fresh DB.
            }
        });

        // 12. CLI command.
        $sdk->command('demo:stats', 'نمایش آمار دمو', function (array $args, array $options, $out): int {
            $rows = $this->stats();
            foreach ($rows as $row) {
                $out->writeln("{$row['event']}: {$row['c']}");
            }

            return 0;
        });

        // 13. Event listener.
        $sdk->listen('ir.demo.viewed', function (): void {
            $this->track('event_viewed');
        });
    }

    public function activate(): void
    {
        $ctx = $this->context();
        if ($ctx->setting('demo_note', '') === '') {
            $ctx->saveSetting('demo_note', 'افزونه دمو فعال است.');
        }
        $this->track('activated');
    }

    public function uninstall(): void
    {
        try {
            Application::get()->make(Database::class)->query('DROP TABLE IF EXISTS ir_demo_stats');
        } catch (\Throwable) {
            // Best effort.
        }
    }

    private function adminPanel(Request $request): string
    {
        $sdk = IRJalali::for('ir-demo');
        if ($request->isMethod('POST')) {
            $sdk->saveSetting('demo_note', mb_substr($request->str('demo_note', ''), 0, 300));
        }
        $note = (string) $sdk->setting('demo_note', '');
        $csrf = Application::get()->make(Csrf::class)->field();
        $rows = $this->stats();

        ob_start(); ?>
        <h2 style="margin-top:0">◈ پنل دمو</h2>
        <p style="color:var(--muted)">این صفحه توسط افزونه <b>ir-demo</b> ثبت شده و تمام چرخه Plugin API را واقعی اجرا می‌کند.</p>
        <form method="post" action="/admin/plugin/ir-demo--panel">
          <?= $csrf ?>
          <label style="display:block;margin:10px 0 4px">یادداشت دمو</label>
          <input type="text" name="demo_note" value="<?= e($note) ?>" style="width:100%;max-width:480px">
          <button class="btn small" type="submit">ذخیره</button>
        </form>
        <h3 style="margin-top:18px">آمار رویدادها</h3>
        <table class="tbl">
          <tr><th>رویداد</th><th>تعداد</th></tr>
          <?php foreach ($rows as $row): ?>
            <tr><td dir="ltr"><?= e($row['event']) ?></td><td><?= e((string) $row['c']) ?></td></tr>
          <?php endforeach; ?>
        </table>
        <?php return (string) ob_get_clean();
    }

    /** @return list<array{event: string, c: int}> */
    private function stats(): array
    {
        try {
            $rows = Application::get()->make(Database::class)
                ->select('SELECT event, COUNT(*) AS c FROM ir_demo_stats GROUP BY event ORDER BY c DESC');

            return array_map(fn ($r) => ['event' => (string) $r['event'], 'c' => (int) $r['c']], $rows);
        } catch (\Throwable) {
            return [];
        }
    }

    private function statCount(): int
    {
        try {
            $row = Application::get()->make(Database::class)->first('SELECT COUNT(*) AS c FROM ir_demo_stats');

            return (int) ($row['c'] ?? 0);
        } catch (\Throwable) {
            return 0;
        }
    }

    private function track(string $event): void
    {
        try {
            Application::get()->make(Database::class)->insert('ir_demo_stats', [
                'event' => mb_substr($event, 0, 60),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable) {
            // Never break the page for analytics.
        }
    }
}
