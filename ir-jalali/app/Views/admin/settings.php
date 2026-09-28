<?php
$__layout = 'layouts.admin';
$active = 'settings';
$title = $t->get('settings.title');
$settings = $settings ?? [];
?>
<div class="panel">
  <form method="post" action="/admin/settings">
    <?= $csrf->field() ?>
    <label><?= e($t->get('settings.site_title')) ?></label>
    <input type="text" name="site_title" value="<?= e($settings['site_title'] ?? '') ?>" required>
    <label><?= e($t->get('settings.tagline')) ?></label>
    <input type="text" name="tagline" value="<?= e($settings['tagline'] ?? '') ?>">
    <label><?= e($t->get('settings.language')) ?></label>
    <select name="language">
      <option value="fa_IR" <?= ($settings['language'] ?? '') === 'fa_IR' ? 'selected' : '' ?>>فارسی (راست‌به‌چپ)</option>
      <option value="en_US" <?= ($settings['language'] ?? '') === 'en_US' ? 'selected' : '' ?>>English (LTR)</option>
    </select>
    <label><?= e($t->get('settings.timezone')) ?></label>
    <input type="text" name="timezone" dir="ltr" value="<?= e($settings['timezone'] ?? 'Asia/Tehran') ?>" required>
    <label><?= e($t->get('settings.website_mode')) ?></label>
    <select name="website_mode">
      <?php foreach (($modes ?? []) as $mode): ?>
        <option value="<?= e($mode['value']) ?>" <?= ($settings['website_mode'] ?? '') === $mode['value'] ? 'selected' : '' ?>><?= e($mode['label']) ?></option>
      <?php endforeach; ?>
    </select>
    <br>
    <button class="btn" type="submit"><?= e($t->get('settings.save')) ?></button>
  </form>
</div>

<div class="panel">
  <h3>برندینگ (وایت‌لیبل)</h3>
  <p class="muted" style="font-size:13px">برای حذف برند پیش‌فرض پلتفرم از پنل مدیریت، مقادیر زیر را پر کنید. خالی بودن هر فیلد یعنی استفاده از برند پیش‌فرض.</p>
  <form method="post" action="/admin/settings">
    <?= $csrf->field() ?>
    <input type="hidden" name="site_title" value="<?= e($settings['site_title'] ?? '') ?>">
    <input type="hidden" name="language" value="<?= e($settings['language'] ?? 'fa_IR') ?>">
    <input type="hidden" name="timezone" value="<?= e($settings['timezone'] ?? 'Asia/Tehran') ?>">
    <input type="hidden" name="website_mode" value="<?= e($settings['website_mode'] ?? 'general') ?>">
    <label>نام برند در پنل مدیریت</label>
    <input type="text" name="admin_name" maxlength="80" value="<?= e($settings['admin_name'] ?? '') ?>" placeholder="مثلاً: سامانه مدیریت شرکت من">
    <label>آدرس لوگوی پنل (اختیاری — از بخش رسانه آپلود کنید)</label>
    <input type="text" name="admin_logo" dir="ltr" maxlength="255" value="<?= e($settings['admin_logo'] ?? '') ?>" placeholder="/uploads/.../logo.png">
    <label>متن پاصفحه پنل</label>
    <input type="text" name="admin_footer" maxlength="200" value="<?= e($settings['admin_footer'] ?? '') ?>" placeholder="© نام شرکت شما">
    <br>
    <button class="btn" type="submit"><?= e($t->get('settings.save')) ?></button>
  </form>
</div>
