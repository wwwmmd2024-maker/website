# IR-Jalali — Plugin API (v1)

افزونه‌ها تنها راه افزودن قابلیت جدید به سامانه هستند؛ **هسته هرگز برای فیچر جدید تغییر نمی‌کند.**

## ساختار پوشه

```
plugins/MyPlugin/
├── plugin.json            # مانیفست (الزامی)
├── Plugin.php             # کلاس اصلی: IRJalali\Plugins\MyPlugin\Plugin
├── Migrations/            # Version_X_Y_Z_Name.php (اختیاری)
└── ...                    # هر کلاس دیگر (autoload PSR-4 از ریشه پوشه)
```

## مانیفست `plugin.json`

```json
{
  "slug": "my-plugin",
  "name": "نام نمایشی",
  "version": "1.0.0",
  "description": "توضیح کوتاه",
  "author": "نام شما",
  "license": "MIT",
  "category": "general",
  "requires": { "core": ">=1.1.0", "php": ">=8.2" },
  "api": "v1",
  "dependencies": ["ir-smtp"],
  "capabilities": ["admin.access", "database.read", "database.write"],
  "autoload": { "psr-4": { "IRJalali\\Plugins\\MyPlugin\\": "" } }
}
```

### قابلیت‌ها (Capabilities)

هر افزونه فقط توانایی‌هایی را دارد که در `plugin.json` اعلام کرده و مدیر هنگام فعال‌سازی اعطا کند:

| قابلیت | معنا |
|---|---|
| `filesystem.read` / `filesystem.write` | خواندن/نوشتن فایل در مسیرهای مجاز |
| `database.read` / `database.write` | کوئری فقط‌خوان / نوشتن (از مسیر `IRJalali::query()` و `$context->db()`) |
| `network.request` | HTTP خروجی با محافظ SSRF (`$context->http()`) |
| `admin.access` | ثبت صفحات مدیریت |
| `users.read` / `users.write` | خواندن/ایجاد کاربر |
| `settings.read` / `settings.write` | خواندن/ذخیره تنظیمات افزونه |

## چرخه حیات

```
نصب (آپلود/بازارچه/اسکن) → بررسی امنیتی (اسکنر + php -l) →
فعال‌سازی: مایگریشن‌ها → ()activate → ()boot (هر درخواست) →
غیرفعال‌سازی → حذف: ()uninstall + DROP جدول‌ها
```

کلاس اصلی:

```php
namespace IRJalali\Plugins\MyPlugin;

use IRJalali\Core\Plugins\PluginContext;
use IRJalali\Core\Plugins\PluginServiceProvider;
use IRJalali\Core\Sdk\IRJalali;

final class Plugin extends PluginServiceProvider
{
    public function boot(PluginContext $context): void
    {
        $sdk = IRJalali::for('my-plugin');
        // ثبت‌ها این‌جا…
    }

    public function activate(): void {}     // یک‌بار هنگام فعال‌سازی
    public function deactivate(): void {}
    public function uninstall(): void {}    // پاک‌سازی هنگام حذف
}
```

## سطوح ثبت (همه از `$sdk`)

| متد | کاربرد |
|---|---|
| `registerPostType(slug, args)` | نوع محتوای سفارشی (در جدول `post_types`؛ محتوا در `posts`) |
| `registerTaxonomy(slug, args)` | رده‌بندی متصل به CPT |
| `registerField(field)` | فیلد سفارشی (رندر در فرم ادمین، ذخیره در `post_meta` با کلید `field:{key}`) |
| `registerBlock(block)` | بلاک بیلدر (اسلاگ باید با پیشوند ونـدور مثل `my/` شروع شود) |
| `registerWidget(widget)` | ویجت سایدبار |
| `registerRoute(methods, path, handler, middleware)` | روت عمومی (بعد از روت‌های هسته سوار می‌شود) |
| `registerApiEndpoint(method, path, handler, ability)` | اندپوینت REST زیر `/api/v1/…` با توکن |
| `registerAdminPage(slug, title, handler, icon, permission)` | صفحه `/admin/plugin/{plugin}--{page}` |
| `registerSettings(fields)` | اسکیمای تنظیمات (خواندن با `setting()`، ذخیره با `saveSetting()`) |
| `on(hook, fn)` / `filter(hook, fn, priority)` | هوک/فیلتر |
| `listen(event, fn)` / `dispatch(object $event)` | رویدادهای شیء‌محور |
| `cron(hook, name, schedule, handler)` | کار زمان‌بندی‌شده (`minutely`/`hourly`/`daily`/`weekly`) |
| `command(name, desc, handler)` | دستور CLI (`php irj name`) |

## مایگریشن افزونه

`Migrations/Version_1_0_0_CreateTables.php`:

```php
namespace IRJalali\Plugins\MyPlugin\Migrations;

use IRJalali\Core\Database\Database;

final class Version_1_0_0_CreateTables
{
    public function up(Database $db): void
    {
        if ($db->driver() === 'sqlite') {
            $db->query('CREATE TABLE IF NOT EXISTS my_table (…)');
        } else {
            $db->query('CREATE TABLE IF NOT EXISTS `my_table` (…) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        }
    }

    public function down(Database $db): void
    {
        $db->query('DROP TABLE IF EXISTS my_table');
    }
}
```

مایگریشن‌ها در `plugin_migrations` با checksum ثبت می‌شوند؛ در آپدیت، فقط نسخه‌های جدید اجرا می‌شوند.

## نقاط توسعه در دسترس افزونه‌ها

| نوع | نام | محل |
|---|---|---|
| فیلتر | `http.before` | قبل از مسیریابی — بازگرداندن `Response` = پاسخ کوتاه‌مدار (کش صفحه) |
| فیلتر | `http.response` | روی پاسخ خروجی |
| فیلتر | `front.head_meta` | تزریق متا در `<head>` قالب |
| فیلتر | `front.redirect` | تصاحب اسلاگ ۴۰۴ (ریدایرکت‌ها) |
| اکشن | `front.not_found` | ثبت ۴۰۴ |
| فیلتر | `security.block_ip` | وتوی IP قبل از شروع نشست |
| فیلتر | `membership.has_access` | اعطای دسترسی محتوای ویژه |
| اکشن | `ir_commerce.order_created` | سفارش جدید فروشگاه |
| فیلتر | `dashboard.widgets` | ویجت‌های پیشخوان |
| فیلتر | `scheduler.handlers` | هندلرهای کرون |

## امنیت بسته‌ها

هر بسته قبل از نصب اسکن می‌شود: الگوهای ممنوع (`eval`, `shell_exec`, `exec`, backtick، `base64_decode` مشکوک و…) + خطایابی `php -l` در صورت در دسترس بودن CLI. فایل‌های بزرگ فقط خط‌یابی می‌شوند.

## نمونه مرجع

افزونه `plugins/IrDemo` تمام سطوح بالا را به‌صورت واقعی استفاده می‌کند — الگوی رسمی برای شروع.
