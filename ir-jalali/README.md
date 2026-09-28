# IR-Jalali — Website Operating System

> **نسخه هسته:** ۱٫۱٫۰ · **وضعیت:** ✅ کامل و تست‌شده (تست‌های خودکار + تست زندهٔ وب واقعی)

IR-Jalali یک **سیستم‌عامل وب‌سایت** است؛ نه یک قالب و نه یک CMS ساده.
هدف: ساخت تقریباً هر نوع وب‌سایت (شرکتی، فروشگاهی، خبری، آموزشی، رزرواسیون، دایرکتوری، عضویت…)
**بدون تغییر هسته** — فقط با Theme، Plugin، Module، Block و Widget.

```
CORE = PLATFORM      THEME = DESIGN        PLUGIN = FUNCTIONALITY
MODULE = FEATURE     BLOCK = UI COMPONENT  TEMPLATE = STRUCTURE
CONTENT = DATA       API = COMMUNICATION
```

---

## ۱. پیش‌نیازها (تولیدی)

| نیاز | مقدار |
|---|---|
| PHP | 8.2+ (تست‌شده تا 8.5) |
| دیتابیس | MySQL 8+ / MariaDB 10.6+ (برای توسعه محلی، SQLite هم پشتیبانی می‌شود) |
| وب‌سرور | Apache 2.4 (+mod_rewrite) یا Nginx |
| اکستنشن اجباری | pdo, pdo_mysql, mbstring, json, fileinfo |
| اکستنشن اختیاری | curl, gd (تصویر)، intl، zip، redis |
| هاست | سازگار با Shared Hosting + cPanel، بدون Composer/Node در پروداکشن |

## ۲. نصب روی cPanel (تولیدی)

1. محتوا را آپلود کنید و **DocumentRoot را روی `…/ir-jalali/public` بگذارید.**
2. در cPanel یک دیتابیس MySQL + کاربر بسازید.
3. از `‎.env.example` یک `‎.env` بسازید و مقادیر را تنظیم کنید (هرگز کامیت نشود).
4. مرورگر: `‎/install` ← ویزارد ← قفل نصب (`storage/install.lock`).
5. کرون دقیقه‌ای: `php /path/cron.php >/dev/null 2>&1`
   (یا با `CRON_WEB_TOKEN` از مسیر وب).

جزئیات کامل: [`docs/DEPLOYMENT.md`](docs/DEPLOYMENT.md)

## ۳. اجرای محلی (توسعه)

```bash
cp .env.example .env          # DB_DRIVER=sqlite برای توسعه بدون MySQL
php dev/rebuild.php           # ساخت DB + سید + مدیر + فعال‌سازی همه افزونه‌ها
php dev/demo.php              # محتوای نمونه برای همه ماژول‌ها
php -S 127.0.0.1:8000 -t public dev/router.php
# سایت:   http://127.0.0.1:8000/
# ادمین:  http://127.0.0.1:8000/admin   (admin / Admin12345!)

php tests/smoke.php           # تست خودکار هسته
php dev/check-plugins.php     # تست یکپارچگی ۱۲ افزونه + بازارچه
```

## ۴. ساختار

```
public/        فرانت‌کنترلر + دارایی‌ها (تنها نقطه ورود وب)
core/          هسته مستقل (Kernel, Http, Database, Security, Date, …)
app/           لایه اپلیکیشن (Controllers, Services, Repositories, Views, …)
config/        کانفیگ محیطی (مقادیر از .env)
database/      مایگریشن‌ها (دو-درایوری) و سیدرها
languages/     fa_IR (پیش‌فرض، RTL) و en_US
storage/       logs / cache / uploads
themes/ plugins/ modules/ blocks/ widgets/ templates/   ← موتورهای توسعه
marketplace/   کاتالوگ + بسته‌های نصبی (LocalMarketplaceProvider)
docs/          مستندات کامل
tests/         تست خودکار
irj            CLI رسمی      ·      cron.php      ورودی زمان‌بند
```

## ۵. ماژول‌های رسمی (بدون تغییر هسته)

| ماژول | کار |
|---|---|
| 🛒 `ir-commerce` | محصول، سبد خرید جلسه‌ای، پرداخت، سفارش، کنترل موجودی، ادمین سفارش‌ها |
| 📅 `ir-booking` | خدمات، وقت‌دهی با اسلات پویا، مدیریت رزرو |
| 👥 `ir-membership` | ثبت‌نام، پلن اشتراک، کنترل دسترسی محتوا |
| 🎓 `ir-lms` | دوره/درس، ثبت‌نام، قفل محتوا، ردگیری پیشرفت |
| 📒 `ir-directory` | آگهی/نیازمندی با ثبت بازدیدکننده و تأیید مدیر |
| 🔎 `ir-seo` | متا/OG/Schema، سایت‌مپ، ریدایرکت، مانیتور 404 |
| 📊 `ir-analytics` | آمار بازدید سبک با نمودار، بدون وابستگی خارجی |
| ✉️ `ir-smtp` | SMTP خالص با صف، قالب `{{}}` و لاگ |
| ⚡ `ir-cache` | کش کامل صفحه + کش فرگمنت (بدون نیاز به Redis) |
| 🛡️ `ir-security` | فایروال IP + غربال امضای درخواست |
| ◈ `ir-demo` | افزونه مرجع که تمام سطوح پلاگین-API را نشان می‌دهد |

همه از **بازارچه محلی** یا `php irj plugin:install` نصب و با بکاپ/رولبک خودکار آپدیت می‌شوند.

## ۶. قابلیت‌های پلتفرم

- **نصب‌کننده وب + ویزارد راه‌اندازی** با حالت‌های آمادهٔ سایت.
- **معماری** DI + Service + Repository؛ Controller → Service → Repository → Database.
- **۴۸ جدول** با مایگریشن دودرایوری و سیدر؛ افزونه‌ها جدول خود را می‌سازند.
- **موتور محتوا:** CPT سفارشی + فیلدساز + تاکسونومی + منو + بازبینی + رسانه امن.
- **تقویم جلالی** Pure-PHP (راستی‌آزمایی با Intl ۱۹۲۵–۲۰۳۰) + abstraction برای میلادی/قمری.
- **RBAC واقعی** (۶ نقش، ۲۷ دسترسی) + ممیزی + لاگ امنیتی.
- **زمان‌بند + صف + کرون** با نقاط توسعه برای افزونه‌ها.
- **بیلدر بصری:** بلاک‌ها/ویجت‌های هسته + ثبت بلاک/ویجت توسط افزونه‌ها.
- **REST API نسخه ۱** (توکن Bearer، Throttle، ۱۳ منبع هسته + منابع افزونه‌ها).
- **امنیت:** CSRF، Rate Limit، هدرهای امنیتی + CSP، نشست سخت‌گیرانه، محافظ مسیر/فایل، محافظت از ورود و اسکن بسته‌ها.
- **CLI** (`php irj`) برای migrate، کاربر، نصب/فعال‌سازی/آپدیت/حذف افزونه و قالب و سازنده‌های `make:*`.

## ۷. مستندات

- [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) — معماری و چرخه درخواست
- [`docs/PLUGIN_API.md`](docs/PLUGIN_API.md) — راهنمای کامل نویسنده افزونه
- [`docs/REST_API.md`](docs/REST_API.md) — مرجع API
- [`docs/MODULES.md`](docs/MODULES.md) — ماژول‌های رسمی
- [`docs/DEPLOYMENT.md`](docs/DEPLOYMENT.md) — استقرار روی هاست اشتراکی
- [`docs/DATABASE_SCHEMA.md`](docs/DATABASE_SCHEMA.md) · [`docs/ERD.md`](docs/ERD.md) · [`docs/SECURITY.md`](docs/SECURITY.md) · [`docs/ROADMAP.md`](docs/ROADMAP.md)
