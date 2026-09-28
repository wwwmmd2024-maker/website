<?php
$__layout = 'layouts.admin';
$active = 'forms';
$form = $form ?? null;
$isEdit = $form !== null;
$title = $isEdit ? 'مشخصات فرم: ' . $form['title'] : 'فرم تازه';
?>
<div class="panel">
  <h3><?= e($title) ?></h3>
  <form method="post" action="<?= e($isEdit ? '/admin/forms/' . $form['id'] : '/admin/forms') ?>">
    <?= $csrf->field() ?>
    <label>عنوان</label>
    <input type="text" name="title" value="<?= e($isEdit ? $form['title'] : '') ?>" required maxlength="150">
    <label>نامک</label>
    <input type="text" name="slug" dir="ltr" value="<?= e($isEdit ? $form['slug'] : '') ?>" maxlength="120">
    <label>توضیح</label>
    <textarea name="description" rows="3" maxlength="2000"><?= e($isEdit ? ($form['description'] ?? '') : '') ?></textarea>
    <label style="display:flex;gap:8px;align-items:center;font-weight:400">
      <input type="checkbox" name="is_active" value="1" <?= !$isEdit || !empty($form['is_active']) ? 'checked' : '' ?>>
      فرم فعال است و در سایت نمایش داده می‌شود
    </label>
    <br>
    <button class="btn" type="submit">ذخیره</button>
    <a href="/admin/forms">بازگشت</a>
  </form>
</div>
