<?php
$__layout = 'layouts.admin';
$active = 'types';
$title = $t->get('types.title');
?>
<div class="panel">
  <h3>ساخت نوع نوشته سفارشی (CPT)</h3>
  <form method="post" action="/admin/types">
    <?= $csrf->field() ?? '' ?>
    <div class="grid2">
      <div>
        <label>نام (مثلاً: محصولات، پزشکان، املاک)</label>
        <input type="text" name="name" required>
        <label>شناسه (لاتین، مثل product)</label>
        <input type="text" name="slug" required dir="ltr">
        <label>آیکون</label>
        <input type="text" name="icon" value="post" dir="ltr">
        <label>تاکسونومی‌ها (با کاما جدا کنید)</label>
        <input type="text" name="taxonomies" dir="ltr" placeholder="category, tag">
        <label>قالب‌ها (با کاما جدا کنید)</label>
        <input type="text" name="templates" dir="ltr" placeholder="single, archive">
      </div>
      <div>
        <label>پشتیبانی از</label>
        <?php foreach ($supports as $s): ?>
          <label style="font-weight:400;display:flex;gap:8px;align-items:center">
            <input type="checkbox" name="supports[]" value="<?= e($s) ?>" <?= in_array($s, ['title', 'editor'], true) ? 'checked' : '' ?>>
            <span dir="ltr"><?= e($s) ?></span>
          </label>
        <?php endforeach; ?>
        <label style="font-weight:400;display:flex;gap:8px;align-items:center"><input type="checkbox" name="archive" value="1" checked> صفحه آرشیو</label>
        <label style="font-weight:400;display:flex;gap:8px;align-items:center"><input type="checkbox" name="single" value="1" checked> صفحه تکی</label>
        <label style="font-weight:400;display:flex;gap:8px;align-items:center"><input type="checkbox" name="rest_api" value="1" checked> در REST API</label>
      </div>
    </div>
    <button class="btn" type="submit">ایجاد نوع نوشته</button>
  </form>
</div>

<div class="panel">
  <h3>انواع نوشته (<?= e((string) count($types ?? [])) ?>)</h3>
  <table class="tbl">
    <tr><th>شناسه</th><th>نام</th><th>منبع</th><th>پشتیبانی</th><th></th></tr>
    <?php foreach ($types as $type): ?>
      <?php $settings = json_decode((string) ($type['settings'] ?? '{}'), true) ?: []; ?>
      <tr>
        <td dir="ltr"><b><?= e($type['slug']) ?></b></td>
        <td><?= e($type['name']) ?> <?= e((string) ($type['icon'] ?? '')) ?></td>
        <td><span class="badge"><?= (int) $type['is_system'] === 1 ? 'سیستمی' : (($settings['source'] ?? 'admin')) ?></span></td>
        <td class="muted" dir="ltr" style="font-size:11.5px"><?= e(implode(', ', json_decode((string) ($type['supports'] ?? '[]'), true) ?: [])) ?></td>
        <td style="white-space:nowrap">
          <?php if ((int) $type['is_system'] !== 1): ?>
            <a class="btn small" style="background:#0891b2" href="/admin/cpt/<?= e($type['slug']) ?>">محتوا</a>
            <form method="post" action="/admin/types/<?= e((string) $type['id']) ?>/delete" class="inline-form" data-confirm="این نوع نوشته حذف شود؟ محتوای آن حفظ می‌ماند.">
              <?= $csrf->field() ?? '' ?><button class="btn small danger" type="submit">حذف</button>
            </form>
          <?php else: ?>
            <span class="muted" style="font-size:12px">غیرقابل حذف</span>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>
</div>
