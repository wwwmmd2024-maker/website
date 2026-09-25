<?php
$__layout = 'layouts.admin';
$active = 'templates';
$title = 'قالب‌ها';
$typeLabels = ['page' => 'برگه', 'single' => 'تکی نوشته', 'archive' => 'آرشیو', 'header' => 'سربرگ', 'footer' => 'پانوشت', 'search' => 'جستجو', 'error404' => 'خطای ۴۰۴'];
?>
<div class="panel">
  <h3>قالب‌ها</h3>
  <form method="get" action="/admin/templates" style="display:flex;gap:8px;margin-bottom:12px">
    <select name="type" style="max-width:200px" onchange="this.form.submit()">
      <option value="">همه انواع</option>
      <?php foreach (($types ?? []) as $tname): ?>
        <option value="<?= e($tname) ?>" <?= ($type ?? '') === $tname ? 'selected' : '' ?>><?= e($typeLabels[$tname] ?? $tname) ?></option>
      <?php endforeach; ?>
    </select>
    <a class="btn" href="/admin/templates/create" style="text-decoration:none">+ قالب تازه</a>
  </form>
  <table class="tbl">
    <tr><th>#</th><th>نام</th><th>نامک</th><th>نوع</th><th>پیش‌فرض</th><th>اقدامات</th></tr>
    <?php foreach (($items ?? []) as $item): ?>
      <tr>
        <td><?= e((string) $item['id']) ?></td>
        <td><b><?= e($item['name']) ?></b></td>
        <td dir="ltr" style="text-align:left"><?= e($item['slug']) ?></td>
        <td><?= e($typeLabels[$item['type']] ?? $item['type']) ?></td>
        <td><?= !empty($item['is_default']) ? 'بله' : '—' ?></td>
        <td style="white-space:nowrap">
          <a href="/admin/builder/template/<?= e((string) $item['id']) ?>">ویرایش با بیلدر</a>
          |
          <a href="/admin/templates/<?= e((string) $item['id']) ?>/edit">مشخصات</a>
          |
          <form method="post" action="/admin/templates/<?= e((string) $item['id']) ?>/delete" style="display:inline" onsubmit="return confirm('حذف شود؟')">
            <?= $csrf->field() ?>
            <button type="submit" style="background:none;border:none;color:#a32b2b;cursor:pointer;padding:0;font:inherit">حذف</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (empty($items)): ?><tr><td colspan="6">قالبی یافت نشد.</td></tr><?php endif; ?>
  </table>
</div>
