<?php
$__layout = 'layouts.guest';
$step = $step ?? 'database';
$stored = $stored ?? [];
$title = $t->get('install.title');
$stepNum = ['database' => 2, 'admin' => 3, 'site' => 4][$step] ?? 2;
?>
<h1><?= e($t->get('install.title')) ?></h1>
<p class="sub">گام <?= e((string) $stepNum) ?> از ۴</p>
<div class="steps">
  <span>پیش‌نیازها</span>
  <span class="<?= $step === 'database' ? 'on' : '' ?>">دیتابیس</span>
  <span class="<?= $step === 'admin' ? 'on' : '' ?>">مدیر</span>
  <span class="<?= $step === 'site' ? 'on' : '' ?>">سایت</span>
</div>
<form method="post" action="/install">
  <?= $csrf->field() ?>
  <input type="hidden" name="step" value="<?= e($step) ?>">

  <?php if ($step === 'database'): ?>
    <div class="row">
      <div><label>هاست دیتابیس</label><input type="text" name="db_host" dir="ltr" value="<?= e($stored['db_host'] ?? '127.0.0.1') ?>" required></div>
      <div><label>پورت</label><input type="number" name="db_port" dir="ltr" value="<?= e($stored['db_port'] ?? '3306') ?>" required></div>
    </div>
    <label>نام دیتابیس</label><input type="text" name="db_name" dir="ltr" value="<?= e($stored['db_name'] ?? '') ?>" required>
    <div class="row">
      <div><label>نام کاربری</label><input type="text" name="db_user" dir="ltr" value="<?= e($stored['db_user'] ?? '') ?>" required></div>
      <div><label>گذرواژه</label><input type="password" name="db_pass" dir="ltr" value="<?= e($stored['db_pass'] ?? '') ?>"></div>
    </div>
    <button class="btn" type="submit">تست اتصال و ادامه ←</button>

  <?php elseif ($step === 'admin'): ?>
    <label>نام کاربری مدیر</label><input type="text" name="admin_username" dir="ltr" value="<?= e($stored['admin_username'] ?? '') ?>" required>
    <label>ایمیل مدیر</label><input type="email" name="admin_email" dir="ltr" value="<?= e($stored['admin_email'] ?? '') ?>" required>
    <div class="row">
      <div><label>گذرواژه (حداقل ۸ کاراکتر)</label><input type="password" name="admin_password" dir="ltr" required></div>
      <div><label>تکرار گذرواژه</label><input type="password" name="admin_password_confirm" dir="ltr" required></div>
    </div>
    <button class="btn" type="submit"><?= e($t->get('install.next')) ?> ←</button>

  <?php else: ?>
    <label>عنوان سایت</label><input type="text" name="site_title" value="<?= e($stored['site_title'] ?? 'سایت من') ?>" required>
    <label>معرفی کوتاه</label><input type="text" name="site_tagline" value="<?= e($stored['site_tagline'] ?? '') ?>">
    <div class="row">
      <div><label>زبان</label>
        <select name="site_language">
          <option value="fa_IR" <?= ($stored['site_language'] ?? 'fa_IR') === 'fa_IR' ? 'selected' : '' ?>>فارسی</option>
          <option value="en_US" <?= ($stored['site_language'] ?? '') === 'en_US' ? 'selected' : '' ?>>English</option>
        </select>
      </div>
      <div><label>منطقه زمانی</label><input type="text" name="site_timezone" dir="ltr" value="<?= e($stored['site_timezone'] ?? 'Asia/Tehran') ?>" required></div>
    </div>
    <label>آدرس سایت (URL)</label><input type="url" name="site_url" dir="ltr" value="<?= e($stored['site_url'] ?? 'http://localhost:8000') ?>">
    <button class="btn" type="submit">🚀 <?= e($t->get('install.install_now')) ?></button>
  <?php endif; ?>
</form>
