<?php

declare(strict_types=1);

namespace IRJalali\Plugins\IrBooking;

use IRJalali\App\Middleware\StartSession;
use IRJalali\App\Middleware\VerifyCsrf;
use IRJalali\Core\Database\Database;
use IRJalali\Core\Date\DateService;
use IRJalali\Core\Http\Request;
use IRJalali\Core\Http\Response;
use IRJalali\Core\Kernel\Application;
use IRJalali\Core\Plugins\PluginContext;
use IRJalali\Core\Plugins\PluginServiceProvider;
use IRJalali\Core\Sdk\IRJalali;
use IRJalali\Core\Security\Csrf;

/**
 * IR-Booking (Part 3 §9): online appointment booking. Services live in the
 * core CPT engine; slots are generated from working-hours settings, conflicts
 * are prevented in the DB, and admins manage reservations from one page.
 */
final class Plugin extends PluginServiceProvider
{
    private const STATUSES = ['pending' => 'در انتظار تأیید', 'confirmed' => 'تأییدشده', 'done' => 'انجام‌شده', 'cancelled' => 'لغوشده'];
    private const WEEKDAYS = ['', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه', 'شنبه', 'یکشنبه'];

    public function boot(PluginContext $context): void
    {
        $sdk = IRJalali::for('ir-booking');

        $sdk->registerPostType('service', [
            'name' => 'خدمت',
            'icon' => '📅',
            'supports' => ['title', 'editor', 'excerpt', 'fields'],
            'settings' => ['archive' => false, 'single' => true, 'rest_api' => true],
        ]);

        foreach ([
            ['key' => 'duration_min', 'label' => 'مدت خدمت (دقیقه)', 'type' => 'number', 'settings' => ['required' => true, 'min' => 5]],
            ['key' => 'price', 'label' => 'هزینه (تومان)', 'type' => 'number', 'settings' => ['required' => false, 'min' => 0]],
        ] as $field) {
            $sdk->registerField($field + ['location' => [['param' => 'post_type', 'value' => 'service']]]);
        }

        $sdk->registerSettings([
            ['key' => 'work_start', 'type' => 'text', 'label' => 'شروع ساعت کاری (مثل 09:00)', 'default' => '09:00'],
            ['key' => 'work_end', 'type' => 'text', 'label' => 'پایان ساعت کاری (مثل 18:00)', 'default' => '18:00'],
            ['key' => 'days_off', 'type' => 'text', 'label' => 'روزهای تعطیل (1=دوشنبه … 7=یکشنبه، با کاما)', 'default' => '5'],
            ['key' => 'days_ahead', 'type' => 'number', 'label' => 'بازه رزرو (روز آینده)', 'default' => '21'],
        ]);

        $sdk->registerAdminPage('appointments', 'رزروها', fn (Request $request): string => $this->adminPage($request), '📅');

        $sdk->registerRoute(['GET'], '/booking', fn (Request $request): Response => $this->servicesPage(), [StartSession::class]);
        $sdk->registerRoute(['GET'], '/booking/{service_id}/slots', fn (Request $request): Response => $this->slots($request), [StartSession::class]);
        $sdk->registerRoute(['GET'], '/booking/{service_id}', fn (Request $request): Response => $this->slotPicker($request), [StartSession::class]);
        $sdk->registerRoute(['POST'], '/booking/reserve', fn (Request $request): Response => $this->reserve($request), [StartSession::class, VerifyCsrf::class]);
        $sdk->registerRoute(['GET'], '/booking/done/{id}', fn (Request $request): Response => $this->confirmation($request), [StartSession::class]);

        $sdk->registerApiEndpoint('GET', 'booking/appointments', function (): Response {
            $rows = Application::get()->make(Database::class)
                ->select('SELECT id, service_title, customer_name, appoint_date, time_slot, status FROM ir_booking_appointments ORDER BY id DESC LIMIT 100');

            return Response::json(['ok' => true, 'data' => $rows]);
        });

        // Auto-close yesterday's confirmed appointments.
        $sdk->cron('close_past', 'بستن رزروهای گذشته', 'daily', function (): void {
            try {
                Application::get()->make(Database::class)->table('ir_booking_appointments')
                    ->where('status', 'confirmed')
                    ->where('appoint_date', date('Y-m-d'), '<')
                    ->update(['status' => 'done']);
            } catch (\Throwable) {
            }
        });
    }

    // ── Public flow ──────────────────────────────────────────────

    private function servicesPage(): Response
    {
        $db = Application::get()->make(Database::class);
        $services = $this->services($db);

        $cards = '';
        foreach ($services as $s) {
            $cards .= '<article style="border:1px solid #e3e7f2;border-radius:14px;padding:16px">'
                . '<h3 style="margin:0 0 6px">' . e($s['title']) . '</h3>'
                . '<p class="muted" style="margin:0 0 10px">مدت: ' . (int) $s['duration_min'] . ' دقیقه'
                . ($s['price'] > 0 ? ' — هزینه: ' . number_format($s['price']) . ' تومان' : '') . '</p>'
                . '<a href="/booking/' . (int) $s['id'] . '" style="display:inline-block;background:#0d9488;color:#fff;padding:8px 18px;border-radius:10px;text-decoration:none">رزرو وقت</a>'
                . '</article>';
        }

        return Response::html($this->shell('رزرواسیون',
            '<h1>📅 انتخاب خدمت</h1>'
            . ($cards === '' ? '<p>هنوز خدمتی تعریف نشده است.</p>' : "<div style='display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:16px'>{$cards}</div>")));
    }

    private function slotPicker(Request $request): Response
    {
        $db = Application::get()->make(Database::class);
        $serviceId = (int) $request->route('service_id', 0);
        $service = $this->findService($db, $serviceId);
        if ($service === null) {
            return Response::html($this->shell('رزرواسیون', '<h1>خدمت پیدا نشد</h1><p><a href="/booking">بازگشت</a></p>'), 404);
        }

        $sdk = IRJalali::for('ir-booking');
        $daysAhead = max(1, min(90, (int) $sdk->setting('days_ahead', '21')));
        $daysOff = array_filter(array_map('intval', explode(',', (string) $sdk->setting('days_off', '5'))));
        /** @var DateService $dates */
        $dates = Application::get()->make(DateService::class);
        $csrf = Application::get()->make(Csrf::class)->field();

        $dayOptions = '';
        for ($i = 1; $i <= $daysAhead; $i++) {
            $ts = strtotime("+{$i} days");
            $weekday = (int) date('N', $ts);
            if (in_array($weekday, $daysOff, true)) {
                continue;
            }
            $ymd = date('Y-m-d', $ts);
            $label = self::WEEKDAYS[$weekday] . ' ' . $dates->formatDate($ymd . ' 00:00:00');
            $dayOptions .= "<option value=\"{$ymd}\">" . e($label) . '</option>';
        }
        if ($dayOptions === '') {
            return Response::html($this->shell('رزرواسیون', '<h1>روز قابل رزروی در بازه تنظیمات وجود ندارد.</h1><p><a href="/booking">بازگشت</a></p>'));
        }

        $body = '<h1>رزرو «' . e($service['title']) . '»</h1>'
            . '<p class="muted">مدت خدمت: ' . (int) $service['duration_min'] . ' دقیقه'
            . ($service['price'] > 0 ? ' — هزینه: ' . number_format($service['price']) . ' تومان' : '') . '</p>'
            . '<form method="post" action="/booking/reserve" style="max-width:480px;display:grid;gap:10px">'
            . $csrf
            . '<input type="hidden" name="service_id" value="' . (int) $service['id'] . '">'
            . '<label>تاریخ مراجعه *<select name="date" id="bk-date" required>' . $dayOptions . '</select></label>'
            . '<label>ساعت *<select name="slot" id="bk-slot" required data-service="' . (int) $service['id'] . '"><option value="">ابتدا تاریخ را انتخاب کنید</option></select></label>'
            . '<label>نام و نام خانوادگی *<input type="text" name="name" required maxlength="120"></label>'
            . '<label>شماره موبایل *<input type="text" name="phone" required dir="ltr" pattern="09[0-9]{9}" placeholder="09xxxxxxxxx"></label>'
            . '<label>ایمیل (اختیاری)<input type="email" name="email" dir="ltr"></label>'
            . '<label>توضیحات (اختیاری)<input type="text" name="note" maxlength="500"></label>'
            . '<button class="btn" style="background:#0d9488;color:#fff;padding:10px 20px;border-radius:10px;border:none">ثبت رزرو</button>'
            . '</form>'
            . '<script>document.getElementById("bk-date").addEventListener("change",function(){loadSlots(this.value)});'
            . 'function loadSlots(d){var s=document.getElementById("bk-slot");s.innerHTML="<option>در حال بارگذاری…</option>";'
            . 'fetch("/booking/"+document.getElementById("bk-slot").dataset.service+"/slots?date="+encodeURIComponent(d)).then(function(r){return r.json()}).then(function(j){'
            . 's.innerHTML="";if(!j.data||!j.data.length){s.innerHTML="<option value=\\\"\\\">وقت آزادی نیست</option>";return}'
            . 'j.data.forEach(function(t){var o=document.createElement("option");o.value=t;o.textContent=t;s.appendChild(o)})}).catch(function(){s.innerHTML="<option value=\\\"\\\">خطا در دریافت</option>"})}</script>';

        return Response::html($this->shell('رزرو وقت', $body));
    }

    /** AJAX: available slots for a service on a date. */
    private function slots(Request $request): Response
    {
        $db = Application::get()->make(Database::class);
        $serviceId = (int) $request->route('service_id', 0);
        $service = $this->findService($db, $serviceId);
        $date = $request->str('date');
        if ($service === null || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return Response::json(['ok' => false, 'error' => 'invalid'], 400);
        }

        return Response::json(['ok' => true, 'data' => $this->availableSlots($db, $service, $date)]);
    }

    private function reserve(Request $request): Response
    {
        $db = Application::get()->make(Database::class);
        $serviceId = (int) $request->input('service_id', 0);
        $service = $this->findService($db, $serviceId);
        $date = $request->str('date');
        $slot = $request->str('slot');
        $name = trim($request->str('name'));
        $phone = trim($request->str('phone'));
        $email = trim($request->str('email'));
        $note = trim($request->str('note'));

        if ($service === null
            || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
            || !preg_match('/^\d{2}:\d{2}$/', $slot)
            || mb_strlen($name) < 3
            || !preg_match('/^09[0-9]{9}$/', $phone)
            || ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL))
        ) {
            return Response::redirect('/booking/' . $serviceId . '?error=validation');
        }

        // The requested slot must still be free (race-safe enough for shared hosting).
        if (!in_array($slot, $this->availableSlots($db, $service, $date), true)) {
            return Response::redirect('/booking/' . $serviceId . '?error=slot');
        }

        $id = (int) $db->insert('ir_booking_appointments', [
            'service_id' => $serviceId,
            'service_title' => mb_substr($service['title'], 0, 255),
            'customer_name' => mb_substr($name, 0, 120),
            'customer_phone' => mb_substr($phone, 0, 40),
            'customer_email' => $email !== '' ? mb_substr($email, 0, 191) : null,
            'appoint_date' => $date,
            'time_slot' => $slot,
            'status' => 'pending',
            'note' => $note !== '' ? mb_substr($note, 0, 500) : null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return Response::redirect('/booking/done/' . $id);
    }

    private function confirmation(Request $request): Response
    {
        $db = Application::get()->make(Database::class);
        $row = $db->first('SELECT * FROM ir_booking_appointments WHERE id = ?', [(int) $request->route('id', 0)]);
        if ($row === null) {
            return Response::html($this->shell('رزرواسیون', '<h1>رزرو پیدا نشد</h1>'), 404);
        }
        /** @var DateService $dates */
        $dates = Application::get()->make(DateService::class);
        $body = '<h1>✅ رزرو شما ثبت شد</h1>'
            . '<p>شماره رزرو: <b>#' . (int) $row['id'] . '</b> — وضعیت: <b>' . e(self::STATUSES[$row['status']] ?? (string) $row['status']) . '</b></p>'
            . '<p>خدمت: ' . e($row['service_title']) . '<br>'
            . 'تاریخ: ' . e($dates->formatDate($row['appoint_date'] . ' 00:00:00')) . ' ساعت ' . e($row['time_slot']) . '<br>'
            . 'به نام: ' . e($row['customer_name']) . '</p>'
            . '<p class="muted">پس از تأیید، با شماره ' . e($row['customer_phone']) . ' تماس گرفته می‌شود.</p>'
            . '<p><a href="/booking">رزرو جدید</a></p>';

        return Response::html($this->shell('تأیید رزرو', $body));
    }

    // ── Admin ────────────────────────────────────────────────────

    private function adminPage(Request $request): string
    {
        $db = Application::get()->make(Database::class);
        $sdk = IRJalali::for('ir-booking');
        $csrf = Application::get()->make(Csrf::class)->field();
        $message = null;

        if ($request->isMethod('POST')) {
            if ($request->str('action') === 'save') {
                foreach (['work_start', 'work_end', 'days_off', 'days_ahead'] as $key) {
                    $sdk->saveSetting($key, mb_substr($request->str($key), 0, 100));
                }
                $message = 'تنظیمات ذخیره شد.';
            } elseif ($request->str('action') === 'status') {
                $status = $request->str('status');
                if (isset(self::STATUSES[$status])) {
                    $db->table('ir_booking_appointments')->where('id', (int) $request->str('id'))->update(['status' => $status]);
                    $message = 'وضعیت رزرو به‌روزرسانی شد.';
                }
            }
        }

        /** @var DateService $dates */
        $dates = Application::get()->make(DateService::class);
        $rows = $db->select('SELECT * FROM ir_booking_appointments ORDER BY appoint_date DESC, time_slot DESC LIMIT 150');

        ob_start(); ?>
        <h2 style="margin-top:0">📅 مدیریت رزروها</h2>
        <?php if ($message !== null): ?><p class="flash-ok"><?= e($message) ?></p><?php endif; ?>

        <div class="panel" style="border:1px solid var(--border);margin-bottom:16px">
          <h3>رزروهای ثبت‌شده</h3>
          <?php if ($rows === []): ?><p class="muted">رزروی ثبت نشده است.</p><?php else: ?>
            <table class="tbl">
              <tr><th>#</th><th>خدمت</th><th>مشتری</th><th>تاریخ</th><th>ساعت</th><th>وضعیت</th><th>تغییر وضعیت</th></tr>
              <?php foreach ($rows as $r): ?>
                <tr>
                  <td><?= (int) $r['id'] ?></td>
                  <td><?= e($r['service_title']) ?></td>
                  <td><?= e($r['customer_name']) ?><br><span class="muted" dir="ltr" style="font-size:11px"><?= e($r['customer_phone']) ?></span></td>
                  <td><?= e($dates->formatDate($r['appoint_date'] . ' 00:00:00')) ?></td>
                  <td dir="ltr"><?= e($r['time_slot']) ?></td>
                  <td><span class="badge <?= $r['status'] === 'confirmed' ? 'green' : ($r['status'] === 'cancelled' ? 'red' : '') ?>"><?= e(self::STATUSES[$r['status']] ?? (string) $r['status']) ?></span></td>
                  <td>
                    <form method="post" action="/admin/plugin/ir-booking--appointments" style="display:flex;gap:4px">
                      <?= $csrf ?><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                      <select name="status">
                        <?php foreach (self::STATUSES as $key => $label): ?>
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
        </div>

        <form method="post" action="/admin/plugin/ir-booking--appointments" class="panel" style="border:1px solid var(--border)">
          <?= $csrf ?><input type="hidden" name="action" value="save">
          <h3>تنظیمات ساعات کاری</h3>
          <div class="grid2">
            <div>
              <label>شروع ساعت کاری</label><input type="text" name="work_start" dir="ltr" value="<?= e($sdk->setting('work_start', '09:00')) ?>">
              <label>پایان ساعت کاری</label><input type="text" name="work_end" dir="ltr" value="<?= e($sdk->setting('work_end', '18:00')) ?>">
            </div>
            <div>
              <label>روزهای تعطیل (1=دوشنبه … 7=یکشنبه)</label><input type="text" name="days_off" dir="ltr" value="<?= e($sdk->setting('days_off', '5')) ?>">
              <label>بازه رزرو (روز آینده)</label><input type="number" name="days_ahead" min="1" max="90" value="<?= e($sdk->setting('days_ahead', '21')) ?>">
            </div>
          </div>
          <button class="btn small" type="submit">ذخیره تنظیمات</button>
        </form>
        <?php return (string) ob_get_clean();
    }

    // ── Helpers ──────────────────────────────────────────────────

    /** @return list<array{id:int,title:string,duration_min:int,price:int}> */
    private function services(Database $db): array
    {
        $rows = $db->select(
            "SELECT id, title FROM posts WHERE post_type = 'service' AND status = 'published' AND deleted_at IS NULL ORDER BY published_at DESC, id DESC LIMIT 100"
        );
        $out = [];
        foreach ($rows as $row) {
            $meta = $this->meta($db, (int) $row['id']);
            $duration = (int) ($meta['duration_min'] ?? 30);
            $out[] = ['id' => (int) $row['id'], 'title' => (string) $row['title'], 'duration_min' => max(5, $duration), 'price' => (int) ($meta['price'] ?? 0)];
        }

        return $out;
    }

    /** @return array{id:int,title:string,duration_min:int,price:int}|null */
    private function findService(Database $db, int $id): ?array
    {
        foreach ($this->services($db) as $s) {
            if ($s['id'] === $id) {
                return $s;
            }
        }

        return null;
    }

    /** @return list<string> e.g. ['09:00','09:30',…] */
    private function availableSlots(Database $db, array $service, string $date): array
    {
        $sdk = IRJalali::for('ir-booking');
        $start = $sdk->setting('work_start', '09:00');
        $end = $sdk->setting('work_end', '18:00');
        if (!preg_match('/^([01]?\d|2[0-3]):[0-5]\d$/', (string) $start) || !preg_match('/^([01]?\d|2[0-3]):[0-5]\d$/', (string) $end)) {
            return [];
        }
        $step = max(5, (int) $service['duration_min']);
        $cursor = strtotime("{$date} {$start}");
        $close = strtotime("{$date} {$end}");
        $now = time();

        $taken = [];
        $rows = $db->select(
            "SELECT time_slot FROM ir_booking_appointments WHERE service_id = ? AND appoint_date = ? AND status IN ('pending','confirmed')",
            [(int) $service['id'], $date]
        );
        foreach ($rows as $row) {
            $taken[] = (string) $row['time_slot'];
        }

        $slots = [];
        while ($cursor !== false && $cursor + $step * 60 <= $close) {
            $label = date('H:i', $cursor);
            if ($cursor > $now && !in_array($label, $taken, true)) {
                $slots[] = $label;
            }
            $cursor += $step * 60;
        }

        return $slots;
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
            . 'label{font-size:13px;display:grid;gap:4px}.btn{border:none;border-radius:8px;padding:6px 14px;cursor:pointer}'
            . '.tbl td,.tbl th{border:1px solid #e3e7f2;padding:8px;text-align:right}.badge{display:inline-block;padding:2px 10px;border-radius:99px;background:#e6ebf7;font-size:12px}'
            . '.badge.green{background:#d1fae5;color:#065f46}.badge.red{background:#fee2e2;color:#991b1b}.muted{color:#7a84a6}'
            . 'header{background:#fff;border-bottom:1px solid #e3e7f2;padding:12px 24px}header a{text-decoration:none;font-weight:700}'
            . '</style></head><body><header><a href="/booking">📅 رزرواسیون</a> · <a href="/">بازگشت به سایت</a></header>'
            . '<main>' . $body . '</main></body></html>';
    }
}
