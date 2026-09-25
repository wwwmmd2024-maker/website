<?php
$__layout = 'layouts.admin';
$active = 'plugins';
$title = $t->get('plugins.title');
?>
<div class="panel">
  <h3>نصب افزونه جدید (بارگذاری ZIP)</h3>
  <form method="post" action="/admin/plugins/upload" enctype="multipart/form-data" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">
    <?= $csrf->field() ?? '' ?>
    <input type="file" name="package" accept=".zip" required style="max-width:340px">
    <button class="btn small" type="submit">بارگذاری و نصب</button>
    <span style="color:var(--muted);font-size:12px">بسته قبل از نصب اعتبارسنجی، سازگاری و اسکن امنیتی می‌شود.</span>
  </form>
</div>

<div class="panel">
  <h3>افزونه‌های نصب‌شده (<?= e((string) count($items ?? [])) ?>)</h3>
  <?php if (empty($items)): ?>
    <p style="color:var(--muted)">هیچ افزونه‌ای نصب نیست. از مارکت‌پلیس یا بارگذاری ZIP نصب کنید.</p>
  <?php else: ?>
    <?php foreach ($items as $item): ?>
      <div class="panel" style="border:1px solid var(--border);margin-bottom:12px">
        <div style="display:flex;justify-content:space-between;gap:14px;flex-wrap:wrap">
          <div style="min-width:260px">
            <b><?= e($item['name']) ?></b>
            <span dir="ltr" style="color:var(--muted);font-size:12px">v<?= e($item['version']) ?></span>
            <?php if ($item['active']): ?><span class="badge green">فعال</span><?php else: ?><span class="badge">غیرفعال</span><?php endif; ?>
            <?php if ($item['must_use']): ?><span class="badge red">ضروری</span><?php endif; ?>
            <?php if (!empty($item['broken'])): ?><span class="badge red">وابستگی ناقص</span><?php endif; ?>
            <p style="color:var(--muted);font-size:12.5px;margin-top:6px"><?= e($item['description']) ?></p>
            <?php if (!empty($item['dependencies'])): ?>
              <p style="font-size:12px;color:var(--muted);margin-top:4px">وابستگی‌ها: <span dir="ltr"><?= e(implode(', ', $item['dependencies'])) ?></span></p>
            <?php endif; ?>
            <?php if (!empty($item['release']['has_update'])): ?>
              <p style="font-size:12px;margin-top:4px"><span class="badge">نسخه جدید <?= e($item['release']['version']) ?></span></p>
            <?php endif; ?>
          </div>
          <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-start">
            <?php foreach ($item['adminPages'] as $page): ?>
              <a class="btn small" style="background:#0891b2" href="/admin/plugin/<?= e($page['slug']) ?>"><?= e($page['title']) ?></a>
            <?php endforeach; ?>
            <?php if ($item['active']): ?>
              <form method="post" action="/admin/plugins/<?= e($item['slug']) ?>/deactivate"><?= $csrf->field() ?? '' ?><button class="btn small" style="background:#64748b" type="submit">غیرفعال‌سازی</button></form>
              <?php if (!empty($item['release']['has_update'])): ?>
                <form method="post" action="/admin/plugins/<?= e($item['slug']) ?>/update"><?= $csrf->field() ?? '' ?><button class="btn small" type="submit">به‌روزرسانی</button></form>
              <?php endif; ?>
            <?php elseif (!$item['must_use']): ?>
              <form method="post" action="/admin/plugins/<?= e($item['slug']) ?>/activate"><?= $csrf->field() ?? '' ?><button class="btn small" type="submit">فعال‌سازی</button></form>
              <form method="post" action="/admin/plugins/<?= e($item['slug']) ?>/uninstall" data-confirm="افزونه به همراه فایل‌ها و تنظیمات حذف شود؟"><?= $csrf->field() ?? '' ?><button class="btn small danger" type="submit">حذف کامل</button></form>
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
<p style="color:var(--muted);font-size:12.5px">چرخه حیات: نصب ← فعال‌سازی ← به‌روزرسانی (با پشتیبان‌گیری خودکار و بازگشت در صورت خطا) ← غیرفعال‌سازی ← حذف. غیرفعال‌سازی هرگز داده‌ها را حذف نمی‌کند.</p>
