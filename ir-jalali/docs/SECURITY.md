# IR-Jalali — Security Architecture (M1)

## ۱. لایه‌های دفاعی پیاده‌سازی‌شده

| تهدید | دفاع | محل کد |
|---|---|---|
| SQL Injection | PDO + Prepared Statements همه‌جا؛ `EMULATE_PREPARES=false`؛ QueryBuilder با پارامتر بایند | `core/Database/*` |
| CSRF | توکن per-session + میدل‌ویر `VerifyCsrf` روی همه POST | `core/Security/Csrf.php` |
| XSS (Reflected/Stored) | `e()`/`Sanitize::html` در همه خروجی‌ها؛ `Sanitize::richText` برای محتوای غنی؛ اعتبارسنجی SVG | `core/Security/Sanitize.php` |
| Brute Force | RateLimiter فایل‌محور روی لاگین (۵ تلاش/۵ دقیقه) + تأخیر تصادفی + لاگ امنیتی | `AuthController` |
| Session Fixation/Hijack | strict_mode، کوکی HttpOnly+SameSite(+Secure روی HTTPS)، چرخش ID | `core/Security/Session.php` |
| Path Traversal | `Filesystem` زندانی (jail) + `Sanitize::filename` | `core/Filesystem/*` |
| آپلود مخرب | allowlist پسوند + MIME sniff واقعی + اعتبارسنجی محتوای تصویر + SVG امن + نام تصادفی + `php_flag engine off` | `app/Services/MediaService.php` |
| دسترسی غیرمجاز | RBAC واقعی: `Auth::can()` در کنترلرها + `Authenticate` + صفحه 403 | `core/Auth/*` |
| Clickjacking/MIME | `X-Frame-Options: SAMEORIGIN`، `nosniff`، CSP سخت‌گیرانه در ادمین/نصب | `core/Security/SecurityHeaders.php` |
| افشای Secret | همه secrets در `.env` (chmod 0600)؛ هیچ secret در سورس/لاگ | `config/*`، `.env.example` |
| User Enumeration | پیام یکسان ورود + تأخیر زمانی یکنواخت | `AuthController::login` |

## ۲. رمزنگاری و توکن‌ها

- گذرواژه: `PASSWORD_ARGON2ID` (fallback به Bcrypt) + rehash خودکار.
- توکن CSRF/API: `random_bytes` + مقایسه `hash_equals`.
- توکن‌های API با SHA-256 هش‌شده ذخیره می‌شوند (متن خام فقط یک‌بار نمایش).

## ۳. حسابرسی

- `security_logs`: login.success/failed/throttled (+ IP و User-Agent).
- `audit_logs`: تغییرات مدیریتی با before/after (مثل settings.update).
- فایل‌های روزانه per-channel + مشاهده‌گر `/admin/logs`.

## ۴. چک‌لیست استقرار امن (cPanel)

- [ ] DocumentRoot روی `public/` باشد (نه ریشه پروژه).
- [ ] `.env` خارج از دسترس وب + دسترسی 600.
- [ ] `APP_DEBUG=false` و `APP_ENV=production`.
- [ ] HTTPS فعال (کوکی Secure خودکار می‌شود).
- [ ] کرون `cron.php` هر دقیقه.
- [ ] بکاپ روزانه دیتابیس + `storage/uploads`.
- [ ] `ADMIN_PATH` پیش‌فرض عوض شود (M2: پشتیبانی کامل مسیر سفارشی ادمین).

## ۵. بدهی امنیتی شناخته‌شده برای M2

- 2FA (TOTP) برای مدیر.
- درایور Database Sessions + لیست نشست‌های فعال + ابطال.
- محدودسازی IP ادمین + Web Application Firewall rules پیشنهادی.
- اسکن دوره‌ای فایل‌ها (integrity check هسته).
