<?php
$__layout = 'layouts.admin';
$active = 'forms';
$title = 'فرم‌ها';
?>
<div class="panel">
  <h3>فرم‌ها <a class="btn" href="/admin/forms/create" style="text-decoration:none">+ فرم تازه</a></h3>
  <table class="tbl">
    <tr><th>#</th><th>عنوان</th><th>نامک</th><th>فعال</th><th>نخوانده</th><th>اقدامات</th></tr>
    <?php foreach (($forms ?? []) as $form): ?>
      <tr>
        <td><?= e((string) $form['id']) ?></td>
        <td><b><?= e($form['title']) ?></b></td>
        <td dir="ltr" style="text-align:left"><?= e($form['slug']) ?></td>
        <td><?= !empty($form['is_active']) ? 'بله' : 'خیر' ?></td>
        <td><?= e($dates->toPersianDigits((string) ($form['unread'] ?? 0))) ?></td>
        <td style="white-space:nowrap">
          <a href="/admin/forms/<?= e((string) $form['id']) ?>/submissions">پاسخ‌ها</a>
          |
          <a href="/admin/forms/<?= e((string) $form['id']) ?>/fields">فیلدها</a>
          |
          <a href="/admin/forms/<?= e((string) $form['id']) ?>/edit">مشخصات</a>
          |
          <form method="post" action="/admin/forms/<?= e((string) $form['id']) ?>/delete" style="display:inline" onsubmit="return confirm('فرم و همه پاسخ‌هایش حذف شود؟')">
            <?= $csrf->field() ?>
            <button type="submit" style="background:none;border:none;color:#a32b2b;cursor:pointer;padding:0;font:inherit">حذف</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (empty($forms)): ?><tr><td colspan="6">فرمی وجود ندارد.</td></tr><?php endif; ?>
  </table>
</div>
