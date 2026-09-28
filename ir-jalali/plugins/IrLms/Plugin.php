<?php

declare(strict_types=1);

namespace IRJalali\Plugins\IrLms;

use IRJalali\App\Middleware\StartSession;
use IRJalali\App\Middleware\VerifyCsrf;
use IRJalali\Core\Auth\Auth;
use IRJalali\Core\Builder\RenderContext;
use IRJalali\Core\Database\Database;
use IRJalali\Core\Hooks\Hooks;
use IRJalali\Core\Http\Request;
use IRJalali\Core\Http\Response;
use IRJalali\Core\Kernel\Application;
use IRJalali\Core\Plugins\PluginContext;
use IRJalali\Core\Plugins\PluginServiceProvider;
use IRJalali\Core\Sdk\IRJalali;
use IRJalali\Core\Security\Csrf;

/**
 * IR-LMS (Part 3 §11): courses + lessons on the CPT engine, enrollment with
 * payment-state workflow, membership-aware access gating and per-lesson
 * progress tracking.
 */
final class Plugin extends PluginServiceProvider
{
    private const ENROLL_STATUSES = ['pending' => 'در انتظار تأیید', 'active' => 'فعال', 'rejected' => 'ردشده'];

    public function boot(PluginContext $context): void
    {
        $sdk = IRJalali::for('ir-lms');

        $sdk->registerPostType('course', [
            'name' => 'دوره آموزشی',
            'icon' => '🎓',
            'supports' => ['title', 'editor', 'excerpt', 'thumbnail', 'seo', 'fields'],
            'settings' => ['archive' => true, 'single' => true, 'rest_api' => true],
        ]);
        $sdk->registerPostType('lesson', [
            'name' => 'درس',
            'icon' => '📖',
            'supports' => ['title', 'editor', 'fields'],
            'settings' => ['archive' => false, 'single' => false, 'rest_api' => true],
        ]);

        foreach ([
            ['key' => 'price', 'label' => 'شهریه دوره (تومان، 0 = رایگان)', 'type' => 'number', 'settings' => ['required' => false, 'min' => 0], 'post_type' => 'course'],
            ['key' => 'instructor', 'label' => 'نام مدرس', 'type' => 'text', 'settings' => ['required' => false], 'post_type' => 'course'],
            ['key' => 'duration_hours', 'label' => 'مدت دوره (ساعت)', 'type' => 'number', 'settings' => ['required' => false, 'min' => 0], 'post_type' => 'course'],
        ] as $f) {
            $pt = $f['post_type'];
            unset($f['post_type']);
            $sdk->registerField($f + ['location' => [['param' => 'post_type', 'value' => $pt]]]);
        }
        foreach ([
            ['key' => 'course_id', 'label' => 'شناسه دوره والد (عدد)', 'type' => 'number', 'settings' => ['required' => true, 'min' => 1]],
            ['key' => 'order', 'label' => 'ترتیب نمایش', 'type' => 'number', 'settings' => ['required' => false, 'min' => 0]],
            ['key' => 'is_free_sample', 'label' => 'درس رایگان (1 یا خالی)', 'type' => 'text', 'settings' => ['required' => false]],
        ] as $f) {
            $sdk->registerField($f + ['location' => [['param' => 'post_type', 'value' => 'lesson']]]);
        }

        // Builder block: course catalog grid.
        $sdk->registerBlock([
            'slug' => 'ir/courses',
            'title' => 'فهرست دوره‌ها',
            'category' => 'education',
            'icon' => '🎓',
            'description' => 'نمایش دوره‌های آموزشی منتشرشده.',
            'schema' => [
                ['key' => 'count', 'type' => 'number', 'label' => 'تعداد'],
                ['key' => 'title', 'type' => 'text', 'label' => 'عنوان بخش'],
            ],
            'defaults' => ['count' => 6, 'title' => 'دوره‌های آموزشی'],
            'render' => fn (array $data, RenderContext $ctx): string => $this->courseGrid(max(1, min(12, (int) ($data['count'] ?? 6))), (string) ($data['title'] ?? 'دوره‌های آموزشی')),
        ]);

        $sdk->registerAdminPage('enrollments', 'ثبت‌نام دوره‌ها', fn (Request $request): string => $this->adminPage($request), '🎓');

        $sdk->registerRoute(['GET'], '/courses', fn (Request $request): Response => $this->catalog(), [StartSession::class]);
        $sdk->registerRoute(['GET'], '/course/{slug}', fn (Request $request): Response => $this->coursePage($request), [StartSession::class]);
        $sdk->registerRoute(['POST'], '/course/enroll', fn (Request $request): Response => $this->enroll($request), [StartSession::class, VerifyCsrf::class]);
        $sdk->registerRoute(['GET'], '/learn', fn (Request $request): Response => $this->myLearning(), [StartSession::class]);
        $sdk->registerRoute(['GET'], '/lesson/{id}', fn (Request $request): Response => $this->lessonPage($request), [StartSession::class]);
        $sdk->registerRoute(['POST'], '/lesson/{id}/complete', fn (Request $request): Response => $this->completeLesson($request), [StartSession::class, VerifyCsrf::class]);

        $sdk->registerApiEndpoint('GET', 'lms/courses', function (): Response {
            $db = Application::get()->make(Database::class);

            return Response::json(['ok' => true, 'data' => $this->courses($db)]);
        });

        $sdk->command('lms:stats', 'آمار ثبت‌نام دوره‌ها', function (array $args, array $options, $out): int {
            $rows = Application::get()->make(Database::class)->select(
                'SELECT status, COUNT(*) AS c FROM ir_lms_enrollments GROUP BY status'
            );
            foreach ($rows as $row) {
                $out->writeln("{$row['status']}: {$row['c']}");
            }

            return 0;
        });
    }

    // ── Front ────────────────────────────────────────────────────

    private function catalog(): Response
    {
        $db = Application::get()->make(Database::class);
        $cards = '';
        foreach ($this->courses($db) as $c) {
            $cards .= '<article style="border:1px solid #e3e7f2;border-radius:14px;padding:16px">'
                . '<h3 style="margin:0 0 6px"><a href="/course/' . e($c['slug']) . '" style="text-decoration:none;color:inherit">' . e($c['title']) . '</a></h3>'
                . ($c['excerpt'] !== '' ? '<p class="muted" style="margin:0 0 8px">' . e(mb_substr($c['excerpt'], 0, 100)) . '</p>' : '')
                . '<p style="margin:0">' . ($c['instructor'] !== '' ? 'مدرس: ' . e($c['instructor']) . ' — ' : '')
                . '<b>' . ($c['price'] === 0 ? 'رایگان' : number_format($c['price']) . ' تومان') . '</b></p>'
                . '<p class="muted" style="margin:4px 0 0">' . $c['lesson_count'] . ' درس</p>'
                . '</article>';
        }

        return Response::html($this->shell('دوره‌ها',
            '<h1>🎓 دوره‌های آموزشی</h1>'
            . ($cards === '' ? '<p>هنوز دوره‌ای منتشر نشده است.</p>' : "<div style='display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:16px'>{$cards}</div>")));
    }

    private function coursePage(Request $request): Response
    {
        $db = Application::get()->make(Database::class);
        $course = $db->first(
            "SELECT * FROM posts WHERE post_type = 'course' AND slug = ? AND status = 'published' AND deleted_at IS NULL",
            [$request->route('slug')]
        );
        if ($course === null) {
            return Response::html($this->shell('دوره', '<h1>دوره پیدا نشد</h1>'), 404);
        }
        $courseId = (int) $course['id'];
        $meta = $this->meta($db, $courseId);
        $price = (int) ($meta['price'] ?? 0);
        $lessons = $this->lessonsOf($db, $courseId);

        /** @var Auth $auth */
        $auth = Application::get()->make(Auth::class);
        $userId = (int) $auth->id();
        $enrollment = $auth->check()
            ? $db->first('SELECT * FROM ir_lms_enrollments WHERE user_id = ? AND course_id = ?', [$userId, $courseId])
            : null;

        $csrf = Application::get()->make(Csrf::class)->field();
        $list = '';
        foreach ($lessons as $i => $l) {
            $free = $l['is_free_sample'] === '1';
            $locked = $enrollment === null || $enrollment['status'] !== 'active';
            $done = false;
            if ($auth->check() && !$locked) {
                $done = $db->first('SELECT id FROM ir_lms_progress WHERE user_id = ? AND lesson_id = ?', [$userId, (int) $l['id']]) !== null;
            }
            $list .= '<li style="margin:6px 0">'
                . ($free || !$locked
                    ? '<a href="/lesson/' . (int) $l['id'] . '">' . ($done ? '✅ ' : '') . e($l['title']) . ($free ? ' <span class="badge green">رایگان</span>' : '') . '</a>'
                    : '🔒 ' . e($l['title']))
                . '</li>';
        }

        $action = '';
        if ($enrollment === null) {
            $action = !$auth->check()
                ? '<p>برای ثبت‌نام ابتدا <a href="/member/login">وارد شوید</a> یا <a href="/register">ثبت‌نام کنید</a>.</p>'
                : '<form method="post" action="/course/enroll">' . $csrf
                  . '<input type="hidden" name="course_id" value="' . $courseId . '">'
                  . '<button style="background:#0d9488;color:#fff;border:none;padding:10px 22px;border-radius:10px;cursor:pointer">'
                  . ($price === 0 ? 'ثبت‌نام رایگان' : 'ثبت‌نام در دوره') . '</button></form>';
        } elseif ($enrollment['status'] === 'active') {
            $action = '<p style="color:#065f46">✅ شما در این دوره ثبت‌نام هستید.</p>';
        } elseif ($enrollment['status'] === 'pending') {
            $action = '<p style="color:#92400e">ثبت‌نام شما در انتظار تأیید است.</p>';
        } else {
            $action = '<p style="color:#991b1b">ثبت‌نام شما تأیید نشد.</p>';
        }

        $content = (string) ($course['content'] ?? '');
        $body = '<h1>' . e((string) $course['title']) . '</h1>'
            . '<p class="muted">' . (isset($meta['instructor']) && $meta['instructor'] !== '' ? 'مدرس: ' . e($meta['instructor']) . ' — ' : '')
            . '<b>' . ($price === 0 ? 'رایگان' : number_format($price) . ' تومان') . '</b>'
            . (isset($meta['duration_hours']) && $meta['duration_hours'] !== '' ? ' — ' . (int) $meta['duration_hours'] . ' ساعت' : '') . '</p>'
            . ($content !== '' ? '<div class="course-content">' . $content . '</div>' : '')
            . '<h3>سرفصل‌ها (' . count($lessons) . ' درس)</h3><ul>' . ($list !== '' ? $list : '<li>درسی تعریف نشده است.</li>') . '</ul>'
            . $action;

        return Response::html($this->shell((string) $course['title'], $body));
    }

    private function enroll(Request $request): Response
    {
        /** @var Auth $auth */
        $auth = Application::get()->make(Auth::class);
        if (!$auth->check()) {
            return Response::redirect('/member/login');
        }
        $db = Application::get()->make(Database::class);
        $courseId = (int) $request->input('course_id', 0);
        $course = $db->first("SELECT id FROM posts WHERE id = ? AND post_type = 'course' AND status = 'published' AND deleted_at IS NULL", [$courseId]);
        if ($course === null) {
            return Response::redirect('/courses');
        }
        $userId = (int) $auth->id();
        if ($db->first('SELECT id FROM ir_lms_enrollments WHERE user_id = ? AND course_id = ?', [$userId, $courseId]) !== null) {
            return Response::redirect('/learn');
        }

        $meta = $this->meta($db, $courseId);
        $price = (int) ($meta['price'] ?? 0);

        // Free course → instant; paid → instant when membership access grants it,
        // otherwise wait for admin approval (manual/offline payment).
        $status = 'pending';
        if ($price === 0) {
            $status = 'active';
        } else {
            $granted = Application::get()->make(Hooks::class)->applyFilters('membership.has_access', false, $userId);
            if ($granted === true) {
                $status = 'active';
            }
        }

        $db->insert('ir_lms_enrollments', [
            'user_id' => $userId,
            'course_id' => $courseId,
            'status' => $status,
            'enrolled_at' => date('Y-m-d H:i:s'),
        ]);

        return Response::redirect('/learn');
    }

    private function myLearning(): Response
    {
        /** @var Auth $auth */
        $auth = Application::get()->make(Auth::class);
        if (!$auth->check()) {
            return Response::redirect('/member/login');
        }
        $db = Application::get()->make(Database::class);
        $userId = (int) $auth->id();
        $rows = $db->select(
            'SELECT e.status, p.id AS course_id, p.title, p.slug FROM ir_lms_enrollments e JOIN posts p ON p.id = e.course_id WHERE e.user_id = ? ORDER BY e.id DESC',
            [$userId]
        );

        $items = '';
        foreach ($rows as $r) {
            $lessons = $this->lessonsOf($db, (int) $r['course_id']);
            $done = (int) $db->value(
                'SELECT COUNT(*) FROM ir_lms_progress pr JOIN posts ls ON ls.id = pr.lesson_id WHERE pr.user_id = ? AND ls.id IN (' . implode(',', array_map(fn ($l) => (int) $l['id'], $lessons) ?: [0]) . ')',
                [$userId]
            );
            $total = count($lessons);
            $pct = $total > 0 ? (int) round($done / $total * 100) : 0;
            $items .= '<li style="margin:8px 0"><a href="/course/' . e($r['slug']) . '">' . e($r['title']) . '</a> — '
                . '<span class="badge ' . ($r['status'] === 'active' ? 'green' : '') . '">' . e(self::ENROLL_STATUSES[$r['status']] ?? (string) $r['status']) . '</span> '
                . ($r['status'] === 'active' ? "پیشرفت: {$pct}٪ ({$done} از {$total} درس)" : '') . '</li>';
        }

        return Response::html($this->shell('یادگیری من',
            '<h1>📚 یادگیری من</h1>'
            . ($items === '' ? '<p>هنوز در دوره‌ای ثبت‌نام نکرده‌اید. <a href="/courses">مشاهده دوره‌ها</a></p>' : "<ul>{$items}</ul>")));
    }

    private function lessonPage(Request $request): Response
    {
        $db = Application::get()->make(Database::class);
        $lessonId = (int) $request->route('id', 0);
        $lesson = $db->first("SELECT * FROM posts WHERE id = ? AND post_type = 'lesson' AND status = 'published' AND deleted_at IS NULL", [$lessonId]);
        if ($lesson === null) {
            return Response::html($this->shell('درس', '<h1>درس پیدا نشد</h1>'), 404);
        }
        $meta = $this->meta($db, $lessonId);
        $courseId = (int) ($meta['course_id'] ?? 0);
        $isFree = ($meta['is_free_sample'] ?? '') === '1';

        /** @var Auth $auth */
        $auth = Application::get()->make(Auth::class);
        $userId = (int) $auth->id();
        $enrolled = $auth->check() && $db->first(
            "SELECT id FROM ir_lms_enrollments WHERE user_id = ? AND course_id = ? AND status = 'active'",
            [$userId, $courseId]
        ) !== null;

        if (!$isFree && !$enrolled) {
            return Response::html($this->shell('درس', '<h1>🔒 محتوای قفل‌شده</h1><p>برای مشاهده این درس باید در دوره ثبت‌نام کنید.</p><p><a href="/courses">مشاهده دوره‌ها</a></p>'), 403);
        }

        $done = $enrolled && $db->first('SELECT id FROM ir_lms_progress WHERE user_id = ? AND lesson_id = ?', [$userId, $lessonId]) !== null;
        $csrf = Application::get()->make(Csrf::class)->field();
        $body = '<h1>' . e((string) $lesson['title']) . ($isFree ? ' <span class="badge green">رایگان</span>' : '') . '</h1>'
            . ($enrolled && !$done
                ? '<form method="post" action="/lesson/' . $lessonId . '/complete">' . $csrf
                  . '<button style="background:#0d9488;color:#fff;border:none;padding:8px 18px;border-radius:10px;cursor:pointer">علامت‌گذاری به عنوان کامل‌شده ✓</button></form>'
                : ($done ? '<p style="color:#065f46">✅ این درس را کامل کرده‌اید.</p>' : ''))
            . '<div class="lesson-content" style="margin-top:14px">' . (string) ($lesson['content'] ?? '') . '</div>'
            . ($courseId > 0 ? '<p style="margin-top:20px"><a href="/learn">بازگشت به یادگیری من</a></p>' : '');

        return Response::html($this->shell((string) $lesson['title'], $body));
    }

    private function completeLesson(Request $request): Response
    {
        /** @var Auth $auth */
        $auth = Application::get()->make(Auth::class);
        if (!$auth->check()) {
            return Response::redirect('/member/login');
        }
        $db = Application::get()->make(Database::class);
        $lessonId = (int) $request->route('id', 0);
        $userId = (int) $auth->id();
        $meta = $this->meta($db, $lessonId);
        $courseId = (int) ($meta['course_id'] ?? 0);
        $enrolled = $db->first(
            "SELECT id FROM ir_lms_enrollments WHERE user_id = ? AND course_id = ? AND status = 'active'",
            [$userId, $courseId]
        ) !== null;

        if ($enrolled && $db->first('SELECT id FROM ir_lms_progress WHERE user_id = ? AND lesson_id = ?', [$userId, $lessonId]) === null) {
            $db->insert('ir_lms_progress', ['user_id' => $userId, 'lesson_id' => $lessonId, 'completed_at' => date('Y-m-d H:i:s')]);
        }

        return Response::redirect('/lesson/' . $lessonId);
    }

    // ── Admin ────────────────────────────────────────────────────

    private function adminPage(Request $request): string
    {
        $db = Application::get()->make(Database::class);
        $csrf = Application::get()->make(Csrf::class)->field();
        $message = null;

        if ($request->isMethod('POST') && $request->str('action') === 'enroll_status') {
            $status = $request->str('status');
            if (isset(self::ENROLL_STATUSES[$status])) {
                $db->table('ir_lms_enrollments')->where('id', (int) $request->str('id'))->update(['status' => $status]);
                $message = 'وضعیت ثبت‌نام به‌روزرسانی شد.';
            }
        }

        $rows = $db->select(
            'SELECT e.*, u.username, u.display_name, p.title AS course_title
             FROM ir_lms_enrollments e JOIN users u ON u.id = e.user_id JOIN posts p ON p.id = e.course_id
             ORDER BY e.id DESC LIMIT 100'
        );

        ob_start(); ?>
        <h2 style="margin-top:0">🎓 ثبت‌نام دوره‌ها</h2>
        <?php if ($message !== null): ?><p class="flash-ok"><?= e($message) ?></p><?php endif; ?>
        <?php if ($rows === []): ?>
          <p class="muted">ثبت‌نامی وجود ندارد.</p>
        <?php else: ?>
          <table class="tbl">
            <tr><th>کاربر</th><th>دوره</th><th>وضعیت</th><th>تاریخ</th><th>تغییر وضعیت</th></tr>
            <?php foreach ($rows as $r): ?>
              <tr>
                <td><?= e((string) ($r['display_name'] ?: $r['username'])) ?></td>
                <td><?= e($r['course_title']) ?></td>
                <td><span class="badge <?= $r['status'] === 'active' ? 'green' : ($r['status'] === 'rejected' ? 'red' : '') ?>"><?= e(self::ENROLL_STATUSES[$r['status']] ?? (string) $r['status']) ?></span></td>
                <td class="muted" style="font-size:11px"><?= e($r['enrolled_at']) ?></td>
                <td>
                  <form method="post" action="/admin/plugin/ir-lms--enrollments" style="display:flex;gap:4px">
                    <?= $csrf ?><input type="hidden" name="action" value="enroll_status"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                    <select name="status">
                      <?php foreach (self::ENROLL_STATUSES as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= $r['status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                      <?php endforeach; ?>
                    </select>
                    <button class="btn small" type="submit">ثبت</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </table>
        <?php endif; ?>
        <p class="muted">نکته: شناسه دوره را در فرم درس وارد کنید تا درس به دوره متصل شود.</p>
        <?php return (string) ob_get_clean();
    }

    // ── Helpers ──────────────────────────────────────────────────

    /** @return list<array{id:int,title:string,slug:string,excerpt:string,price:int,instructor:string,lesson_count:int}> */
    private function courses(Database $db): array
    {
        $rows = $db->select(
            "SELECT id, title, slug, excerpt FROM posts WHERE post_type = 'course' AND status = 'published' AND deleted_at IS NULL ORDER BY published_at DESC, id DESC LIMIT 50"
        );
        $out = [];
        foreach ($rows as $row) {
            $meta = $this->meta($db, (int) $row['id']);
            $out[] = [
                'id' => (int) $row['id'],
                'title' => (string) $row['title'],
                'slug' => (string) $row['slug'],
                'excerpt' => (string) ($row['excerpt'] ?? ''),
                'price' => (int) ($meta['price'] ?? 0),
                'instructor' => (string) ($meta['instructor'] ?? ''),
                'lesson_count' => count($this->lessonsOf($db, (int) $row['id'])),
            ];
        }

        return $out;
    }

    /** @return list<array{id:int,title:string,is_free_sample:string}> */
    private function lessonsOf(Database $db, int $courseId): array
    {
        $rows = $db->select(
            "SELECT id, title FROM posts WHERE post_type = 'lesson' AND status = 'published' AND deleted_at IS NULL ORDER BY id"
        );
        $out = [];
        foreach ($rows as $row) {
            $meta = $this->meta($db, (int) $row['id']);
            if ((int) ($meta['course_id'] ?? 0) !== $courseId) {
                continue;
            }
            $out[] = ['id' => (int) $row['id'], 'title' => (string) $row['title'], 'is_free_sample' => (string) ($meta['is_free_sample'] ?? ''), 'order' => (int) ($meta['order'] ?? 999)];
        }
        usort($out, fn ($a, $b) => $a['order'] <=> $b['order']);

        return $out;
    }

    private function courseGrid(int $count, string $title): string
    {
        $db = Application::get()->make(Database::class);
        $courses = array_slice($this->courses($db), 0, $count);
        if ($courses === []) {
            return '';
        }
        $cards = '';
        foreach ($courses as $c) {
            $cards .= '<article style="border:1px solid #e3e7f2;border-radius:14px;padding:16px">'
                . '<h3 style="margin:0 0 6px"><a href="/course/' . e($c['slug']) . '" style="text-decoration:none;color:inherit">' . e($c['title']) . '</a></h3>'
                . '<p style="margin:0">' . ($c['instructor'] !== '' ? e($c['instructor']) . ' — ' : '')
                . '<b>' . ($c['price'] === 0 ? 'رایگان' : number_format($c['price']) . ' تومان') . '</b></p>'
                . '<p class="muted" style="margin:4px 0 0">' . $c['lesson_count'] . ' درس</p></article>';
        }

        return '<section class="irj-courses">'
            . ($title !== '' ? '<h2 style="margin:8px 0 14px">' . e($title) . '</h2>' : '')
            . '<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px">' . $cards . '</div></section>';
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

    private function shell(string $title, string $body): string
    {
        return '<!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>' . e($title) . '</title>'
            . '<style>body{font-family:Tahoma,"Segoe UI",sans-serif;margin:0;background:#f6f8fc;color:#1f2540}'
            . 'main{max-width:900px;margin:0 auto;padding:24px}'
            . 'a{color:#0d9488}input,select,textarea{width:100%;padding:8px;border:1px solid #d6dcea;border-radius:8px;box-sizing:border-box}'
            . 'label{font-size:13px;display:grid;gap:4px}.muted{color:#7a84a6}'
            . '.badge{display:inline-block;padding:2px 10px;border-radius:99px;background:#e6ebf7;font-size:12px}'
            . '.badge.green{background:#d1fae5;color:#065f46}.badge.red{background:#fee2e2;color:#991b1b}'
            . '.tbl td,.tbl th{border:1px solid #e3e7f2;padding:8px;text-align:right}'
            . 'header{background:#fff;border-bottom:1px solid #e3e7f2;padding:12px 24px}header a{text-decoration:none;font-weight:700}'
            . '</style></head><body><header><a href="/courses">🎓 آموزش</a> · <a href="/learn">یادگیری من</a> · <a href="/">بازگشت به سایت</a></header>'
            . '<main>' . $body . '</main></body></html>';
    }
}
