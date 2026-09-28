# Plugins (Engine: Milestone 2)

**قرارداد نهایی و ثابت** — موتور نصب/فعال‌سازی در M2 می‌آید، اما قرارداد از حالا قطعی است:

```
plugins/{slug}/
  plugin.json     { "slug","name","version","requires":{"core":">=1.0"},"dependencies":[] }
  Plugin.php      namespace IRJalali\Plugins\{Slug}; class Plugin { activate(); deactivate(); }
  routes.php      (اختیاری) ثبت روت از طریق Router
  Migrations/     (اختیاری) کلاس‌های IRJalali\Database\Migration
  Views/          (اختیاری)
```

قوانین: اتصال به Core فقط از Hooks/Events/Contracts/API. ویرایش Core ممنوع.
وضعیت در جدول `plugins` + تنظیمات در `plugin_settings` (هر دو از M1 آماده‌اند).
