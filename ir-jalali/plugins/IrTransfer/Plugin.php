<?php

declare(strict_types=1);

namespace IRJalali\Plugins\IrTransfer;

use IRJalali\Core\Database\Database;
use IRJalali\Core\Http\Request;
use IRJalali\Core\Http\Response;
use IRJalali\Core\Kernel\Application;
use IRJalali\Core\Plugins\PluginContext;
use IRJalali\Core\Plugins\PluginServiceProvider;
use IRJalali\Core\Sdk\IRJalali;
use IRJalali\Core\Security\Csrf;

/**
 * IR-Transfer (Part 3): content portability.
 * - Export: JSON bundle of posts/pages/CPTs (+meta), menus, forms and options.
 * - Import: restores such a bundle (safe: never overwrites existing slugs).
 * - Clone: creates a full-site backup package via the core backup service
 *   (restore on another install = site clone).
 */
final class Plugin extends PluginServiceProvider
{
    private const FORMAT = 'ir-jalali/transfer';
    private const VERSION = 1;

    public function boot(PluginContext $context): void
    {
        $sdk = IRJalali::for('ir-transfer');
        $sdk->registerAdminPage('transfer', 'انتقال محتوا', fn (Request $request) => $this->adminPage($request), '⇄', 'settings.manage');
    }

    /** @return string|Response */
    private function adminPage(Request $request)
    {
        $csrf = Application::get()->make(Csrf::class)->field();
        $message = null;
        $error = null;

        if ($request->isMethod('POST')) {
            $action = $request->str('action');
            if ($action === 'export') {
                $payload = $this->buildExport();
                $name = 'ir-jalali-export-' . date('Ymd-His') . '.json';

                return Response::text($payload, 200)
                    ->withHeader('Content-Type', 'application/json; charset=utf-8')
                    ->withHeader('Content-Disposition', 'attachment; filename="' . $name . '"');
            }
            if ($action === 'import') {
                $file = $request->file('bundle');
                try {
                    $result = $this->runImport($file);
                    $message = "ورودی انجام شد: {$result['posts']} محتوا، {$result['menus']} فهرست، {$result['forms']} فرم، {$result['options']} تنظیمات ({$result['skipped']} مورد تکراری رد شد).";
                } catch (\Throwable $e) {
                    $error = 'خطا در ورودی: ' . $e->getMessage();
                }
            } elseif ($action === 'clone') {
                try {
                    $backup = Application::get()->make(\IRJalali\Core\Updates\SiteBackupService::class)->create('clone');
                    $message = 'بسته کامل سایت (برای کلون/مهاجرت) ساخته شد: ' . basename($backup);
                } catch (\Throwable $e) {
                    $error = 'خطا در ساخت بسته کلون: ' . $e->getMessage();
                }
            }
        }

        $db = Application::get()->make(Database::class);
        $counts = [
            'posts' => (int) $db->value('SELECT COUNT(*) FROM posts WHERE deleted_at IS NULL'),
            'menus' => (int) $db->value('SELECT COUNT(*) FROM menus'),
            'forms' => (int) $db->value('SELECT COUNT(*) FROM forms'),
        ];

        ob_start(); ?>
        <h2 style="margin-top:0">⇄ انتقال محتوا</h2>
        <?php if ($message !== null): ?><p class="flash-ok"><?= e($message) ?></p><?php endif; ?>
        <?php if ($error !== null): ?><p style="color:#b91c1c"><?= e($error) ?></p><?php endif; ?>

        <div class="cards" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px">
          <div class="panel" style="border:1px solid var(--border)">
            <h3>۱) خروجی (Export)</h3>
            <p class="muted" style="font-size:13px">دریافت فایل JSON شامل <?= (int) $counts['posts'] ?> محتوا، <?= (int) $counts['menus'] ?> فهرست و <?= (int) $counts['forms'] ?> فرم همراه متا و تنظیمات.</p>
            <form method="post" action="/admin/plugin/ir-transfer--transfer"><?= $csrf ?>
              <input type="hidden" name="action" value="export">
              <button class="btn small" type="submit">دانلود فایل خروجی</button>
            </form>
          </div>

          <div class="panel" style="border:1px solid var(--border)">
            <h3>۲) ورودی (Import)</h3>
            <p class="muted" style="font-size:13px">بازیابی فایل خروجی. اسلاگ‌های تکراری بازنویسی نمی‌شوند.</p>
            <form method="post" action="/admin/plugin/ir-transfer--transfer" enctype="multipart/form-data"><?= $csrf ?>
              <input type="hidden" name="action" value="import">
              <input type="file" name="bundle" accept=".json,application/json" required style="width:100%;margin-bottom:8px">
              <button class="btn small" type="submit" data-confirm="محتوای فایل وارد سایت می‌شود؛ ادامه؟">ورودی فایل</button>
            </form>
          </div>

          <div class="panel" style="border:1px solid var(--border)">
            <h3>۳) کلون کامل سایت</h3>
            <p class="muted" style="font-size:13px">یک بسته پشتیبان کامل (دیتابیس + فایل‌ها) می‌سازد؛ با بازیابی آن روی نصب دیگر، سایت کلون می‌شود. مدیریت: <a href="/admin/backups">پشتیبان‌گیری</a>.</p>
            <form method="post" action="/admin/plugin/ir-transfer--transfer"><?= $csrf ?>
              <input type="hidden" name="action" value="clone">
              <button class="btn small" type="submit">ساخت بسته کلون</button>
            </form>
          </div>
        </div>
        <?php return (string) ob_get_clean();
    }

    private function buildExport(): string
    {
        $db = Application::get()->make(Database::class);

        $posts = [];
        $rows = $db->select('SELECT * FROM posts WHERE deleted_at IS NULL ORDER BY id');
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $meta = [];
            foreach ($db->select('SELECT `key`, `value` FROM post_meta WHERE post_id = ?', [$id]) as $m) {
                $meta[(string) $m['key']] = (string) $m['value'];
            }
            $posts[] = [
                'type' => $row['post_type'], 'title' => $row['title'], 'slug' => $row['slug'],
                'excerpt' => $row['excerpt'], 'content' => $row['content'], 'content_json' => $row['content_json'],
                'status' => $row['status'], 'published_at' => $row['published_at'], 'meta' => $meta,
            ];
        }

        $menus = [];
        foreach ($db->select('SELECT * FROM menus ORDER BY id') as $menu) {
            $items = $db->select('SELECT title, type, url, reference_type, reference_id, parent_id, ordering, target, css_class FROM menu_items WHERE menu_id = ? ORDER BY ordering', [(int) $menu['id']]);
            $menus[] = ['slug' => $menu['slug'], 'name' => $menu['name'], 'location' => $menu['location'], 'items' => $items];
        }

        $forms = [];
        foreach ($db->select('SELECT * FROM forms ORDER BY id') as $form) {
            $fields = $db->select('SELECT `key`, label, type, settings, ordering FROM form_fields WHERE form_id = ? ORDER BY ordering', [(int) $form['id']]);
            $forms[] = ['title' => $form['title'], 'slug' => $form['slug'], 'description' => $form['description'], 'settings' => $form['settings'], 'is_active' => $form['is_active'], 'fields' => $fields];
        }

        $options = [];
        foreach ($db->select("SELECT `key`, `value` FROM options WHERE `key` NOT IN ('install_secret','app_key')") as $o) {
            $options[(string) $o['key']] = (string) $o['value'];
        }

        return (string) json_encode([
            'format' => self::FORMAT,
            'version' => self::VERSION,
            'exported_at' => date('c'),
            'site' => ['title' => $options['site_title'] ?? '', 'url' => $options['site_url'] ?? ''],
            'options' => $options,
            'posts' => $posts,
            'menus' => $menus,
            'forms' => $forms,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /** @param array{name?:string,tmp_name?:string,size?:int,error?:int}|null $file @return array{posts:int,menus:int,forms:int,options:int,skipped:int} */
    private function runImport(?array $file): array
    {
        if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('فایلی دریافت نشد.');
        }
        $raw = (string) file_get_contents((string) $file['tmp_name']);
        $data = json_decode($raw, true);
        if (!is_array($data) || ($data['format'] ?? '') !== self::FORMAT) {
            throw new \RuntimeException('فرمت فایل معتبر نیست (باید خروجی همین بخش باشد).');
        }

        $db = Application::get()->make(Database::class);
        $now = date('Y-m-d H:i:s');
        $result = ['posts' => 0, 'menus' => 0, 'forms' => 0, 'options' => 0, 'skipped' => 0];

        foreach ((array) ($data['options'] ?? []) as $key => $value) {
            if (!is_string($key) || $key === '' || in_array($key, ['install_secret', 'app_key'], true)) {
                continue;
            }
            $existing = $db->table('options')->where('key', $key)->first();
            if ($existing === null) {
                $db->insert('options', ['key' => $key, 'value' => (string) $value, 'autoload' => 1, 'created_at' => $now, 'updated_at' => $now]);
                $result['options']++;
            } else {
                $result['skipped']++;
            }
        }

        foreach ((array) ($data['posts'] ?? []) as $p) {
            $type = (string) ($p['type'] ?? 'post');
            $slug = (string) ($p['slug'] ?? '');
            if ($slug === '' || $db->first('SELECT id FROM posts WHERE post_type = ? AND slug = ?', [$type, $slug]) !== null) {
                $result['skipped']++;
                continue;
            }
            $postId = (int) $db->insert('posts', [
                'uuid' => bin2hex(random_bytes(16)),
                'post_type' => mb_substr($type, 0, 60),
                'title' => mb_substr((string) ($p['title'] ?? ''), 0, 255),
                'slug' => mb_substr($slug, 0, 255),
                'excerpt' => isset($p['excerpt']) ? (string) $p['excerpt'] : null,
                'content' => isset($p['content']) ? (string) $p['content'] : null,
                'content_json' => isset($p['content_json']) ? (string) $p['content_json'] : null,
                'status' => in_array($p['status'] ?? '', ['draft', 'pending', 'published', 'scheduled', 'private'], true) ? $p['status'] : 'draft',
                'published_at' => $p['published_at'] ?? null,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            foreach ((array) ($p['meta'] ?? []) as $mk => $mv) {
                if (is_string($mk) && $mk !== '' && str_starts_with($mk, 'field:')) {
                    $db->insert('post_meta', ['post_id' => $postId, 'key' => mb_substr($mk, 0, 190), 'value' => (string) $mv]);
                }
            }
            $result['posts']++;
        }

        foreach ((array) ($data['menus'] ?? []) as $m) {
            $slug = (string) ($m['slug'] ?? '');
            if ($slug === '' || $db->first('SELECT id FROM menus WHERE slug = ?', [$slug]) !== null) {
                $result['skipped']++;
                continue;
            }
            $menuId = (int) $db->insert('menus', [
                'slug' => mb_substr($slug, 0, 80),
                'name' => mb_substr((string) ($m['name'] ?? $slug), 0, 120),
                'location' => mb_substr((string) ($m['location'] ?? 'primary'), 0, 60),
                'created_at' => $now, 'updated_at' => $now,
            ]);
            foreach ((array) ($m['items'] ?? []) as $item) {
                $db->insert('menu_items', [
                    'menu_id' => $menuId,
                    'title' => mb_substr((string) ($item['title'] ?? ''), 0, 120),
                    'type' => mb_substr((string) ($item['type'] ?? 'custom'), 0, 40),
                    'url' => mb_substr((string) ($item['url'] ?? ''), 0, 500),
                    'reference_type' => $item['reference_type'] ?? null,
                    'reference_id' => $item['reference_id'] !== null ? (int) $item['reference_id'] : null,
                    'parent_id' => null,
                    'ordering' => (int) ($item['ordering'] ?? 0),
                    'target' => mb_substr((string) ($item['target'] ?? ''), 0, 20) ?: null,
                    'css_class' => mb_substr((string) ($item['css_class'] ?? ''), 0, 120) ?: null,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
            $result['menus']++;
        }

        foreach ((array) ($data['forms'] ?? []) as $f) {
            $slug = (string) ($f['slug'] ?? '');
            if ($slug === '' || $db->first('SELECT id FROM forms WHERE slug = ?', [$slug]) !== null) {
                $result['skipped']++;
                continue;
            }
            $formId = (int) $db->insert('forms', [
                'title' => mb_substr((string) ($f['title'] ?? ''), 0, 150),
                'slug' => mb_substr($slug, 0, 150),
                'description' => isset($f['description']) ? mb_substr((string) $f['description'], 0, 500) : null,
                'settings' => (string) ($f['settings'] ?? '{}'),
                'is_active' => (int) ($f['is_active'] ?? 1),
                'created_at' => $now, 'updated_at' => $now,
            ]);
            foreach ((array) ($f['fields'] ?? []) as $field) {
                $db->insert('form_fields', [
                    'form_id' => $formId,
                    'key' => mb_substr((string) ($field['key'] ?? ''), 0, 80),
                    'label' => mb_substr((string) ($field['label'] ?? ''), 0, 150),
                    'type' => mb_substr((string) ($field['type'] ?? 'text'), 0, 40),
                    'settings' => (string) ($field['settings'] ?? '{}'),
                    'ordering' => (int) ($field['ordering'] ?? 0),
                ]);
            }
            $result['forms']++;
        }

        return $result;
    }
}
