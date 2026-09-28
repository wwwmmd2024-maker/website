# Installer

نصب‌کننده وب در مسیر `/install` زندگی می‌کند:

- کنترلر: `app/Controllers/InstallController.php`
- منطق: `app/Services/InstallerService.php` (پیش‌نیازها، تست اتصال، .env، مایگریشن، سید، مدیر، قفل)
- ویوها: `app/Views/installer/*`

پس از نصب موفق، فایل `storage/install.lock` ساخته می‌شود و
میدل‌ویر `RequireNotInstalled` مسیر `/install` را برای همیشه می‌بندد.
