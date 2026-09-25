<?php

declare(strict_types=1);

namespace IRJalali\Plugins\IrMembership;

use IRJalali\App\Middleware\StartSession;
use IRJalali\App\Middleware\VerifyCsrf;
use IRJalali\Core\Auth\Auth;
use IRJalali\Core\Database\Database;
use IRJalali\Core\Http\Request;
use IRJalali\Core\Http\Response;
use IRJalali\Core\Kernel\Application;
use IRJalali\Core\Plugins\PluginContext;
use IRJalali\Core\Plugins\PluginServiceProvider;
use IRJalali\Core\Sdk\IRJalali;
use IRJalali\Core\Security\Csrf;
use IRJalali\Core\Security\Hasher;
use IRJalali\Core\Security\RateLimiter;

/**
 * IR-Membership (Part 3 §10): self-registration, subscription plans, member
 * dashboard and access gating. Users live in core `users` (role: subscriber);
 * plans and subscriptions are plugin-owned tables.
 */
final class Plugin extends PluginServiceProvider
{
    private const SUB_STATUSES = ['pending_payment' => 'در انتظار پرداخت', 'active' => 'فعال', 'expired' => 'منقضی‌شده', 'cancelled' => 'لغوشده'];

    public function boot(PluginContext $context): void
    {
        $sdk = IRJalali::for('ir-membership');

        $sdk->registerSettings([
            ['key' => 'enable_registration', 'type' => 'checkbox', 'label' => 'باز بودن ثبت‌نام', 'default' => '1'],
            ['key' => 'welcome_note', 'type' => 'textarea', 'label' => 'متن خوش‌آمدگویی داشبورد', 'default' => 'به ناحیه کاربری خوش آمدید.'],
        ]);

        $sdk->registerAdminPage('subscriptions', 'اشتراک‌ها', fn (Request $request): string => $this->adminPage($request), '👥', 'users.manage');

        // Public/member routes.
        $sdk->registerRoute(['GET'], '/plans', fn (Request $request): Response => $this->plansPage(), [StartSession::class]);
        $sdk->registerRoute(['GET'], '/register', fn (Request $request): Response => $this->registerForm(), [StartSession::class]);
        $sdk->registerRoute(['POST'], '/register', fn (Request $request): Response => $this->registerSubmit($request), [StartSession::class, VerifyCsrf::class]);
        $sdk->registerRoute(['GET'], '/member/login', fn (Request $request): Response => $this->loginForm(), [StartSession::class]);
        $sdk->registerRoute(['POST'], '/member/login', fn (Request $request): Response => $this->loginSubmit($request), [StartSession::class, VerifyCsrf::class]);
        $sdk->registerRoute(['POST'], '/member/logout', fn (Request $request): Response => $this->logout(), [StartSession::class, VerifyCsrf::class]);
        $sdk->registerRoute(['GET'], '/member', fn (Request $request): Response => $this->dashboard(), [StartSession::class]);
        $sdk->registerRoute(['POST'], '/member/subscribe', fn (Request $request): Response => $this->subscribe($request), [StartSession::class, VerifyCsrf::class]);

        $sdk->registerApiEndpoint('GET', 'membership/plans', function (): Response {
            $rows = Application::get()->make(Database::class)
                ->select('SELECT id, title, slug, description, price, period_days FROM ir_membership_plans WHERE is_active = 1 ORDER BY price');

            return Response::json(['ok' => true, 'data' => $rows]);
        });

        // Access-gating extension point: return true to grant access.
        $sdk->filter('membership.has_access', function ($granted, int $userId) {
            if ($granted === true) {
                return true;
            }

            return $this->userHasActiveSubscription($userId);
        }, 10);

        // Expire overdue subscriptions each day.
        $sdk->cron('expire', 'انقضای اشتراک‌های گذشته', 'daily', function (): void {
            try {
                Application::get()->make(Database::class)->table('ir_membership_subscriptions')
                    ->where('status', 'active')
                    ->where('expires_at', date('Y-m-d H:i:s'), '<')
                    ->update(['status' => 'expired', 'updated_at' => date('Y-m-d H:i:s')]);
            } catch (\Throwable) {
            }
        });
    }

    /** Public helper other plugins can call (IR-LMS uses it). */
    public function userHasActiveSubscription(int $userId): bool
    {
        try {
            $row = Application::get()->make(Database::class)->first(
                "SELECT id FROM ir_membership_subscriptions WHERE user_id = ? AND status = 'active' AND (expires_at IS NULL OR expires_at > ?) LIMIT 1",
                [$userId, date('Y-m-d H:i:s')]
            );

            return $row !== null;
        } catch (\Throwable) {
            return false;
        }
    }

    // ── Public pages ─────────────────────────────────────────────

    private function plansPage(): Response
    {
        $db = Application::get()->make(Database::class);
        $plans = $db->select('SELECT * FROM ir_membership_plans WHERE is_active = 1 ORDER BY price');
        $csrf = Application::get()->make(Csrf::class)->field();
        /** @var Auth $auth */
        $auth = Application::get()->make(Auth::class);

        $cards = '';
        foreach ($plans as $p) {
            $button = !$auth->check()
                ? '<a href="/register" style="display:inline-block;background:#0d9488;color:#fff;padding:8px 18px;border-radius:10px;text-decoration:none">ثبت‌نام و انتخاب</a>'
                : '<form method="post" action="/member/subscribe">' . $csrf
                  . '<input type="hidden" name="plan_id" value="' . (int) $p['id'] . '">'
                  . '<button style="background:#0d9488;color:#fff;padding:8px 18px;border-radius:10px;border:none;cursor:pointer">انتخاب این پلن</button></form>';
            $cards .= '<article style="border:1px solid #e3e7f2;border-radius:14px;padding:20px;text-align:center">'
                . '<h3 style="margin:0 0 6px">' . e($p['title']) . '</h3>'
                . '<div style="font-size:22px;font-weight:700;margin:8px 0">' . ((int) $p['price'] === 0 ? 'رایگان' : number_format((int) $p['price']) . ' تومان') . '</div>'
                . '<p class="muted">' . (int) $p['period_days'] . ' روز</p>'
                . '<p>' . e((string) ($p['description'] ?? '')) . '</p>'
                . $button . '</article>';
        }

        return Response::html($this->shell('پلن‌های اشتراک',
            '<h1>پلن‌های اشتراک</h1>'
            . ($cards === '' ? '<p>پلن فعالی تعریف نشده است.</p>' : "<div style='display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:16px'>{$cards}</div>")));
    }

    private function registerForm(string $error = ''): Response
    {
        $sdk = IRJalali::for('ir-membership');
        if ($sdk->setting('enable_registration', '1') !== '1') {
            return Response::html($this->shell('ثبت‌نام', '<h1>ثبت‌نام در حال حاضر بسته است.</h1>'));
        }
        $csrf = Application::get()->make(Csrf::class)->field();
        $body = '<h1>ثبت‌نام</h1>'
            . ($error !== '' ? "<p style='color:#b91c1c'>{$error}</p>" : '')
            . '<form method="post" action="/register" style="max-width:440px;display:grid;gap:10px">' . $csrf
            . '<label>نام و نام خانوادگی *<input type="text" name="display_name" required maxlength="120"></label>'
            . '<label>نام کاربری *<input type="text" name="username" required dir="ltr" pattern="[a-zA-Z0-9_]{3,60}"></label>'
            . '<label>ایمیل *<input type="email" name="email" required dir="ltr"></label>'
            . '<label>شماره موبایل (اختیاری)<input type="text" name="mobile" dir="ltr"></label>'
            . '<label>گذرواژه *<input type="password" name="password" required minlength="8" autocomplete="new-password"></label>'
            . '<label>تکرار گذرواژه *<input type="password" name="password2" required minlength="8" autocomplete="new-password"></label>'
            . '<button style="background:#0d9488;color:#fff;padding:10px 20px;border-radius:10px;border:none">ایجاد حساب</button></form>'
            . '<p>حساب کاربری دارید؟ <a href="/member/login">ورود</a></p>';

        return Response::html($this->shell('ثبت‌نام', $body));
    }

    private function registerSubmit(Request $request): Response
    {
        $sdk = IRJalali::for('ir-membership');
        if ($sdk->setting('enable_registration', '1') !== '1') {
            return Response::redirect('/');
        }

        /** @var RateLimiter $limiter */
        $limiter = Application::get()->make(RateLimiter::class);
        $rlKey = 'register:' . $request->ip();
        if ($limiter->tooManyAttempts($rlKey, 5)) {
            return $this->registerForm('تعداد تلاش‌ها بیش از حد مجاز است؛ بعداً دوباره امتحان کنید.');
        }

        $displayName = trim($request->str('display_name'));
        $username = trim($request->str('username'));
        $email = strtolower(trim($request->str('email')));
        $mobile = trim($request->str('mobile'));
        $password = (string) $request->input('password', '');
        $password2 = (string) $request->input('password2', '');

        if (mb_strlen($displayName) < 3
            || !preg_match('/^[a-zA-Z0-9_]{3,60}$/', $username)
            || !filter_var($email, FILTER_VALIDATE_EMAIL)
            || strlen($password) < 8
            || $password !== $password2
        ) {
            $limiter->hit($rlKey, 900);

            return $this->registerForm('اطلاعات واردشده معتبر نیست. نام کاربری باید ۳ تا ۶۰ کاراکتر انگلیسی و گذرواژه حداقل ۸ کاراکتر باشد.');
        }

        $db = Application::get()->make(Database::class);
        if ($db->first('SELECT id FROM users WHERE username = ? OR email = ?', [$username, $email]) !== null) {
            $limiter->hit($rlKey, 900);

            return $this->registerForm('این نام کاربری یا ایمیل قبلاً ثبت شده است.');
        }

        $now = date('Y-m-d H:i:s');
        $userId = (int) $db->insert('users', [
            'uuid' => $this->uuid(),
            'username' => mb_substr($username, 0, 60),
            'email' => mb_substr($email, 0, 191),
            'password_hash' => Application::get()->make(Hasher::class)->hash($password),
            'display_name' => mb_substr($displayName, 0, 120),
            'mobile' => $mobile !== '' ? mb_substr($mobile, 0, 20) : null,
            'status' => 'active',
            'locale' => 'fa_IR',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $role = $db->first("SELECT id FROM roles WHERE slug = 'subscriber'");
        if ($role !== null) {
            $db->insert('user_roles', ['user_id' => $userId, 'role_id' => (int) $role['id']]);
        }

        Application::get()->make(Auth::class)->login($userId);

        return Response::redirect('/member');
    }

    private function loginForm(string $error = ''): Response
    {
        /** @var Auth $auth */
        $auth = Application::get()->make(Auth::class);
        if ($auth->check()) {
            return Response::redirect('/member');
        }
        $csrf = Application::get()->make(Csrf::class)->field();
        $body = '<h1>ورود اعضا</h1>'
            . ($error !== '' ? "<p style='color:#b91c1c'>{$error}</p>" : '')
            . '<form method="post" action="/member/login" style="max-width:400px;display:grid;gap:10px">' . $csrf
            . '<label>نام کاربری یا ایمیل *<input type="text" name="login" required dir="ltr"></label>'
            . '<label>گذرواژه *<input type="password" name="password" required autocomplete="current-password"></label>'
            . '<button style="background:#0d9488;color:#fff;padding:10px 20px;border-radius:10px;border:none">ورود</button></form>'
            . '<p>حساب ندارید؟ <a href="/register">ثبت‌نام</a></p>';

        return Response::html($this->shell('ورود اعضا', $body));
    }

    private function loginSubmit(Request $request): Response
    {
        /** @var RateLimiter $limiter */
        $limiter = Application::get()->make(RateLimiter::class);
        $rlKey = 'memberlogin:' . $request->ip();
        if ($limiter->tooManyAttempts($rlKey, 8)) {
            return $this->loginForm('تلاش بیش از حد؛ چند دقیقه صبر کنید.');
        }

        /** @var Auth $auth */
        $auth = Application::get()->make(Auth::class);
        if (!$auth->attempt($request->str('login'), (string) $request->input('password', ''))) {
            $limiter->hit($rlKey, 300);

            return $this->loginForm('نام کاربری یا گذرواژه اشتباه است.');
        }
        $limiter->clear($rlKey);

        return Response::redirect('/member');
    }

    private function logout(): Response
    {
        Application::get()->make(Auth::class)->logout();

        return Response::redirect('/');
    }

    private function dashboard(): Response
    {
        /** @var Auth $auth */
        $auth = Application::get()->make(Auth::class);
        if (!$auth->check()) {
            return Response::redirect('/member/login');
        }
        $user = (array) $auth->user();
        $db = Application::get()->make(Database::class);
        $sdk = IRJalali::for('ir-membership');
        $sub = $db->first(
            'SELECT s.*, p.title AS plan_title, p.period_days FROM ir_membership_subscriptions s JOIN ir_membership_plans p ON p.id = s.plan_id WHERE s.user_id = ? ORDER BY s.id DESC LIMIT 1',
            [(int) $auth->id()]
        );

        $hasAccess = $this->userHasActiveSubscription((int) $auth->id());
        $subHtml = $sub === null
            ? '<p>اشتراکی ندارید. <a href="/plans">مشاهده پلن‌ها</a></p>'
            : '<p>پلن: <b>' . e($sub['plan_title']) . '</b> — وضعیت: <b>' . e(self::SUB_STATUSES[$sub['status']] ?? (string) $sub['status']) . '</b><br>'
              . ($sub['expires_at'] !== null ? 'اعتبار تا: ' . e((string) $sub['expires_at']) : 'بدون انقضا') . '</p>';

        $membersContent = $hasAccess
            ? '<div style="background:#d1fae5;border-radius:12px;padding:16px">✅ شما به محتوای ویژه دسترسی دارید. این ناحیه نمونه‌ای از محتوای محافظت‌شده است.</div>'
            : '<div style="background:#fee2e2;border-radius:12px;padding:16px">برای دسترسی به محتوای ویژه، اشتراک فعال تهیه کنید. <a href="/plans">پلن‌ها</a></div>';

        $body = '<h1>ناحیه کاربری</h1>'
            . '<p>' . e((string) $sdk->setting('welcome_note', '')) . '</p>'
            . '<p>خوش آمدید، <b>' . e((string) ($user['display_name'] ?: $user['username'])) . '</b> '
            . '(<span dir="ltr">' . e((string) $user['email']) . '</span>)</p>'
            . '<h3>وضعیت اشتراک</h3>' . $subHtml
            . '<h3>محتوای ویژه</h3>' . $membersContent
            . '<form method="post" action="/member/logout" style="margin-top:18px">'
            . Application::get()->make(Csrf::class)->field()
            . '<button style="background:#e6ebf7;border:none;padding:8px 16px;border-radius:8px;cursor:pointer">خروج از حساب</button></form>';

        return Response::html($this->shell('ناحیه کاربری', $body));
    }

    private function subscribe(Request $request): Response
    {
        /** @var Auth $auth */
        $auth = Application::get()->make(Auth::class);
        if (!$auth->check()) {
            return Response::redirect('/member/login');
        }
        $db = Application::get()->make(Database::class);
        $plan = $db->first('SELECT * FROM ir_membership_plans WHERE id = ? AND is_active = 1', [(int) $request->input('plan_id', 0)]);
        if ($plan === null) {
            return Response::redirect('/plans');
        }

        $now = date('Y-m-d H:i:s');
        $free = (int) $plan['price'] === 0;
        $db->insert('ir_membership_subscriptions', [
            'user_id' => (int) $auth->id(),
            'plan_id' => (int) $plan['id'],
            'status' => $free ? 'active' : 'pending_payment',
            'started_at' => $free ? $now : null,
            'expires_at' => $free ? date('Y-m-d H:i:s', strtotime('+' . max(1, (int) $plan['period_days']) . ' days')) : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return Response::redirect('/member' . ($free ? '' : '?notice=pending'));
    }

    // ── Admin ────────────────────────────────────────────────────

    private function adminPage(Request $request): string
    {
        $db = Application::get()->make(Database::class);
        $csrf = Application::get()->make(Csrf::class)->field();
        $message = null;

        if ($request->isMethod('POST')) {
            $action = $request->str('action');
            if ($action === 'sub_status') {
                $status = $request->str('status');
                $id = (int) $request->str('id');
                if (isset(self::SUB_STATUSES[$status]) && $id > 0) {
                    $update = ['status' => $status, 'updated_at' => date('Y-m-d H:i:s')];
                    if ($status === 'active') {
                        $sub = $db->first('SELECT s.*, p.period_days FROM ir_membership_subscriptions s JOIN ir_membership_plans p ON p.id = s.plan_id WHERE s.id = ?', [$id]);
                        if ($sub !== null) {
                            $update['started_at'] = date('Y-m-d H:i:s');
                            $update['expires_at'] = date('Y-m-d H:i:s', strtotime('+' . max(1, (int) $sub['period_days']) . ' days'));
                        }
                    }
                    $db->table('ir_membership_subscriptions')->where('id', $id)->update($update);
                    $message = 'وضعیت اشتراک به‌روزرسانی شد.';
                }
            } elseif ($action === 'plan_save') {
                $title = trim($request->str('title'));
                $price = max(0, (int) $request->str('price'));
                $period = max(1, min(3650, (int) $request->str('period_days')));
                $isActive = $request->str('is_active') === '1' ? 1 : 0;
                if ($title !== '') {
                    $planId = (int) $request->str('plan_id');
                    if ($planId > 0) {
                        $db->table('ir_membership_plans')->where('id', $planId)->update([
                            'title' => mb_substr($title, 0, 120), 'price' => $price, 'period_days' => $period, 'is_active' => $isActive,
                            'description' => mb_substr($request->str('description'), 0, 500),
                        ]);
                    } else {
                        $slug = $this->slugify($title);
                        $i = 2;
                        while ($db->first('SELECT id FROM ir_membership_plans WHERE slug = ?', [$slug]) !== null) {
                            $slug = $this->slugify($title) . '-' . $i++;
                        }
                        $db->insert('ir_membership_plans', [
                            'title' => mb_substr($title, 0, 120), 'slug' => mb_substr($slug, 0, 120),
                            'description' => mb_substr($request->str('description'), 0, 500),
                            'price' => $price, 'period_days' => $period, 'is_active' => $isActive,
                        ]);
                    }
                    $message = 'پلن ذخیره شد.';
                }
            }
        }

        $subs = $db->select(
            'SELECT s.*, p.title AS plan_title, u.username, u.display_name, u.email
             FROM ir_membership_subscriptions s
             JOIN ir_membership_plans p ON p.id = s.plan_id
             JOIN users u ON u.id = s.user_id
             ORDER BY s.id DESC LIMIT 100'
        );
        $plans = $db->select('SELECT * FROM ir_membership_plans ORDER BY id');

        ob_start(); ?>
        <h2 style="margin-top:0">👥 اشتراک‌ها و پلن‌ها</h2>
        <?php if ($message !== null): ?><p class="flash-ok"><?= e($message) ?></p><?php endif; ?>

        <div class="panel" style="border:1px solid var(--border);margin-bottom:16px">
          <h3>اشتراک‌ها</h3>
          <?php if ($subs === []): ?><p class="muted">اشتراکی ثبت نشده است.</p><?php else: ?>
            <table class="tbl">
              <tr><th>کاربر</th><th>پلن</th><th>وضعیت</th><th>اعتبار تا</th><th>تغییر وضعیت</th></tr>
              <?php foreach ($subs as $s): ?>
                <tr>
                  <td><?= e((string) ($s['display_name'] ?: $s['username'])) ?><br><span class="muted" style="font-size:11px" dir="ltr"><?= e($s['email']) ?></span></td>
                  <td><?= e($s['plan_title']) ?></td>
                  <td><span class="badge <?= $s['status'] === 'active' ? 'green' : ($s['status'] === 'expired' || $s['status'] === 'cancelled' ? 'red' : '') ?>"><?= e(self::SUB_STATUSES[$s['status']] ?? (string) $s['status']) ?></span></td>
                  <td class="muted" style="font-size:11px"><?= e((string) ($s['expires_at'] ?? '—')) ?></td>
                  <td>
                    <form method="post" action="/admin/plugin/ir-membership--subscriptions" style="display:flex;gap:4px">
                      <?= $csrf ?><input type="hidden" name="action" value="sub_status"><input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                      <select name="status">
                        <?php foreach (self::SUB_STATUSES as $key => $label): ?>
                          <option value="<?= e($key) ?>" <?= $s['status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                      </select>
                      <button class="btn small" type="submit">ثبت</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </table>
          <?php endif; ?>
        </div>

        <div class="panel" style="border:1px solid var(--border)">
          <h3>پلن‌ها</h3>
          <table class="tbl">
            <tr><th>عنوان</th><th>قیمت (تومان)</th><th>مدت (روز)</th><th>وضعیت</th></tr>
            <?php foreach ($plans as $p): ?>
              <tr>
                <td><?= e($p['title']) ?></td>
                <td dir="ltr"><?= number_format((int) $p['price']) ?></td>
                <td><?= (int) $p['period_days'] ?></td>
                <td><span class="badge <?= $p['is_active'] ? 'green' : 'red' ?>"><?= $p['is_active'] ? 'فعال' : 'غیرفعال' ?></span></td>
              </tr>
            <?php endforeach; ?>
          </table>
          <h3>پلن جدید</h3>
          <form method="post" action="/admin/plugin/ir-membership--subscriptions">
            <?= $csrf ?><input type="hidden" name="action" value="plan_save">
            <div class="grid2">
              <div>
                <label>عنوان *</label><input type="text" name="title" required>
                <label>توضیح</label><input type="text" name="description">
              </div>
              <div>
                <label>قیمت (تومان، 0 = رایگان)</label><input type="number" name="price" min="0" value="0">
                <label>مدت (روز)</label><input type="number" name="period_days" min="1" value="30">
              </div>
            </div>
            <label style="margin-top:8px"><input type="checkbox" name="is_active" value="1" checked style="width:auto"> فعال</label>
            <button class="btn small" type="submit" style="margin-top:8px">ذخیره پلن</button>
          </form>
        </div>
        <?php return (string) ob_get_clean();
    }

    // ── Helpers ──────────────────────────────────────────────────

    private function uuid(): string
    {
        $b = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
        $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }

    private function slugify(string $text): string
    {
        $text = trim(mb_strtolower($text));
        $text = preg_replace('/[^a-z0-9\x{0600}-\x{06FF}]+/u', '-', $text) ?? '';
        $text = trim($text, '-');

        return $text !== '' ? mb_substr($text, 0, 100) : 'plan-' . bin2hex(random_bytes(3));
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
            . 'header{background:#fff;border-bottom:1px solid #e3e7f2;padding:12px 24px}header a{text-decoration:none;font-weight:700}'
            . '</style></head><body><header><a href="/member">👤 عضویت</a> · <a href="/plans">پلن‌ها</a> · <a href="/">بازگشت به سایت</a></header>'
            . '<main>' . $body . '</main></body></html>';
    }
}
