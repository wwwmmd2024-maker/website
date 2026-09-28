<?php
$__layout = 'layouts.admin';
$active = 'forms';
$title = 'پاسخ‌های فرم: ' . ($form['title'] ?? '');
$pages = max(1, (int) ceil(($total ?? 0) / ($perPage ?? 20)));
?>
<div class="panel">
  <h3><?= e($title) ?> (<?= e($dates->toPersianDigits((string) ($total ?? 0))) ?>) <a href="/admin/forms">بازگشت</a></h3>
  <table class="tbl">
    <tr><th>#</th><th>خلاصه</th><th>وضعیت</th><th>تاریخ</th><th>اقدامات</th></tr>
    <?php foreach (($submissions ?? []) as $sub): ?>
      <?php
      $data = json_decode((string) $sub['data_json'], true) ?: [];
      $preview = mb_substr(implode('، ', array_slice(array_map('strval', array_values($data)), 0, 3)), 0, 80);
      ?>
      <tr>
        <td><?= e((string) $sub['id']) ?></td>
        <td><?= e($preview !== '' ? $preview : '—') ?></td>
        <td><?= !empty($sub['is_read']) ? 'خوانده‌شده' : '<b>تازه</b>' ?></td>
        <td><?= e($dates->format($sub['created_at'])) ?></td>
        <td style="white-space:nowrap">
          <a href="/admin/forms/<?= e((string) $form['id']) ?>/submissions/<?= e((string) $sub['id']) ?>">مشاهده</a>
          |
          <form method="post" action="/admin/forms/<?= e((string) $form['id']) ?>/submissions/<?= e((string) $sub['id']) ?>/delete" style="display:inline" onsubmit="return confirm('حذف شود؟')">
            <?= $csrf->field() ?>
            <button type="submit" style="background:none;border:none;color:#a32b2b;cursor:pointer;padding:0;font:inherit">حذف</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (empty($submissions)): ?><tr><td colspan="5">پاسخی ثبت نشده است.</td></tr><?php endif; ?>
  </table>
  <?php if ($pages > 1): ?>
    <div style="margin-top:12px;display:flex;gap:6px">
      <?php for ($p = 1; $p <= $pages; $p++): ?>
        <a class="btn" style="text-decoration:none" href="/admin/forms/<?= e((string) $form['id']) ?>/submissions?page=<?= $p ?>"><?= e($dates->toPersianDigits((string) $p)) ?></a>
      <?php endfor; ?>
    </div>
  <?php endif; ?>
</div>
