<?php
$__layout = 'layouts.admin';
$active = 'themes';
$title = $t->get('themes.title');
?>
<div class="panel">
  <h3>نصب قالب جدید (بارگذاری ZIP)</h3>
  <form method="post" action="/admin/themes/upload" enctype="multipart/form-data" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">
    <?= $csrf->field() ?? '' ?>
    <input type="file" name="package" accept=".zip" required style="max-width:340px">
    <button class="btn small" type="submit">بارگذاری و نصب</button>
    <span style="color:var(--muted);font-size:12px">قالب قبل از نصب اسکن امنیتی و بررسی سازگاری می‌شود.</span>
  </form>
</div>

<div class="mgrid">
  <?php foreach ($items as $item): ?>
    <div class="mitem" style="display:flex;flex-direction:column">
      <?php if ($item['screenshot']): ?>
        <img src="<?= e($item['screenshot']) ?>" alt="<?= e($item['name']) ?>">
      <?php else: ?>
        <div class="fileph">▦</div>
      <?php endif; ?>
      <div class="meta" style="flex:1">
        <b><?= e($item['name']) ?></b>
        <span dir="ltr">v<?= e($item['version']) ?></span>
        <?php if ($item['active']): ?><span class="badge green">فعال</span><?php endif; ?>
        <?php if ($item['child']): ?><span class="badge">فرزندِ <?= e((string) $item['parent']) ?></span><?php endif; ?>
        <?php if (!$item['compatible']): ?><span class="badge red">ناسازگار</span><?php endif; ?>
        <?php if ($item['has_update']): ?><span class="badge">آپدیت دارد</span><?php endif; ?>
        <div style="margin-top:6px;color:var(--muted);font-size:11.5px"><?= e($item['description']) ?></div>
      </div>
      <div style="display:flex;gap:6px;padding:10px;flex-wrap:wrap">
        <?php if (!$item['active'] && $item['compatible']): ?>
          <form method="post" action="/admin/themes/<?= e($item['slug']) ?>/activate"><?= $csrf->field() ?? '' ?><button class="btn small" type="submit">فعال‌سازی</button></form>
        <?php endif; ?>
        <?php if (!$item['active']): ?>
          <form method="post" action="/admin/themes/<?= e($item['slug']) ?>/remove" data-confirm="این قالب حذف شود؟"><?= $csrf->field() ?? '' ?><button class="btn small danger" type="submit">حذف</button></form>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<p style="color:var(--muted);font-size:12.5px;margin-top:10px">قالب فقط «نمایش» را کنترل می‌کند؛ محتوا، کاربران، سفارش‌ها و رزروها مستقل از قالب‌اند و با تعویض قالب از بین نمی‌روند.</p>
