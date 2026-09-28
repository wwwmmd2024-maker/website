<?php
$__layout = 'layouts.admin';
$active = 'fields';
$title = $t->get('fields.title');
?>
<div class="panel">
  <h3>ساخت گروه فیلد جدید</h3>
  <form method="post" action="/admin/fields/groups" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
    <?= $csrf->field() ?? '' ?>
    <div><label>عنوان گروه</label><input type="text" name="title" required></div>
    <div><label>محل نمایش</label>
      <select name="post_type">
        <option value="*">همه انواع محتوا</option>
        <?php foreach ($postTypes ?? [] as $pt): ?>
          <option value="<?= e($pt['slug']) ?>"><?= e($pt['name']) ?> (<?= e($pt['slug']) ?>)</option>
        <?php endforeach; ?>
      </select>
    </div>
    <button class="btn small" type="submit" style="margin-bottom:2px">ایجاد گروه</button>
  </form>
</div>

<?php if (empty($groups)): ?>
  <div class="panel"><p class="muted">هنوز گروه فیلدی ساخته نشده است.</p></div>
<?php endif; ?>

<?php foreach ($groups as $group): ?>
  <div class="panel">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
      <h3 style="margin:0">⌗ <?= e($group['title']) ?></h3>
      <form method="post" action="/admin/fields/groups/<?= e((string) $group['id']) ?>/delete" class="inline-form" data-confirm="این گروه و فیلدهایش حذف شود؟">
        <?= $csrf->field() ?? '' ?><button class="btn small danger" type="submit">حذف گروه</button>
      </form>
    </div>
    <?php $rules = json_decode((string) ($group['location_rules'] ?? '[]'), true) ?: []; ?>
    <p class="muted" style="font-size:12px">محل نمایش: <?= $rules === [] ? 'همه انواع محتوا' : e(($rules[0]['value'] ?? '?')) ?></p>

    <table class="tbl">
      <tr><th>کلید</th><th>برچسب</th><th>نوع</th><th>تنظیمات</th><th></th></tr>
      <?php foreach ($group['fields'] as $f): ?>
        <tr>
          <td dir="ltr"><b><?= e($f['key']) ?></b></td>
          <td><?= e($f['label']) ?></td>
          <td><span class="badge" dir="ltr"><?= e($f['type']) ?></span></td>
          <td class="muted" dir="ltr" style="font-size:11.5px;max-width:280px;overflow:hidden;text-overflow:ellipsis"><?= e(mb_substr((string) ($f['settings'] ?? ''), 0, 120)) ?></td>
          <td>
            <form method="post" action="/admin/fields/<?= e((string) $f['id']) ?>/delete" class="inline-form"><?= $csrf->field() ?? '' ?><button class="btn small danger" type="submit">حذف</button></form>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>

    <form method="post" action="/admin/fields/groups/<?= e((string) $group['id']) ?>/fields" class="toolbar" style="margin-top:12px">
      <?= $csrf->field() ?? '' ?>
      <input type="text" name="key" placeholder="کلید (لاتین)" required style="max-width:140px" dir="ltr">
      <input type="text" name="label" placeholder="برچسب" required style="max-width:160px">
      <select name="type" style="max-width:170px">
        <?php foreach ($types as $ft): ?><option value="<?= e($ft) ?>" dir="ltr"><?= e($ft) ?></option><?php endforeach; ?>
      </select>
      <input type="text" name="choices" placeholder="گزینه‌ها (برای select/radio/checkbox — با کاما)" style="max-width:230px">
      <label style="display:flex;gap:6px;align-items:center;font-weight:400;margin:0"><input type="checkbox" name="required" value="1"> الزامی</label>
      <button class="btn small" type="submit">افزودن فیلد</button>
    </form>
  </div>
<?php endforeach; ?>
<p class="muted" style="font-size:12.5px">۳۰ نوع فیلد پشتیبانی می‌شود: متن، عدد، تاریخ، تصویر، گالری، فایل، انتخابی، رنگ، تکرارشونده، گروه، رابطه، انتخابگر نوشته/کاربر/تاکسونومی، نقشه، کد، JSON و… مقادیر در متای محتوا ذخیره می‌شوند.</p>
