<?php
$__layout = 'layouts.admin';
$active = 'backups';
$title = $t->get('backups.title');
?>
<div class="toolbar">
  <form method="post" action="/admin/backups/create" class="inline-form">
    <?= $csrf->field() ?? '' ?>
    <button class="btn small" type="submit">＋ پشتیبان کامل جدید</button>
  </form>
  <form method="post" action="/admin/backups/schedule" class="inline-form">
    <?= $csrf->field() ?? '' ?>
    <?php if (!empty($scheduled)): ?>
      <button class="btn small danger" type="submit">غیرفعال‌سازی پشتیبان خودکار</button>
    <?php else: ?>
      <button class="btn small" style="background:#059669" type="submit" name="enable" value="1">فعال‌سازی پشتیبان خودکار روزانه</button>
    <?php endif; ?>
  </form>
  <span class="muted" style="font-size:12.5px">پشتیبان کامل = دیتابیس + آپلودها + قالب‌ها + افزونه‌ها (با مانیفست و checksum)</span>
</div>

<div class="panel">
  <h3>پشتیبان‌های سایت (<?= e((string) count($backups ?? [])) ?>)</h3>
  <?php if (empty($backups)): ?>
    <p style="color:var(--muted)">هنوز پشتیبانی گرفته نشده است.</p>
  <?php else: ?>
    <table class="tbl">
      <tr><th>فایل</th><th>حجم</th><th>تاریخ</th><th>دلیل</th><th>عملیات</th></tr>
      <?php foreach ($backups as $b): ?>
        <tr>
          <td dir="ltr" style="font-size:12px"><?= e($b['name']) ?></td>
          <td><?= e(round($b['size'] / 1024) . ' KB') ?></td>
          <td><?= e($dates->format($b['created'])) ?></td>
          <td><span class="badge"><?= e($b['reason']) ?></span></td>
          <td style="white-space:nowrap">
            <a class="btn small" style="background:#0891b2" href="/admin/backups/<?= e($b['name']) ?>/download">دانلود</a>
            <form method="post" action="/admin/backups/<?= e($b['name']) ?>/restore" class="inline-form" data-confirm="بازیابی این پشتیبان، وضعیت فعلی سایت را جایگزین می‌کند. ادامه می‌دهید؟">
              <?= $csrf->field() ?? '' ?>
              <button class="btn small" type="submit">بازیابی</button>
            </form>
            <form method="post" action="/admin/backups/<?= e($b['name']) ?>/delete" class="inline-form" data-confirm="این پشتیبان حذف شود؟">
              <?= $csrf->field() ?? '' ?>
              <button class="btn small danger" type="submit">حذف</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
  <?php endif; ?>
</div>
<p class="muted" style="font-size:12.5px">بازیابی شامل بررسی یکپارچگی (checksum)، بررسی نسخه و فایل‌هاست؛ قبل از هر بازیابی، وضعیت فعلی نیز پشتیبان‌گیری می‌شود.</p>
