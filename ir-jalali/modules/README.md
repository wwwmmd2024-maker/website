# Modules (Engine: Milestone 2)

ماژول = سیستم فیچر کامل (مثل E-Commerce، Booking) که به Website Mode گره می‌خورد:

```
modules/{slug}/
  module.json  { "slug","modes":["ecommerce"],"routes":true,"migrations":true }
  routes.php · Migrations/ · Views/ · Controllers/ · Services/
```

فعال‌سازی ماژول هرگز Core را لمس نمی‌کند؛ فقط داده + روت + منو اضافه می‌کند.
