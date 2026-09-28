# IR-Jalali — REST API (v1)

API فقط‌خوان و سرلس (Headless) برای اتصال اپلیکیشن‌ها و فرانت‌های جدا. همه پاسخ‌ها JSON است.

## احراز هویت

توکن `Bearer` در هدر `Authorization`:

```
Authorization: Bearer <token>
```

توکن از مسیر ادمین (API Tokens) یا CLI ساخته می‌شود و هش‌شده در `api_tokens` ذخیره می‌گردد. بدون توکن معتبر پاسخ `401` است.

نرخ‌گذاری: `ApiThrottle` تعداد درخواست در بازه را محدود و در صورت عبور `429` برمی‌گرداند.

## ساختار پاسخ

```json
{
  "ok": true,
  "data": [ ... ],
  "page": 1,
  "per_page": 20,
  "total": 42
}
```

## Endpointهای هسته

پیشوند: `/api/v1`

| متد | مسیر | احراز | توضیح |
|---|---|---|---|
| GET | `/status` | عمومی | وضعیت نصب، نسخه، زبان/تقویم |
| GET | `/posts` | توکن | نوشته‌های منتشرشده |
| GET | `/pages` | توکن | برگه‌ها |
| GET | `/users` | توکن | کاربران |
| GET | `/media` | توکن | رسانه‌ها |
| GET | `/themes` | توکن | قالب‌ها |
| GET | `/plugins` | توکن | افزونه‌ها |
| GET | `/blocks` | توکن | تعریف بلاک‌ها (بدون `render`) |
| GET | `/widgets` | توکن | تعریف ویجت‌ها |
| GET | `/templates` | توکن | قالب‌های صفحه (slug/name/type) |
| GET | `/forms` | توکن | فرم‌ساز + فیلدها |
| GET | `/settings` | توکن | تنظیمات عمومی |
| GET | `/menus` | توکن | فهرست‌ها + درخت آیتم‌ها |
| GET | `/search?q=…` | توکن | جستجوی محتوا |

## Endpointهای افزونه‌ها

افزونه‌ها با `registerApiEndpoint()` مسیر اضافه می‌کنند (همه با توکن):

| مسیر | افزونه |
|---|---|
| `/commerce/products` · `/commerce/orders` | IR-Commerce |
| `/booking/appointments` | IR-Booking |
| `/membership/plans` | IR-Membership |
| `/lms/courses` | IR-LMS |
| `/directory/listings` | IR-Directory |
| `/demo/stats` | IR-Demo (نمونه) |

## نمونه

```bash
curl -H "Authorization: Bearer $TOKEN" \
     "http://example.com/api/v1/posts?page=1"
```
