# Blocks (Engine: Milestone 2 — Visual Builder)

بلاک = کامپوننت UI قابل استفاده در Page Builder.
تعریف در جدول `blocks` (slug, schema, defaults) + نمونه‌ها در `block_instances`.
رندر: `render(array $data): string` — خروجی همیشه escapeشده مگر فیلدهای rich مجاز.
