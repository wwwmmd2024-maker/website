<?php
$__layout = 'layouts.admin';
$active = 'forms';
$title = 'فیلدهای فرم: ' . ($form['title'] ?? '');
$typeLabels = ['text' => 'متن کوتاه', 'email' => 'ایمیل', 'number' => 'عدد', 'textarea' => 'متن بلند', 'select' => 'کشویی', 'checkbox' => 'گزینه (چک‌باکس)'];
?>
<div class="panel">
  <h3><?= e($title) ?> <a href="/admin/forms">بازگشت</a></h3>
  <table class="tbl">
    <tr><th>برچسب</th><th>کلید</th><th>نوع</th><th>الزامی</th><th>ترتیب</th><th>اقدامات</th></tr>
    <?php foreach (($fields ?? []) as $field): ?>
      <?php $settings = json_decode((string) ($field['settings'] ?? '{}'), true) ?: []; ?>
      <tr>
        <td><b><?= e($field['label']) ?></b></td>
        <td dir="ltr" style="text-align:left"><?= e($field['key']) ?></td>
        <td><?= e($typeLabels[$field['type']] ?? $field['type']) ?></td>
        <td><?= !empty($settings['required']) ? 'بله' : '—' ?></td>
        <td style="white-space:nowrap">
          <form method="post" action="/admin/forms/<?= e((string) $form['id']) ?>/fields/<?= e((string) $field['id']) ?>/move" style="display:inline">
            <?= $csrf->field() ?><input type="hidden" name="move" value="up"><button type="submit" title="بالا">↑</button>
          </form>
          <form method="post" action="/admin/forms/<?= e((string) $form['id']) ?>/fields/<?= e((string) $form['id']) ?>/fields/<?= e((string) $field['id']) ?>/move" style="display:inline">
            <?= $csrf->field() ?><input type="hidden" name="move" value="down"><button type="submit" title="پایین">↓</button>
          </form>
        </td>
        <td>
          <form method="post" action="/admin/forms/<?= e((string) $form['id']) ?>/fields/<?= e((string) $field['id']) ?>/delete" style="display:inline" onsubmit="return confirm('حذف شود؟')">
            <?= $csrf->field() ?>
            <button type="submit" style="background:none;border:none;color:#a32b2b;cursor:pointer;padding:0;font:inherit">حذف</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (empty($fields)): ?><tr><td colspan="6">هنوز فیلدی ندارد.</td></tr><?php endif; ?>
  </table>
</div>
<div class="panel">
  <h3>افزودن فیلد</h3>
  <form method="post" action="/admin/forms/<?= e((string) $form['id']) ?>/fields">
    <?= $csrf->field() ?>
    <label>برچسب (فارسی)</label>
    <input type="text" name="label" required maxlength="150">
    <label>کلید لاتین (مثل phone — بدون فاصله)</label>
    <input type="text" name="key" dir="ltr" required maxlength="100">
    <label>نوع</label>
    <select name="type" id="ij-field-type" onchange="document.getElementById('ij-opts').style.display=this.value==='select'?'':'none'">
      <?php foreach (($fieldTypes ?? []) as $ft): ?>
        <option value="<?= e($ft) ?>"><?= e($typeLabels[$ft] ?? $ft) ?></option>
      <?php endforeach; ?>
    </select>
    <div id="ij-opts" style="display:none">
      <label>گزینه‌ها (هر خط یک گزینه)</label>
      <textarea name="options" rows="4" dir="auto"></textarea>
    </div>
    <label style="display:flex;gap:8px;align-items:center;font-weight:400">
      <input type="checkbox" name="required" value="1"> فیلد الزامی است
    </label>
    <br>
    <button class="btn" type="submit">افزودن فیلد</button>
  </form>
</div>
