# IR-Jalali — ERD (Core Tables, M1)

> نماد: `1──∞` یعنی یک-به-چند. FKها در MySQL واقعی‌اند (CASCADE روی همه).

## ۱. هویت و دسترسی (RBAC)

```
users 1──∞ user_roles ∞──1 roles 1──∞ role_permissions ∞──1 permissions
  │                        │
  │                        └── slug یکتا: super_admin, administrator, editor,
  │                            author, contributor, subscriber (+ دلخواه)
  └── users.uuid یکتا · soft delete · status
```

## ۲. سایت و تنظیمات

```
sites 1──∞ settings (site_id, group, key)   [key/value گروه‌بندی‌شده]
options (key یکتا)                           [key/value سراسری + کش]
```

## ۳. موتور محتوا

```
post_types ──(slug)── posts ──∞ post_meta (post_id, key, value)
post_statuses ──(slug)──┘
  │
  │  posts: uuid یکتا · (post_type,slug) یکتا · status · author_id
  │         parent_id · template · published_at · soft delete
  │
  ├──∞ term_relationships ∞──1 terms ∞──1 taxonomies
  │                              (taxonomy_id,slug) یکتا
  └──(author_id)── users
```

## ۴. بازبینی (Polymorphic)

```
revisions → (entity_type, entity_id)
  entity_type ∈ {post, page, template, form, menu, theme_settings}
  + user_id · snapshot(JSON) · is_autosave
```

## ۵. رسانه

```
media 1──∞ media_meta (media_id, key, value)
  media: uuid یکتا · folder · mime · width/height · soft delete
```

## ۶. فهرست‌ها

```
menus 1──∞ menu_items (خودارجاع parent_id برای تودرتویی)
  menu_items: type ∈ {custom, page, post, cpt, taxonomy} · reference_* · ordering
```

## ۷. قالب‌ها و ظاهر (داده Part 1 — موتور Part 2)

```
templates 1──∞ template_parts (template_id, area)
themes 1──∞ theme_settings (theme_id, key)
blocks ──(block_id?)── block_instances → (entity_type, entity_id, area)
widgets ──(widget_id?)── widget_instances (sidebar, ordering)
seo_meta → (entity_type, entity_id) یکتا
redirects (from_path یکتا)
```

## ۸. افزونه‌ها و فیلدها

```
plugins 1──∞ plugin_settings (plugin_id, key)
custom_field_groups 1──∞ custom_fields ((group_id,key) یکتا)
forms 1──∞ form_fields · forms 1──∞ form_submissions
```

## ۹. کاربر، اعلان، توکن

```
users 1──∞ api_tokens (token_hash یکتا) · users 1──∞ notifications
sessions (رزرو درایور دیتابیسی؛ M1 از سشن امن native استفاده می‌کند)
cache_entries (رزرو درایور دیتابیسی؛ M1 از کش فایل استفاده می‌کند)
```

## ۱۰. لاگ، حسابرسی، زمان‌بند

```
logs (channel, level) · audit_logs (user_id, action, entity_*)
security_logs (event, user_id, ip)
scheduled_tasks (hook یکتا · schedule · next_run_at)
```

## جدول‌ها (۴۵ + migrations = ۴۶)

users, roles, permissions, role_permissions, user_roles, sites, settings,
options, post_types, post_statuses, posts, post_meta, revisions, taxonomies,
terms, term_relationships, media, media_meta, menus, menu_items, templates,
template_parts, custom_field_groups, custom_fields, blocks, block_instances,
widgets, widget_instances, themes, theme_settings, plugins, plugin_settings,
forms, form_fields, form_submissions, notifications, sessions, api_tokens,
logs, audit_logs, security_logs, redirects, seo_meta, scheduled_tasks,
cache_entries, migrations
