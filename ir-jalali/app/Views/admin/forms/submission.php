<?php
$__layout = 'layouts.admin';
$active = 'forms';
$title = 'مشاهده پاسخ #' . ($submission['id'] ?? '');
?>
<div class="panel">
  <h3><?= e($title) ?> <a href="/admin/forms/<?= e((string) ($form['id'] ?? '')) ?>/submissions">بازگشت</a></h3>
  <p style="color:var(--muted);font-size:12px">
    تاریخ: <?= e($dates->format($submission['created_at'] ?? '')) ?>
    <?php if (!empty($submission['ip'])): ?> | آی‌پی: <span dir="ltr"><?= e($submission['ip']) ?></span><?php endif; ?>
  </p>
  <table class="tbl">
    <tr><th>فیلد</th><th>مقدار</th></tr>
    <?php foreach (($answers ?? []) as $key => $value): ?>
      <tr>
        <td dir="ltr" style="text-align:left"><b><?= e((string) $key) ?></b></td>
        <td><?= nl2br(e((string) $value)) ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
</div>
