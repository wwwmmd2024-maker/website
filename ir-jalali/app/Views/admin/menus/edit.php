<?php
$__layout = 'layouts.admin';
$active = 'menus';
$title = 'فهرست: ' . ($menu['name'] ?? '');
$locLabels = ['primary' => 'اصلی', 'footer' => 'پانوشت', 'mobile' => 'موبایل'];
$byId = [];
foreach (($items ?? []) as $it) { $byId[$it['id']] = $it; }
$depthOf = function (array $item) use (&$byId): int {
    $depth = 0; $pid = $item['parent_id'];
    while (!empty($pid) && isset($byId[$pid]) && $depth < 5) { $depth++; $pid = $byId[$pid]['parent_id']; }
    return $depth;
};
?>
<div class="panel">
  <h3><?= e($title) ?></h3>
  <form method="post" action="/admin/menus/<?= e((string) $menu['id']) ?>" style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end">
    <?= $csrf->field() ?>
    <div><label>نام</label><input type="text" name="name" value="<?= e($menu['name'] ?? '') ?>" required maxlength="120"></div>
    <div><label>موقعیت</label>
      <select name="location">
        <?php foreach (($locations ?? []) as $loc): ?>
          <option value="<?= e($loc) ?>" <?= ($menu['location'] ?? '') === $loc ? 'selected' : '' ?>><?= e($locLabels[$loc] ?? $loc) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button class="btn" type="submit">ذخیره مشخصات</button>
    <a href="/admin/menus">بازگشت</a>
  </form>
</div>
<div class="panel">
  <h3>آیتم‌ها</h3>
  <table class="tbl">
    <tr><th>عنوان</th><th>نوع</th><th>نشانی/مرجع</th><th>ترتیب</th><th>اقدامات</th></tr>
    <?php foreach (($items ?? []) as $item): ?>
      <?php $depth = $depthOf($item); ?>
      <tr>
        <td><?= str_repeat('— ', $depth) ?><b><?= e($item['title']) ?></b></td>
        <td><?= e($item['type'] === 'custom' ? 'پیوند دلخواه' : ($item['type'] === 'page' ? 'برگه' : 'نوشته')) ?></td>
        <td dir="ltr" style="text-align:left"><?= e((string) ($item['url'] ?? '')) ?></td>
        <td style="white-space:nowrap">
          <form method="post" action="/admin/menus/<?= e((string) $menu['id']) ?>/items/<?= e((string) $item['id']) ?>" style="display:inline">
            <?= $csrf->field() ?>
            <input type="hidden" name="move" value="up"><button type="submit" title="بالا">↑</button>
          </form>
          <form method="post" action="/admin/menus/<?= e((string) $menu['id']) ?>/items/<?= e((string) $item['id']) ?>" style="display:inline">
            <?= $csrf->field() ?>
            <input type="hidden" name="move" value="down"><button type="submit" title="پایین">↓</button>
          </form>
        </td>
        <td>
          <form method="post" action="/admin/menus/<?= e((string) $menu['id']) ?>/items/<?= e((string) $item['id']) ?>/delete" style="display:inline" onsubmit="return confirm('حذف شود؟')">
            <?= $csrf->field() ?>
            <button type="submit" style="background:none;border:none;color:#a32b2b;cursor:pointer;padding:0;font:inherit">حذف</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (empty($items)): ?><tr><td colspan="5">هنوز آیتمی ندارد.</td></tr><?php endif; ?>
  </table>
</div>
<div class="panel">
  <h3>افزودن آیتم</h3>
  <form method="post" action="/admin/menus/<?= e((string) $menu['id']) ?>/items">
    <?= $csrf->field() ?>
    <label>عنوان</label>
    <input type="text" name="title" required maxlength="150">
    <label>نوع</label>
    <select name="type" id="ij-item-type" onchange="document.getElementById('ij-custom').style.display=this.value==='custom'?'':'none';document.getElementById('ij-ref').style.display=this.value==='custom'?'none':''">
      <option value="custom">پیوند دلخواه</option>
      <option value="page">برگه</option>
      <option value="post">نوشته</option>
    </select>
    <div id="ij-custom">
      <label>نشانی (با / یا http شروع شود)</label>
      <input type="text" name="url" dir="ltr" maxlength="500" placeholder="/about">
    </div>
    <div id="ij-ref" style="display:none">
      <label>برگه</label>
      <select name="reference_page">
        <?php foreach (($pages ?? []) as $p): ?><option value="<?= e((string) $p->id) ?>"><?= e($p->title) ?></option><?php endforeach; ?>
      </select>
      <p style="color:var(--muted);font-size:12px">برای «نوشته» از فهرست زیر انتخاب کنید و نوع را «نوشته» بگذارید:</p>
      <label>نوشته</label>
      <select name="reference_post">
        <?php foreach (($posts ?? []) as $p): ?><option value="<?= e((string) $p->id) ?>"><?= e($p->title) ?></option><?php endforeach; ?>
      </select>
    </div>
    <label>والد (برای زیرمنو)</label>
    <select name="parent_id">
      <option value="">— بدون والد —</option>
      <?php foreach (($items ?? []) as $item): ?>
        <option value="<?= e((string) $item['id']) ?>"><?= e($item['title']) ?></option>
      <?php endforeach; ?>
    </select>
    <label style="display:flex;gap:8px;align-items:center;font-weight:400">
      <input type="checkbox" name="new_tab" value="1"> باز شدن در برگه تازه
    </label>
    <label>کلاس CSS (اختیاری)</label>
    <input type="text" name="css_class" dir="ltr" maxlength="120">
    <br><br>
    <button class="btn" type="submit">افزودن آیتم</button>
  </form>
  <script>
    // Merge the page/post pickers into reference_id before submit.
    document.currentScript.previousElementSibling.addEventListener('submit', function (ev) {
      var type = document.getElementById('ij-item-type').value;
      if (type === 'page' || type === 'post') {
        var pick = this.querySelector(type === 'page' ? '[name=reference_page]' : '[name=reference_post]');
        var hidden = document.createElement('input');
        hidden.type = 'hidden'; hidden.name = 'reference_id'; hidden.value = pick ? pick.value : '';
        this.appendChild(hidden);
      }
      if (this.querySelector('[name=new_tab]').checked) {
        var t = document.createElement('input');
        t.type = 'hidden'; t.name = 'target'; t.value = '_blank';
        this.appendChild(t);
      }
    });
  </script>
</div>
