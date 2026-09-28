<?php
$__layout = 'layouts.admin';
$active = 'widgets';
$title = $t->get('widgets.title');
$bySidebar = [];
foreach ($instances ?? [] as $instance) {
    $bySidebar[$instance['sidebar']][] = $instance;
}
?>
<div class="panel">
  <h3>افزودن ویجت</h3>
  <form method="post" action="/admin/widgets">
    <?= $csrf->field() ?? '' ?>
    <div class="toolbar" style="margin-bottom:0">
      <select name="widget" required style="max-width:240px">
        <?php foreach ($available as $w): ?>
          <option value="<?= e($w->slug) ?>"><?= e($w->icon) ?> <?= e($w->title) ?> — <?= e($w->slug) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="sidebar" style="max-width:180px">
        <?php foreach ($sidebars as $key => $label): ?>
          <option value="<?= e($key) ?>"><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
      <input type="text" name="title" placeholder="عنوان ویجت (اختیاری)" style="max-width:220px">
      <button class="btn small" type="submit">افزودن</button>
    </div>
  </form>
</div>

<?php foreach ($sidebars as $sidebarKey => $sidebarLabel): ?>
  <div class="panel">
    <h3><?= e($sidebarLabel) ?></h3>
    <?php $rows = $bySidebar[$sidebarKey] ?? []; ?>
    <?php if ($rows === []): ?>
      <p class="muted">ویجتی در این ناحیه نیست.</p>
    <?php endif; ?>
    <?php foreach ($rows as $row): ?>
      <div class="panel" style="border:1px solid var(--border);padding:14px">
        <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;align-items:center">
          <div>
            <b><?= e($row['widgetTitle']) ?></b>
            <?php if ($row['title'] !== ''): ?><span class="badge"><?= e($row['title']) ?></span><?php endif; ?>
            <?php if (!$row['available']): ?><span class="badge red">ویجت غیرفعال/حذف‌شده</span><?php endif; ?>
            <span class="muted" dir="ltr" style="font-size:11px"><?= e($row['slug']) ?></span>
          </div>
          <div style="display:flex;gap:6px;flex-wrap:wrap">
            <form method="post" action="/admin/widgets/<?= e((string) $row['id']) ?>/move" class="inline-form"><?= $csrf->field() ?? '' ?><input type="hidden" name="dir" value="up"><button class="btn small" style="background:#64748b" type="submit">↑</button></form>
            <form method="post" action="/admin/widgets/<?= e((string) $row['id']) ?>/move" class="inline-form"><?= $csrf->field() ?? '' ?><input type="hidden" name="dir" value="down"><button class="btn small" style="background:#64748b" type="submit">↓</button></form>
            <form method="post" action="/admin/widgets/<?= e((string) $row['id']) ?>/delete" class="inline-form" data-confirm="ویجت حذف شود؟"><?= $csrf->field() ?? '' ?><button class="btn small danger" type="submit">حذف</button></form>
          </div>
        </div>
        <?php if (!empty($row['schema'])): ?>
          <form method="post" action="/admin/widgets/<?= e((string) $row['id']) ?>">
            <?= $csrf->field() ?? '' ?>
            <input type="hidden" name="title" value="<?= e($row['title']) ?>">
            <?php foreach ($row['schema'] as $field): ?>
              <label><?= e((string) ($field['label'] ?? $field['key'])) ?> <span class="muted" dir="ltr">(<?= e((string) ($field['key'] ?? '')) ?>)</span></label>
              <input type="<?= in_array($field['type'] ?? 'text', ['number', 'email', 'url'], true) ? e((string) $field['type']) : 'text' ?>" name="w_<?= e((string) $field['key']) ?>" value="<?= e((string) ($row['data'][$field['key']] ?? '')) ?>">
            <?php endforeach; ?>
            <button class="btn small" type="submit">ذخیره تنظیمات</button>
          </form>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
<?php endforeach; ?>
<p class="muted" style="font-size:12.5px">ویجت‌های ثبت‌شده توسط افزونه‌ها پس از فعال‌سازی افزونه، به‌صورت خودکار در این فهرست ظاهر می‌شوند.</p>
