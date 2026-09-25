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
