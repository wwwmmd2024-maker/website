<?php
$__layout = 'layouts.admin';
$active = 'menus';
$title = 'فهرست‌ها';
$locLabels = ['primary' => 'اصلی', 'footer' => 'پانوشت', 'mobile' => 'موبایل'];
?>
<div class="panel">
  <h3>فهرست‌ها</h3>
  <table class="tbl">
    <tr><th>#</th><th>نام</th><th>نامک</th><th>موقعیت</th><th>اقدامات</th></tr>
    <?php foreach (($menus ?? []) as $menu): ?>
      <tr>
        <td><?= e((string) $menu['id']) ?></td>
        <td><b><?= e($menu['name']) ?></b></td>
        <td dir="ltr" style="text-align:left"><?= e($menu['slug']) ?></td>
        <td><?= e($locLabels[$menu['location']] ?? $menu['location']) ?></td>
        <td style="white-space:nowrap">
          <a href="/admin/menus/<?= e((string) $menu['id']) ?>/edit">مدیریت آیتم‌ها</a>
          |
          <form method="post" action="/admin/menus/<?= e((string) $menu['id']) ?>/delete" style="display:inline" onsubmit="return confirm('فهرست و همه آیتم‌هایش حذف شود؟')">
            <?= $csrf->field() ?>
            <button type="submit" style="background:none;border:none;color:#a32b2b;cursor:pointer;padding:0;font:inherit">حذف</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (empty($menus)): ?><tr><td colspan="5">فهرستی وجود ندارد.</td></tr><?php endif; ?>
  </table>
</div>
<div class="panel">
  <h3>فهرست تازه</h3>
  <form method="post" action="/admin/menus" style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end">
    <?= $csrf->field() ?>
    <div><label>نام</label><input type="text" name="name" required maxlength="120"></div>
    <div><label>موقعیت</label>
      <select name="location">
        <?php foreach (($locations ?? []) as $loc): ?>
          <option value="<?= e($loc) ?>"><?= e($locLabels[$loc] ?? $loc) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button class="btn" type="submit">ساخت فهرست</button>
  </form>
</div>
