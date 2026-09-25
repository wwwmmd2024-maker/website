# IR-Jalali — Architecture (Part 1)

## ۱. اصول

1. **Core = Platform.** هیچ فیچر اختصاصی، Core را تغییر نمی‌دهد.
2. **توسعه فقط از Extension Points:** Hooks، Filters، Events، Contracts، Services، REST API.
3. **لایه‌بندی سخت‌گیرانه:** Controller → Service → Repository → Database.
   Controller هرگز کوئری پیچیده نمی‌نویسد.
4. **Production بدون بیلد:** صفر وابستگی Composer/NPM در ران‌تایم؛ اجرا روی Shared Hosting.
5. **امنیت پیش‌فرض:** همه ورودی‌ها اعتبارسنجی، همه خروجی‌ها escape، همه کوئری‌ها prepared.

## ۲. چرخه درخواست

```
Browser
  → public/index.php (front controller)
  → Autoloader (PSR-4 بدون Composer)
  → Application::boot (.env → Config → Container → Services)
  → app/bootstrap.php (helpers + locale/timezone دیتابیسی + جاب‌های کرون)
  → app/routes.php (ثبت روت‌ها)
  → Router::dispatch (تطبیق مسیر + زنجیره Middleware + Controller)
  → Response::send (+ Security Headers)
```

## ۳. لایه‌ها و قراردادها

| لایه | مسیر | قرارداد کلیدی |
|---|---|---|
| DI Container | `core/Kernel/Container.php` | `bind/singleton/make` + auto-wiring |
| Config | `core/Config/*` | `Env::get()` + `Config::get('a.b')` |
| HTTP | `core/Http/*` | `Request`، `Response`، `Router`، `Middleware::handle()` |
| Database | `core/Database/*` | `Connection`، `Database`، `QueryBuilder`، `Schema\Blueprint`، `Migration::up/down` |
| Security | `core/Security/*` | `Csrf`، `Hasher`، `RateLimiter`، `Sanitize::*`، `Session` |
| Date | `core/Date/*` | `CalendarInterface`، `DateService` (تک‌نقطه ورود تاریخ) |
| i18n | `core/Translation/Translator` | فایل‌های `languages/*.php`، `get($key)` |
| Events/Hooks | `core/Events/*`، `core/Hooks/*` | `listen/dispatch` + `addAction/doAction/addFilter/applyFilters` |
| Cache/Queue | `core/Cache/*`، `core/Queue/*` | `CacheInterface` (فایل؛ Redis آینده) |
| Scheduler | `core/Scheduler/Scheduler` | تسک‌ها در DB + هندلرها از فیلتر `scheduler.handlers` |
| Auth/RBAC | `core/Auth/Auth` | `attempt/login/check/can/hasRole` |
| View | `core/View/View` | رندر PHP با layout + هلپر `e()` |

## ۴. قراردادهای توسعه (برای Part 2 — ثابت و نهایی)

- **Plugin:** `plugins/{slug}/plugin.json` + کلاس `IRJalali\Plugins\{Slug}\Plugin` با متدهای
  `activate()/deactivate()`؛ هوک‌ها تنها راه اتصال به Core.
- **Theme:** `themes/{slug}/theme.json` + `templates/*.php`؛ رندر از `ThemeManager` (Part 2).
- **Module:** `modules/{slug}/module.json` + `routes.php` + `Migrations/*`؛ فعال‌سازی per Website-Mode.
- **Block:** رکورد `blocks` + `render(array $data): string`؛ نمونه‌ها در `block_instances`.
- **Widget:** رکورد `widgets` + سایدبارها در `widget_instances`.
- **CPT:** رکورد `post_types` + `supports`؛ خروجی خودکار در API با `settings.api=true`.
- **Custom Fields:** گروه در `custom_field_groups` + فیلدها در `custom_fields`؛ مقادیر در `post_meta`.

## ۵. Website Mode

`options.website_mode ∈ {general,business,blog,magazine,portfolio,booking,membership,education,ecommerce,marketplace,directory,custom}`

- Mode فقط **داده** است. هیچ شاخه‌زنی بر اساس Mode داخل Core وجود ندارد.
- ماژول‌ها خودشان را به Modeها اعلام می‌کنند (`module.json → modes: [...]`) و
  `ModuleManager` (Part 2) آن‌ها را فعال/نمایان می‌کند.
- تغییر Mode هرگز جدول نمی‌سازد و کد را تغییر نمی‌دهد.

## ۶. تقویم و تاریخ

- **ذخیره‌سازی همیشه Gregorian** (`Y-m-d H:i:s` در timezone اپ).
- **نمایش همیشه از `DateService`** با تقویم فعال (`jalali` پیش‌فرض).
- `CalendarInterface` برای `gregorian` و `jalali` پیاده شده؛ `hijri` آینده همین قرارداد را می‌گیرد.
- الگوریتم جلالی Pure-PHP است و در `tests/smoke.php` با Intl راستی‌آزمایی می‌شود.

## ۷. کش و صف

- کش: `FileCache` (اتمیک، TTL، clearPrefix). افزودن Redis = یک کلاس جدید پشت `CacheInterface`.
- صف: durable روی `scheduled_tasks` با `schedule='once'`؛ ورکر همان `cron.php` است.

## ۸. خطاها و لاگ

- کانال‌های فایل: `app, admin, security, database, scheduler, api, install`.
- جدول‌های DB: `logs, audit_logs, security_logs` + مشاهده‌گر در `/admin/logs`.
