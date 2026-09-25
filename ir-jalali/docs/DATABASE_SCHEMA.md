# IR-Jalali — Database Schema (M1)

- موتور: InnoDB، کاراکترست `utf8mb4` + کالیشن `utf8mb4_unicode_ci`.
- کلید اصلی همه جدول‌ها `id BIGINT UNSIGNED AUTO_INCREMENT` است، مگر تصریح‌شده.
- همه جدول‌ها `created_at/updated_at` دارند (DATETIME، nullable) مگر تصریح‌شده.
- Soft Delete با `deleted_at` روی: users, posts, media.
- مایگریشن‌ها: `database/Migrations/Migration00{1,2,3}*.php` + سیدر `database/Seeds/CoreSeeder.php`.

## Migration001Identity — هویت، سایت، تنظیمات

### users
| ستون | نوع | توضیح |
|---|---|---|
| uuid | CHAR(36) UNIQUE | شناسه عمومی |
| username | VARCHAR(60) UNIQUE | |
| email | VARCHAR(191) UNIQUE | |
| password_hash | VARCHAR(255) | Argon2id/Bcrypt |
| display_name/first_name/last_name | VARCHAR | نمایشی |
| mobile | VARCHAR(20) NULL | |
| avatar | VARCHAR(255) NULL | |
| status | VARCHAR(20) DEFAULT 'active' | + ایندکس |
| locale | VARCHAR(10) DEFAULT 'fa_IR' | |
| last_login_at | DATETIME NULL | |

### roles — slug UNIQUE · name · description · is_system
### permissions — slug UNIQUE · name · group + ایندکس group
### role_permissions — (role_id, permission_id) UNIQUE + دو FK
### user_roles — (user_id, role_id) UNIQUE + دو FK
### sites — name · domain(NULL+index) · locale/timezone/calendar · is_default
### settings — (site_id, group, key) UNIQUE · value LONGTEXT · type · is_public
### options — key UNIQUE · value LONGTEXT · autoload

## Migration002Content — محتوا

### post_types — slug UNIQUE · name · icon · supports(JSON) · settings(JSON) · is_system
### post_statuses — slug UNIQUE · name — شش وضعیت: draft/pending/published/scheduled/private/trash
### posts
uuid UNIQUE · post_type · title · slug · excerpt · content · content_json ·
status + ایندکس (post_type,status) · author_id · parent_id · menu_order ·
featured_image · template · locale · published_at(+index) · **(post_type,slug) UNIQUE**
### post_meta — post_id(FK) · key · value + ایندکس (post_id,key)
### revisions — entity_type · entity_id · user_id · title · snapshot · is_autosave · created_at(NOT NULL)
### taxonomies — slug UNIQUE · name · post_types(JSON) · hierarchical
### terms — taxonomy_id(FK) · name · slug · parent_id · description · count + **(taxonomy_id,slug) UNIQUE**
### term_relationships — post_id(FK) · term_id(FK) · ordering + (post_id,term_id) UNIQUE
### media — uuid UNIQUE · filename · original_name · mime(+index) · extension · size_bytes · folder(+index) · disk · width/height · alt · caption · description · uploaded_by
### media_meta — media_id(FK) · key · value + ایندکس
### menus — slug UNIQUE · name · location
### menu_items — menu_id(FK) · parent_id · title · type · url · reference_type/id · ordering · target · css_class
### templates — slug UNIQUE · name · type · content_json · is_default
### template_parts — template_id(FK) · area · content_json · ordering
### custom_field_groups — title · location_rules(JSON) · ordering · is_active
### custom_fields — group_id(FK) · key · label · type · settings(JSON) · ordering + (group_id,key) UNIQUE

## Migration003System — سیستم

### blocks — slug UNIQUE · name · category · source · source_slug · schema(JSON) · defaults(JSON) · is_active
### block_instances — uuid · block_id · entity_type · entity_id · area · data_json · ordering
### widgets — slug UNIQUE · name · source · source_slug · schema(JSON) · is_active
### widget_instances — uuid · widget_id · sidebar · data_json · ordering
### themes — slug UNIQUE · name · version · author · screenshot · is_active
### theme_settings — theme_id(FK) · key · value + UNIQUE
### plugins — slug UNIQUE · name · version · author · status · dependencies(JSON)
### plugin_settings — plugin_id(FK) · key · value + UNIQUE
### forms — title · slug UNIQUE · description · settings(JSON) · is_active
### form_fields — form_id(FK) · key · label · type · settings(JSON) · ordering
### form_submissions — form_id(FK) · data_json · ip · user_agent · is_read · created_at(NOT NULL)
### notifications — uuid · user_id · type · title · body · url · read_at · created_at(NOT NULL)
### sessions — id VARCHAR(128) PK · user_id · ip · user_agent · payload · last_activity (رزرو M2)
### api_tokens — user_id(FK) · name · token_hash UNIQUE · abilities(JSON) · last_used_at · expires_at
### logs — channel · level · message · context_json · user_id · ip · created_at(NOT NULL)
### audit_logs — user_id · action · entity_type/id · before/after JSON · ip · created_at(NOT NULL)
### security_logs — event · user_id · ip · user_agent · details_json · created_at(NOT NULL)
### redirects — from_path UNIQUE · to_url · status_code · hits · is_active
### seo_meta — (entity_type,entity_id) UNIQUE · meta_title/description · canonical_url · og_image · extra(JSON)
### scheduled_tasks — name · hook UNIQUE · schedule · payload_json · next_run_at(NOT NULL) · last_run_at · is_active
### cache_entries — key PK · value · expires_at (رزرو M2)
