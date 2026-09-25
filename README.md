# website

Repository for the **IR-Jalali** project — a Persian-first "Universal Website Operating System".

## Layout

- [`ir-jalali/`](ir-jalali/) — the complete platform (core, app, themes, plugins, marketplace, docs, tests). Start here: [`ir-jalali/README.md`](ir-jalali/README.md).
- [`login-page/`](login-page/) — static login page design reference.

## Quick start

```bash
cd ir-jalali
cp .env.example .env        # set DB values (sqlite is fine for local dev)
php dev/rebuild.php         # fresh DB + admin + all official plugins
php dev/demo.php            # sample content for every module
php -S 127.0.0.1:8000 -t public dev/router.php
```

Site at `http://127.0.0.1:8000/`, admin at `http://127.0.0.1:8000/admin`
(default dev login: `admin` / `Admin12345!`).

Tests:

```bash
php tests/smoke.php          # core test-suite
php dev/check-plugins.php    # 12-plugin integration + marketplace check
```
