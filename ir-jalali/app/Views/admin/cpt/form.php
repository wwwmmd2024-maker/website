<?php
$__layout = 'layouts.admin';
$active = 'types';
$isEdit = ($item ?? null) !== null;
$title = ($isEdit ? 'ویرایش' : 'افزودن') . ' ' . $type['name'];
$values = $values ?? [];
?>
<form method="post" action="<?= $isEdit ? '/admin/cpt/' . e($type['slug']) . '/' . e((string) $item->id) : '/admin/cpt/' . e($type['slug']) ?>">
  <?= $csrf->field() ?? '' ?>
  <div class="panel">
    <h3><?= e($title) ?></h3>
    <label>عنوان</label>
    <input type="text" name="title" required value="<?= $isEdit ? e($item->title) : '' ?>">
    <?php if (!$isEdit): ?>
      <label>نامک (slug)</label>
      <input type="text" name="slug" dir="ltr" value="">
    <?php endif; ?>
    <label>چکیده</label>
    <textarea name="excerpt" rows="2" style="width:100%;max-width:560px"><?= $isEdit ? e((string) ($item->excerpt ?? '')) : '' ?></textarea>
    <label>محتوا (کلاسیک)</label>
    <textarea name="content" rows="6" style="width:100%;max-width:560px"><?= $isEdit ? e((string) ($item->content ?? '')) : '' ?></textarea>
    <label>وضعیت</label>
    <select name="status">
      <option value="draft" <?= $isEdit && $item->status === 'draft' ? 'selected' : '' ?>>پیش‌نویس</option>
      <option value="published" <?= $isEdit && $item->status === 'published' ? 'selected' : '' ?>>منتشرشده</option>
    </select>
  </div>

  <?php if (!empty($fields)): ?>
    <div class="panel">
      <h3>فیلدهای سفارشی</h3>
      <?php foreach ($fields as $field): ?>
        <?= $renderer->input($field, $values[$field['key']] ?? ($field['settings']['default'] ?? null)) ?>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <button class="btn" type="submit"><?= $isEdit ? 'ذخیره تغییرات' : 'ایجاد' ?></button>
  <a class="btn" style="background:#64748b" href="/admin/cpt/<?= e($type['slug']) ?>">بازگشت</a>
</form>
