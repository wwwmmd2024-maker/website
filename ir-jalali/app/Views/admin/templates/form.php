<?php
$__layout = 'layouts.admin';
$active = 'templates';
$template = $template ?? null;
$isEdit = $template !== null;
$title = $isEdit ? 'مشخصات قالب: ' . $template['name'] : 'قالب تازه';
$typeLabels = ['page' => 'برگه', 'single' => 'تکی نوشته', 'archive' => 'آرشیو', 'header' => 'سربرگ', 'footer' => 'پانوشت', 'search' => 'جستجو', 'error404' => 'خطای ۴۰۴'];
?>
<div class="panel">
  <h3><?= e($title) ?></h3>
  <?php if ($isEdit): ?>
    <p><a class="btn" style="text-decoration:none" href="/admin/builder/template/<?= e((string) $template['id']) ?>">ویرایش با بیلدر بصری</a></p>
  <?php endif; ?>
  <form method="post" action="<?= e($isEdit ? '/admin/templates/' . $template['id'] : '/admin/templates') ?>">
    <?= $csrf->field() ?>
    <label>نام قالب</label>
    <input type="text" name="name" value="<?= e($isEdit ? $template['name'] : '') ?>" required maxlength="150">
    <label>نامک</label>
    <input type="text" name="slug" dir="ltr" value="<?= e($isEdit ? $template['slug'] : '') ?>" maxlength="120">
    <label>نوع</label>
    <select name="type">
      <?php foreach (($types ?? []) as $tname): ?>
        <option value="<?= e($tname) ?>" <?= ($isEdit ? $template['type'] : 'page') === $tname ? 'selected' : '' ?>><?= e($typeLabels[$tname] ?? $tname) ?></option>
      <?php endforeach; ?>
    </select>
    <label style="display:flex;gap:8px;align-items:center;font-weight:400">
      <input type="checkbox" name="is_default" value="1" <?= $isEdit && !empty($template['is_default']) ? 'checked' : '' ?>>
      قالب پیش‌فرض این نوع
    </label>
    <br>
    <button class="btn" type="submit">ذخیره</button>
    <a href="/admin/templates">بازگشت</a>
  </form>
</div>
