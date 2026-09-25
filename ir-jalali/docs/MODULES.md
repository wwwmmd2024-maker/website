# IR-Jalali — ماژول‌های رسمی (Official Modules)

یازده ماژول رسمی، همه به‌صورت **افزونه** و بدون هیچ تغییر در هسته. هر کدام با `plugin.json`، مایگریشن اختصاصی، صفحات ادمین و نقاط توسعه خود.

| ماژول | اسلاگ | کار اصلی |
|---|---|---|
| فروشگاه | `ir-commerce` | محصول، سبد، پرداخت، سفارش، موجودی |
| رزرواسیون | `ir-booking` | خدمات، وقت‌دهی، مدیریت رزرو |
| عضویت | `ir-membership` | ثبت‌نام، پلن اشتراک، کنترل دسترسی |
| آموزش | `ir-lms` | دوره، درس، ثبت‌نام، پیشرفت |
| دایرکتوری | `ir-directory` | آگهی/نیازمندی با ثبت بازدیدکننده |
| سئو | `ir-seo` | متا، سایت‌مپ، ریدایرکت، مانیتور 404 |
| فرم | (فرم‌ساز هسته) | ساخت فرم + ارسال‌ها |
| آمار | `ir-analytics` | بازدید صفحات بدون وابستگی خارجی |
| ایمیل | `ir-smtp` | SMTP با صف، قالب و لاگ |
| کش | `ir-cache` | کش کامل صفحه + کش فرگمنت |
| امنیت | `ir-security` | فایروال IP + غربال امضای درخواست |

## فروشگاه (IR-Commerce)

- CPT `product` + رده `product-category` + فیلدهای `price / sale_price / sku / stock`.
- سبد خرید جلسه‌ای؛ قیمت در لحظه افزودن قفل می‌شود.
- مسیرها: `/shop`، `/cart`، `/checkout`، `/order/{order_no}`.
- مالیات و هزینه ارسال از تنظیمات؛ کنترل موجودی و قفسه‌بندی.
- ادمین: `/admin/plugin/ir-commerce--orders` (تغییر وضعیت سفارش/پرداخت).
- بلوک بیلدر `ir/products` و ویجت `ir/cart-summary`.
- رویداد `ir_commerce.order_created` برای اطلاع‌رسانی (مثلاً ایمیل).

## رزرواسیون (IR-Booking)

- CPT `service` با فیلد `duration_min`.
- اسلات‌ها از `work_start / work_end / days_off` تولید و با رزروهای موجود تضادیاب می‌شوند.
- مسیرها: `/booking`، `/booking/{id}`، `/booking/{id}/slots` (AJAX)، `/booking/done/{id}`.
- ادمین: `/admin/plugin/ir-booking--appointments`.

## عضویت (IR-Membership)

- ثبت‌نام کاربر با نقش `subscriber` (جدول `users` هسته).
- پلن‌ها در `ir_membership_plans`؛ اشتراک‌ها در `ir_membership_subscriptions`.
- کنترل دسترسی با فیلتر `membership.has_access` — سایر ماژول‌ها (LMS) از آن استفاده می‌کنند.
- مسیرها: `/plans`، `/register`، `/member`، `/member/login`.
- کرون روزانه: انقضای اشتراک‌های گذشته.

## آموزش (IR-LMS)

- CPT `course` و `lesson`؛ درس‌ها با `course_id` به دوره وصل می‌شوند.
- ثبت‌نام: دوره رایگان → فعال؛ پولی → در انتظار تأیید یا با دسترسی عضویت فعال می‌شود.
- قفل محتوا برای درس‌های غیررایگان؛ علامت‌گذاری پیشرفت در `ir_lms_progress`.
- مسیرها: `/courses`، `/course/{slug}`، `/learn`، `/lesson/{id}`.

## دایرکتوری (IR-Directory)

- CPT `listing` با فیلدهای تماس/نشانی.
- بازدیدکنندگان از `/directory/submit` ثبت می‌کنند → وضعیت `pending` → مدیر منتشر می‌کند.
- جستجوی عمومی در `/directory`.

## سئو (IR-SEO)

- تزریق متا/کانونیکال/OG/توییتر/Schema با فیلتر `front.head_meta`.
- `/sitemap.xml` و `/robots.txt` پویا.
- مدیر ریدایرکت با فیلتر `front.redirect` + مانیتور 404 با اکشن `front.not_found`.
- ادمین: `/admin/plugin/ir-seo--manager`.

## آمار (IR-Analytics)

- ثبت بازدید در فیلتر `http.response` (بدون اسکریپت خارجی).
- تشخیص ربات، جمع‌بندی روزانه در `ir_analytics_daily`، نمودار ۱۴روزه.
- ادمین: `/admin/plugin/ir-analytics--analytics`.

## ایمیل (IR-SMTP)

- کلاینت SMTP خالص (STARTTLS/SSL، AUTH) بدون وابستگی.
- صف در `ir_smtp_queue` + لاگ در `ir_smtp_log` + قالب‌های `{{placeholder}}`.
- درایور `log` برای توسعه. سایر ماژول‌ها با `Plugin::mailer()` ایمیل می‌فرستند.
- ادمین: `/admin/plugin/ir-smtp--mail` (تست، پردازش صف، لاگ).

## کش (IR-Cache)

- کش کامل صفحه برای مهمان‌ها از `http.before`/`http.response` (بدون Redis).
- صفحات دارای `_token` یا کاربران لاگین‌شده/ادمین هرگز کش نمی‌شوند.
- کش فرگمنت برای توسعه‌دهندگان: `FragmentCache::render(key, ttl, fn)`.
- درایور کش هسته: `FileCache` پیش‌فرض؛ `RedisCache` اختیاری با افتادن خودکار روی فایل.
- ادمین: `/admin/plugin/ir-cache--cache` (فعال/غیرفعال، عمر، پاک‌سازی).

## امنیت (IR-Security)

- فایروال مسدود/مجازسازی IP در `ir_security_rules` با انقضا؛ اتصال به `security.block_ip` هسته.
- غربال امضای درخواست (پترن‌های رایج تزریق/مسیر‌یابی) با حالت «فقط لاگ» یا «مسدود موقت».
- مکمل هسته است: هسته از قبل `RateLimiter`، `SecurityHeaders` و audit دارد.
- ادمین: `/admin/plugin/ir-security--security`.

## نصب

همه ماژول‌ها در بسته‌های بازارچه محلی (`marketplace/packages/*.zip`) نیز بسته‌بندی شده‌اند و از ادمین «بازارچه/به‌روزرسانی» یا با `php irj plugin:install` قابل نصب‌اند.
