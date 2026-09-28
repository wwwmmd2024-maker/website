<?php
$__layout = 'layouts.admin';
$active = $active ?? 'pages';
$isPage = ($type ?? 'page') === 'page';
$post = $post ?? null;
$isEdit = $post !== null;
$title = ($isEdit ? 'ویرایش ' : ($isPage ? 'برگه تازه' : 'نوشته تازه')) . ($isEdit ? ': ' . $post->title : '');
$pubLocal = '';
if ($isEdit && !empty($post->publishedAt)) {
    $pubLocal = str_replace(' ', 'T', substr($post->publishedAt, 0, 16));
}
?>
<div class="panel">
  <h3><?= e($title) ?></h3>
  <?php if ($isEdit): ?>
    <p><a class="btn" style="text-decoration:none" href="/admin/builder/<?= e($type) ?>/<?= e((string) $post->id) ?>">ویرایش با بیلدر بصری</a></p>
  <?php endif; ?>
  <form method="post" action="<?= e($isEdit ? $baseUrl . '/' . $post->id : $baseUrl) ?>">
    <?= $csrf->field() ?>
    <label>عنوان</label>
    <input type="text" name="title" value="<?= e($isEdit ? $post->title : '') ?>" required maxlength="200">
    <label>نامک (خالی = خودکار از روی عنوان)</label>
    <input type="text" name="slug" dir="ltr" value="<?= e($isEdit ? $post->slug : '') ?>" maxlength="180">
    <label>وضعیت</label>
    <select name="status">
      <?php $st = $isEdit ? $post->status : 'draft'; ?>
      <option value="draft" <?= $st === 'draft' ? 'selected' : '' ?>>پیش‌نویس</option>
      <?php if (!empty($canPublish)): ?>
        <option value="published" <?= $st === 'published' ? 'selected' : '' ?>>منتشرشده</option>
        <option value="scheduled" <?= $st === 'scheduled' ? 'selected' : '' ?>>زمان‌بندی‌شده</option>
      <?php endif; ?>
    </select>
    <label>تاریخ انتشار (برای انتشار/زمان‌بندی)</label>
    <input type="datetime-local" name="published_at" dir="ltr" value="<?= e($pubLocal) ?>">
    <label>خلاصه</label>
    <textarea name="excerpt" rows="2" maxlength="500"><?= e($isEdit ? ($post->excerpt ?? '') : '') ?></textarea>
    <label>محتوای کلاسیک (اگر بیلدر استفاده می‌کنید، خالی بگذارید)</label>
    <textarea name="content" rows="8" dir="auto"><?= e($isEdit ? ($post->content ?? '') : '') ?></textarea>
    <?php if ($isPage): ?>
      <label>والد</label>
      <select name="parent_id">
        <option value="">— بدون والد —</option>
        <?php foreach (($parents ?? []) as $parent): ?>
          <?php if ($isEdit && $parent->id === $post->id) continue; ?>
          <option value="<?= e((string) $parent->id) ?>" <?= $isEdit && (int) ($post->row['parent_id'] ?? 0) === $parent->id ? 'selected' : '' ?>><?= e($parent->title) ?></option>
        <?php endforeach; ?>
      </select>
      <label>ترتیب</label>
      <input type="number" name="menu_order" value="<?= e($isEdit ? (string) ($post->row['menu_order'] ?? 0) : '0') ?>" style="max-width:140px">
    <?php endif; ?>
    <label>تصویر شاخص (نشانی)</label>
    <input type="text" name="featured_image" dir="ltr" value="<?= e($isEdit ? ($post->featuredImage ?? '') : '') ?>" maxlength="500">
    <br><br>
    <button class="btn" type="submit">ذخیره</button>
    <a href="<?= e($baseUrl) ?>">بازگشت</a>
  </form>
</div>
