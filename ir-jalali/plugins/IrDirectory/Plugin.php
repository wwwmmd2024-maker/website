<?php

declare(strict_types=1);

namespace IRJalali\Plugins\IrDirectory;

use IRJalali\App\Middleware\StartSession;
use IRJalali\App\Middleware\VerifyCsrf;
use IRJalali\Core\Database\Database;
use IRJalali\Core\Http\Request;
use IRJalali\Core\Http\Response;
use IRJalali\Core\Kernel\Application;
use IRJalali\Core\Plugins\PluginContext;
use IRJalali\Core\Plugins\PluginServiceProvider;
use IRJalali\Core\Sdk\IRJalali;
use IRJalali\Core\Security\Csrf;
use IRJalali\Core\Security\RateLimiter;

/**
 * IR-Directory (Part 3 §12): business listings/neededs. Listings are CPT
 * `listing` with contact fields; visitors submit (stored `pending`), admins
 * review/publish from the dedicated page, and a public searchable index is
 * served by the plugin — no core edits.
 */
final class Plugin extends PluginServiceProvider
{
    public function boot(PluginContext $context): void
    {
        $sdk = IRJalali::for('ir-directory');

        $sdk->registerPostType('listing', [
            'name' => 'آگهی',
            'icon' => '📒',
            'supports' => ['title', 'editor', 'excerpt', 'fields'],
            'settings' => ['archive' => true, 'single' => true, 'rest_api' => true],
        ]);

        foreach ([
            ['key' => 'category', 'label' => 'دسته', 'type' => 'text'],
            ['key' => 'city', 'label' => 'شهر', 'type' => 'text'],
            ['key' => 'phone', 'label' => 'تلفن', 'type' => 'text'],
            ['key' => 'website', 'label' => 'وب‌سایت', 'type' => 'text'],
            ['key' => 'address', 'label' => 'نشانی', 'type' => 'textarea'],
        ] as $f) {
            $sdk->registerField($f + [
                'settings' => ['required' => false],
                'location' => [['param' => 'post_type', 'value' => 'listing']],
            ]);
        }

        $sdk->registerAdminPage('listings', 'آگهی‌ها', fn (Request $request): string => $this->adminPage($request), '📒');

        $sdk->registerRoute(['GET'], '/directory', fn (Request $request): Response => $this->index($request), [StartSession::class]);
        $sdk->registerRoute(['GET'], '/directory/submit', fn (Request $request): Response => $this->submitForm(), [StartSession::class]);
        $sdk->registerRoute(['POST'], '/directory/submit', fn (Request $request): Response => $this->submitStore($request), [StartSession::class, VerifyCsrf::class]);

        $sdk->registerApiEndpoint('GET', 'directory/listings', function (Request $request): Response {
            $db = Application::get()->make(Database::class);

            return Response::json(['ok' => true, 'data' => $this->published($db, $request->str('q'), $request->str('city'))]);
        });
    }

    // ── Public ───────────────────────────────────────────────────

    private function index(Request $request): Response
    {
        $db = Application::get()->make(Database::class);
        $q = trim($request->str('q'));
        $city = trim($request->str('city'));
        $listings = $this->published($db, $q, $city);

        $cards = '';
        foreach ($listings as $l) {
            $cards .= '<article style="border:1px solid #e3e7f2;border-radius:14px;padding:16px">'
                . '<h3 style="margin:0 0 6px">' . e($l['title']) . '</h3>'
                . ($l['excerpt'] !== '' ? '<p class="muted" style="margin:0 0 8px">' . e(mb_substr($l['excerpt'], 0, 120)) . '</p>' : '')
                . '<p style="margin:0;font-size:13px">'
                . ($l['city'] !== '' ? '🏙 ' . e($l['city']) . ' — ' : '')
                . ($l['category'] !== '' ? '📂 ' . e($l['category']) . '<br>' : '')
                . ($l['phone'] !== '' ? '📞 <span dir="ltr">' . e($l['phone']) . '</span><br>' : '')
                . ($l['website'] !== '' ? '🌐 <a href="' . e($l['website']) . '" rel="nofollow noopener" target="_blank">' . e($l['website']) . '</a>' : '')
                . '</p></article>';
        }

        $body = '<h1>📒 دایرکتوری</h1>'
            . '<form method="get" action="/directory" class="toolbar" style="display:flex;gap:8px;margin-bottom:16px">'
            . '<input type="text" name="q" placeholder="جستجو…" value="' . e($q) . '" style="max-width:220px">'
            . '<input type="text" name="city" placeholder="شهر" value="' . e($city) . '" style="max-width:140px">'
            . '<button style="background:#0d9488;color:#fff;border:none;border-radius:8px;padding:8px 16px;cursor:pointer">جستجو</button>'
            . '</form>'
            . '<p style="margin-bottom:14px"><a href="/directory/submit">+ ثبت آگهی جدید</a></p>'
            . ($cards === ''
                ? '<p>آگهی‌ای یافت نشد.</p>'
                : "<div style='display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:16px'>{$cards}</div>");

        return Response::html($this->shell('دایرکتوری', $body));
    }

    private function submitForm(string $error = ''): Response
    {
        $csrf = Application::get()->make(Csrf::class)->field();
        $body = '<h1>ثبت آگهی</h1>'
            . ($error !== '' ? "<p style='color:#b91c1c'>{$error}</p>" : '')
            . '<p class="muted">آگهی شما پس از بررسی توسط مدیریت منتشر می‌شود.</p>'
            . '<form method="post" action="/directory/submit" style="max-width:480px;display:grid;gap:10px">' . $csrf
            . '<label>عنوان آگهی *<input type="text" name="title" required maxlength="200"></label>'
            . '<label>توضیحات *<textarea name="description" required rows="4" maxlength="2000"></textarea></label>'
            . '<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">'
            . '<label>دسته *<input type="text" name="category" required maxlength="80"></label>'
            . '<label>شهر *<input type="text" name="city" required maxlength="60"></label>'
            . '<label>تلفن *<input type="text" name="phone" required dir="ltr" maxlength="30"></label>'
            . '<label>وب‌سایت (اختیاری)<input type="url" name="website" dir="ltr" maxlength="200"></label>'
            . '</div>'
            . '<label>نشانی (اختیاری)<input type="text" name="address" maxlength="200"></label>'
            . '<button style="background:#0d9488;color:#fff;padding:10px 20px;border-radius:10px;border:none">ثبت برای بررسی</button>'
            . '</form>';

        return Response::html($this->shell('ثبت آگهی', $body));
    }

    private function submitStore(Request $request): Response
    {
        /** @var RateLimiter $limiter */
        $limiter = Application::get()->make(RateLimiter::class);
        $rlKey = 'directory:' . $request->ip();
        if ($limiter->tooManyAttempts($rlKey, 5)) {
            return $this->submitForm('تعداد ثبت‌ها بیش از حد مجاز است؛ بعداً تلاش کنید.');
        }

        $title = trim($request->str('title'));
        $description = trim($request->str('description'));
        $category = trim($request->str('category'));
        $city = trim($request->str('city'));
        $phone = trim($request->str('phone'));
        $website = trim($request->str('website'));
        $address = trim($request->str('address'));

        if (mb_strlen($title) < 5 || mb_strlen($description) < 10 || $category === '' || $city === '' || $phone === ''
            || ($website !== '' && !filter_var($website, FILTER_VALIDATE_URL))
        ) {
            $limiter->hit($rlKey, 600);

            return $this->submitForm('لطفاً همه فیلدهای الزامی را به‌درستی پر کنید.');
        }

        $db = Application::get()->make(Database::class);
        $now = date('Y-m-d H:i:s');
        $slug = $this->uniqueSlug($db, $title);

        $postId = (int) $db->insert('posts', [
            'uuid' => $this->uuid(),
            'post_type' => 'listing',
            'title' => mb_substr($title, 0, 200),
            'slug' => $slug,
            'excerpt' => mb_substr($description, 0, 300),
            'content' => $description,
            'status' => 'pending',
            'locale' => 'fa_IR',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach ([
            'category' => mb_substr($category, 0, 80),
            'city' => mb_substr($city, 0, 60),
            'phone' => mb_substr($phone, 0, 30),
            'website' => mb_substr($website, 0, 200),
            'address' => mb_substr($address, 0, 200),
        ] as $key => $value) {
            $db->insert('post_meta', ['post_id' => $postId, 'key' => 'field:' . $key, 'value' => $value]);
        }

        return Response::html($this->shell('ثبت شد', '<h1>✅ آگهی شما ثبت شد</h1><p>پس از بررسی و تأیید مدیریت، در دایرکتوری نمایش داده خواهد شد.</p><p><a href="/directory">بازگشت به دایرکتوری</a></p>'));
    }

    // ── Admin ────────────────────────────────────────────────────

    private function adminPage(Request $request): string
    {
        $db = Application::get()->make(Database::class);
        $csrf = Application::get()->make(Csrf::class)->field();
        $message = null;

        if ($request->isMethod('POST')) {
            $action = $request->str('action');
            $id = (int) $request->str('id');
            if ($action === 'publish') {
                $db->table('posts')->where('id', $id)->update(['status' => 'published', 'published_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
                $message = 'آگهی منتشر شد.';
            } elseif ($action === 'trash') {
                $db->table('posts')->where('id', $id)->update(['status' => 'trash', 'deleted_at' => date('Y-m-d H:i:s')]);
                $message = 'آگهی به زباله‌دان رفت.';
            }
        }

        $pending = $db->select("SELECT * FROM posts WHERE post_type = 'listing' AND status = 'pending' AND deleted_at IS NULL ORDER BY id DESC LIMIT 100");
        $published = $db->select("SELECT * FROM posts WHERE post_type = 'listing' AND status = 'published' AND deleted_at IS NULL ORDER BY id DESC LIMIT 50");

        ob_start(); ?>
        <h2 style="margin-top:0">📒 مدیریت آگهی‌ها</h2>
        <?php if ($message !== null): ?><p class="flash-ok"><?= e($message) ?></p><?php endif; ?>

        <div class="panel" style="border:1px solid var(--border);margin-bottom:16px">
          <h3>در انتظار بررسی (<?= count($pending) ?>)</h3>
          <?php if ($pending === []): ?><p class="muted">موردی نیست.</p><?php else: ?>
            <table class="tbl">
              <tr><th>عنوان</th><th>دسته/شهر</th><th>تلفن</th><th>عملیات</th></tr>
              <?php foreach ($pending as $p): $m = $this->meta($db, (int) $p['id']); ?>
                <tr>
                  <td><?= e($p['title']) ?><br><span class="muted" style="font-size:11px"><?= e(mb_substr((string) $p['excerpt'], 0, 80)) ?></span></td>
                  <td><?= e($m['category'] ?? '') ?> / <?= e($m['city'] ?? '') ?></td>
                  <td dir="ltr"><?= e($m['phone'] ?? '') ?></td>
                  <td>
                    <form method="post" action="/admin/plugin/ir-directory--listings" style="display:inline">
                      <?= $csrf ?><input type="hidden" name="action" value="publish"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                      <button class="btn small" type="submit">انتشار</button>
                    </form>
                    <form method="post" action="/admin/plugin/ir-directory--listings" style="display:inline">
                      <?= $csrf ?><input type="hidden" name="action" value="trash"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                      <button class="btn small" type="submit" data-confirm="حذف شود؟">حذف</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </table>
          <?php endif; ?>
        </div>

        <div class="panel" style="border:1px solid var(--border)">
          <h3>منتشرشده‌ها</h3>
          <?php if ($published === []): ?><p class="muted">آگهی منتشرشده‌ای نیست.</p><?php else: ?>
            <table class="tbl">
              <tr><th>عنوان</th><th>شهر</th><th>تاریخ انتشار</th></tr>
              <?php foreach ($published as $p): $m = $this->meta($db, (int) $p['id']); ?>
                <tr><td><?= e($p['title']) ?></td><td><?= e($m['city'] ?? '') ?></td><td class="muted" style="font-size:11px"><?= e((string) ($p['published_at'] ?? '')) ?></td></tr>
              <?php endforeach; ?>
            </table>
          <?php endif; ?>
        </div>
        <?php return (string) ob_get_clean();
    }

    // ── Helpers ──────────────────────────────────────────────────

    /** @return list<array{title:string,excerpt:string,city:string,category:string,phone:string,website:string}> */
    private function published(Database $db, string $q, string $city): array
    {
        $sql = "SELECT id, title, excerpt FROM posts WHERE post_type = 'listing' AND status = 'published' AND deleted_at IS NULL";
        $params = [];
        if ($q !== '') {
            $sql .= ' AND (title LIKE :q OR excerpt LIKE :q OR content LIKE :q)';
            $params['q'] = '%' . $q . '%';
        }
        $sql .= ' ORDER BY published_at DESC, id DESC LIMIT 60';
        $rows = $db->select($sql, $params);

        $out = [];
        foreach ($rows as $row) {
            $meta = $this->meta($db, (int) $row['id']);
            if ($city !== '' && mb_stripos((string) ($meta['city'] ?? ''), $city) === false) {
                continue;
            }
            $out[] = [
                'title' => (string) $row['title'],
                'excerpt' => (string) ($row['excerpt'] ?? ''),
                'city' => (string) ($meta['city'] ?? ''),
                'category' => (string) ($meta['category'] ?? ''),
                'phone' => (string) ($meta['phone'] ?? ''),
                'website' => (string) ($meta['website'] ?? ''),
            ];
        }

        return $out;
    }

    /** @return array<string, string> */
    private function meta(Database $db, int $postId): array
    {
        $rows = $db->select('SELECT `key`, `value` FROM post_meta WHERE post_id = ?', [$postId]);
        $out = [];
        foreach ($rows as $row) {
            $key = (string) $row['key'];
            if (str_starts_with($key, 'field:')) {
                $out[substr($key, 6)] = (string) $row['value'];
            }
        }

        return $out;
    }

    private function uniqueSlug(Database $db, string $title): string
    {
        $base = trim(mb_strtolower($title));
        $base = preg_replace('/[^a-z0-9\x{0600}-\x{06FF}]+/u', '-', $base) ?? '';
        $base = trim($base, '-');
        if ($base === '') {
            $base = 'listing-' . bin2hex(random_bytes(3));
        }
        $slug = mb_substr($base, 0, 200);
        $i = 2;
        while ($db->first("SELECT id FROM posts WHERE post_type = 'listing' AND slug = ?", [$slug]) !== null) {
            $slug = mb_substr($base, 0, 190) . '-' . $i++;
        }

        return $slug;
    }

    private function uuid(): string
    {
        $b = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
        $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }

    private function shell(string $title, string $body): string
    {
        return '<!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>' . e($title) . '</title>'
            . '<style>body{font-family:Tahoma,"Segoe UI",sans-serif;margin:0;background:#f6f8fc;color:#1f2540}'
            . 'main{max-width:1000px;margin:0 auto;padding:24px}'
            . 'a{color:#0d9488}input,select,textarea{width:100%;padding:8px;border:1px solid #d6dcea;border-radius:8px;box-sizing:border-box}'
            . 'label{font-size:13px;display:grid;gap:4px}.muted{color:#7a84a6}'
            . '.badge{display:inline-block;padding:2px 10px;border-radius:99px;background:#e6ebf7;font-size:12px}'
            . '.tbl td,.tbl th{border:1px solid #e3e7f2;padding:8px;text-align:right}'
            . 'header{background:#fff;border-bottom:1px solid #e3e7f2;padding:12px 24px}header a{text-decoration:none;font-weight:700}'
            . '</style></head><body><header><a href="/directory">📒 دایرکتوری</a> · <a href="/">بازگشت به سایت</a></header>'
            . '<main>' . $body . '</main></body></html>';
    }
}
