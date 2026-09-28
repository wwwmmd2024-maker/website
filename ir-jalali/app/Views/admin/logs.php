<?php
$__layout = 'layouts.admin';
$active = 'logs';
$title = $t->get('logs.title');
$tab = $tab ?? 'security';
?>
<div class="logtabs">
  <a href="/admin/logs?tab=security" class="<?= $tab === 'security' ? 'active' : '' ?>">رویدادهای امنیتی</a>
  <a href="/admin/logs?tab=audit" class="<?= $tab === 'audit' ? 'active' : '' ?>">حسابرسی مدیریت</a>
  <a href="/admin/logs?tab=files" class="<?= $tab === 'files' ? 'active' : '' ?>">فایل‌های لاگ</a>
</div>
<div class="panel">
  <?php if ($tab === 'files'): ?>
    <form method="get" action="/admin/logs" style="margin-bottom:14px">
      <input type="hidden" name="tab" value="files">
      <label style="display:inline;margin-left:8px"><?= e($t->get('logs.channel')) ?></label>
      <select name="channel" onchange="this.form.submit()" style="max-width:220px">
        <?php foreach (($channels ?? []) as $ch): ?>
          <option value="<?= e($ch) ?>" <?= ($channel ?? '') === $ch ? 'selected' : '' ?>><?= e($ch) ?></option>
        <?php endforeach; ?>
      </select>
    </form>
    <?php if (empty($lines)): ?>
      <p style="color:var(--muted)"><?= e($t->get('logs.empty')) ?></p>
    <?php else: ?>
      <pre class="logs"><?php foreach (($lines ?? []) as $line): ?><?= e($line) . "\n" ?><?php endforeach; ?></pre>
    <?php endif; ?>
  <?php elseif ($tab === 'audit'): ?>
    <?php if (empty($rows)): ?><p style="color:var(--muted)"><?= e($t->get('logs.empty')) ?></p>
    <?php else: ?>
      <table class="tbl">
        <tr><th>#</th><th>کاربر</th><th>اقدام</th><th>موجودیت</th><th>IP</th><th>زمان</th></tr>
        <?php foreach ($rows as $row): ?>
          <tr>
            <td><?= e((string) $row['id']) ?></td>
            <td><?= e((string) ($row['user_id'] ?? '—')) ?></td>
            <td dir="ltr"><?= e($row['action']) ?></td>
            <td dir="ltr"><?= e(($row['entity_type'] ?? '') . ($row['entity_id'] ? '#' . $row['entity_id'] : '')) ?></td>
            <td dir="ltr"><?= e($row['ip'] ?? '') ?></td>
            <td><?= e($dates->formatFull($row['created_at'])) ?></td>
          </tr>
        <?php endforeach; ?>
      </table>
    <?php endif; ?>
  <?php else: ?>
    <?php if (empty($rows)): ?><p style="color:var(--muted)"><?= e($t->get('logs.empty')) ?></p>
    <?php else: ?>
      <table class="tbl">
        <tr><th>#</th><th>رویداد</th><th>کاربر</th><th>IP</th><th>زمان</th></tr>
        <?php foreach ($rows as $row): ?>
          <tr>
            <td><?= e((string) $row['id']) ?></td>
            <td dir="ltr"><span class="badge <?= str_contains($row['event'], 'failed') || str_contains($row['event'], 'throttled') ? 'red' : 'green' ?>"><?= e($row['event']) ?></span></td>
            <td><?= e((string) ($row['user_id'] ?? '—')) ?></td>
            <td dir="ltr"><?= e($row['ip'] ?? '') ?></td>
            <td><?= e($dates->formatFull($row['created_at'])) ?></td>
          </tr>
        <?php endforeach; ?>
      </table>
    <?php endif; ?>
  <?php endif; ?>
</div>
