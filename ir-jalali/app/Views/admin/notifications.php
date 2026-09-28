<?php
$__layout = 'layouts.admin';
$active = 'notifications';
$title = $t->get('notifications.title');
$types = ['user.new' => 'کاربر جدید', 'form.new' => 'فرم جدید', 'order.new' => 'سفارش جدید', 'booking.new' => 'رزرو جدید', 'security.alert' => 'هشدار امنیتی', 'plugin.update' => 'افزونه', 'theme.update' => 'قالب', 'core.update' => 'هسته', 'system.error' => 'خطای سیستم'];
?>
<div class="toolbar">
  <form method="post" action="/admin/notifications/read-all" class="inline-form"><?= $csrf->field() ?? '' ?><button class="btn small" type="submit">علامت‌گذاری همه به عنوان خوانده‌شده</button></form>
  <form method="post" action="/admin/notifications/clear-read" class="inline-form" data-confirm="اعلان‌های خوانده‌شده حذف شوند؟"><?= $csrf->field() ?? '' ?><button class="btn small danger" type="submit">پاک‌سازی خوانده‌شده‌ها</button></form>
  <span class="muted"><?= e((string) ($unread ?? 0)) ?> خوانده‌نشده</span>
</div>

<div class="panel">
  <?php if (empty($items)): ?>
    <p style="color:var(--muted)">اعلانی وجود ندارد.</p>
  <?php else: ?>
    <table class="tbl">
      <tr><th>نوع</th><th>عنوان</th><th>متن</th><th>زمان</th><th></th></tr>
      <?php foreach ($items as $n): ?>
        <tr style="<?= empty($n['read_at']) ? 'background:#f8fafc' : '' ?>">
          <td><span class="badge"><?= e($types[$n['type']] ?? $n['type']) ?></span></td>
          <td><b><?= e($n['title']) ?></b></td>
          <td class="muted" style="font-size:12.5px"><?= e(mb_substr((string) $n['body'], 0, 120)) ?></td>
          <td style="font-size:12px" class="muted"><?= e($dates->format((string) $n['created_at'])) ?></td>
          <td style="white-space:nowrap">
            <?php if (!empty($n['url'])): ?><a class="btn small" style="background:#0891b2" href="<?= e((string) $n['url']) ?>">مشاهده</a><?php endif; ?>
            <?php if (empty($n['read_at'])): ?>
              <form method="post" action="/admin/notifications/<?= e((string) $n['id']) ?>/read" class="inline-form"><?= $csrf->field() ?? '' ?><button class="btn small" type="submit">خواندم</button></form>
            <?php endif; ?>
            <form method="post" action="/admin/notifications/<?= e((string) $n['id']) ?>/delete" class="inline-form"><?= $csrf->field() ?? '' ?><button class="btn small danger" type="submit">حذف</button></form>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
  <?php endif; ?>
</div>
