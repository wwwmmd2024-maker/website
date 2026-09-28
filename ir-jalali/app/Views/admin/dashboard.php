<?php
$__layout = 'layouts.admin';
$active = 'dashboard';
$title = $t->get('dashboard.title');
$stats = $stats ?? [];
?>
<div class="cards">
  <div class="card"><div class="num"><?= e((string) ($stats['users'] ?? 0)) ?></div><div class="lbl"><?= e($t->get('dashboard.users')) ?></div></div>
  <div class="card"><div class="num"><?= e((string) ($stats['pages'] ?? 0)) ?></div><div class="lbl"><?= e($t->get('dashboard.pages')) ?></div></div>
  <div class="card"><div class="num"><?= e((string) ($stats['posts'] ?? 0)) ?></div><div class="lbl"><?= e($t->get('dashboard.posts')) ?></div></div>
  <div class="card"><div class="num"><?= e((string) ($stats['media'] ?? 0)) ?></div><div class="lbl"><?= e($t->get('dashboard.media')) ?></div></div>
</div>
<?php if (!empty($widgets ?? [])): ?>
<div class="grid2" style="margin-bottom:18px">
  <?php foreach ($widgets as $widget): ?>
    <div class="panel" <?= ($widget['size'] ?? 'half') === 'full' ? 'style="grid-column:1/-1"' : '' ?>>
      <h3><?= e($widget['title']) ?></h3>
      <?= $widget['html'] ?>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
<div class="grid2">
  <div class="panel">
    <h3>وضعیت سیستم</h3>
    <table class="tbl">
      <tr><th><?= e($t->get('dashboard.mode')) ?></th><td><span class="badge"><?= e($mode ?? 'general') ?></span></td></tr>
      <tr><th>نوع وب‌سایت</th><td><span class="badge green"><?= e($websiteType ?? 'custom') ?></span></td></tr>
      <tr><th><?= e($t->get('dashboard.version')) ?></th><td dir="ltr"><?= e($version ?? '1.0.0') ?></td></tr>
      <tr><th>نسخه PHP</th><td dir="ltr"><?= e($phpVersion ?? '') ?></td></tr>
      <tr><th>تقویم</th><td>شمسی (جلالی) · Asia/Tehran</td></tr>
    </table>
  </div>
  <div class="panel">
    <h3><?= e($t->get('dashboard.recent_logs')) ?></h3>
    <?php if (empty($recentLogs)): ?>
      <p style="color:var(--muted)">رویدادی ثبت نشده است.</p>
    <?php else: ?>
      <table class="tbl">
        <?php foreach ($recentLogs as $log): ?>
          <tr>
            <td dir="ltr" style="font-size:12px"><?= e($log['event']) ?></td>
            <td dir="ltr" style="font-size:12px;color:var(--muted)"><?= e($log['ip'] ?? '') ?></td>
            <td style="font-size:12px;color:var(--muted)"><?= e($dates->format($log['created_at'])) ?></td>
          </tr>
        <?php endforeach; ?>
      </table>
    <?php endif; ?>
  </div>
</div>
