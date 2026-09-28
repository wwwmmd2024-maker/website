<?php
$__layout = 'layouts.admin';
$active = $active ?? 'pages';
$isPage = ($type ?? 'page') === 'page';
$title = $isPage ? 'برگه‌ها' : 'نوشته‌ها';
$pages = max(1, (int) ceil(($total ?? 0) / ($perPage ?? 20)));
?>
<div class="panel">
  <h3><?= e($title) ?> (<?= e($dates->toPersianDigits((string) ($total ?? 0))) ?>)</h3>
  <form method="get" action="<?= e($baseUrl) ?>" style="display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap">
    <input type="text" name="q" value="<?= e($search ?? '') ?>" placeholder="جستجو در عنوان…" style="max-width:240px">
    <select name="status" style="max-width:160px">
      <option value="">همه وضعیت‌ها</option>
      <option value="published" <?= ($status ?? '') === 'published' ? 'selected' : '' ?>>منتشرشده</option>
      <option value="draft" <?= ($status ?? '') === 'draft' ? 'selected' : '' ?>>پیش‌نویس</option>
      <option value="scheduled" <?= ($status ?? '') === 'scheduled' ? 'selected' : '' ?>>زمان‌بندی‌شده</option>
    </select>
    <button class="btn" type="submit">جستجو</button>
    <a class="btn" href="<?= e($baseUrl) ?>/create" style="text-decoration:none">+ <?= e($isPage ? 'برگه تازه' : 'نوشته تازه') ?></a>
  </form>
  <table class="tbl">
    <tr><th>#</th><th>عنوان</th><th>نامک</th><th>وضعیت</th><th>انتشار</th><th>اقدامات</th></tr>
    <?php foreach (($items ?? []) as $item): ?>
      <tr>
        <td><?= e((string) $item->id) ?></td>
        <td><b><?= e($item->title) ?></b></td>
        <td dir="ltr" style="text-align:left"><?= e($item->slug) ?></td>
        <td>
          <?php if ($item->status === 'published'): ?><span class="badge">منتشرشده</span>
          <?php elseif ($item->status === 'scheduled'): ?><span class="badge">زمان‌بندی‌شده</span>
          <?php else: ?><span class="badge">پیش‌نویس</span><?php endif; ?>
        </td>
        <td><?= e($item->publishedAt ? $dates->format($item->publishedAt) : '—') ?></td>
        <td style="white-space:nowrap">
          <a href="<?= e($baseUrl) ?>/<?= e((string) $item->id) ?>/edit">ویرایش</a>
          |
          <a href="/admin/builder/<?= e($type) ?>/<?= e((string) $item->id) ?>">ویرایش با بیلدر</a>
          |
          <form method="post" action="<?= e($baseUrl) ?>/<?= e((string) $item->id) ?>/delete" style="display:inline" onsubmit="return confirm('حذف شود؟')">
            <?= $csrf->field() ?>
            <button type="submit" style="background:none;border:none;color:#a32b2b;cursor:pointer;padding:0;font:inherit">حذف</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (empty($items)): ?><tr><td colspan="6">موردی یافت نشد.</td></tr><?php endif; ?>
  </table>
  <?php if ($pages > 1): ?>
    <div style="margin-top:12px;display:flex;gap:6px">
      <?php for ($p = 1; $p <= $pages; $p++): ?>
        <a class="btn" style="text-decoration:none<?= $p === ($page ?? 1) ? ';font-weight:800' : '' ?>" href="<?= e($baseUrl) ?>?page=<?= $p ?>&q=<?= urlencode($search ?? '') ?>&status=<?= e($status ?? '') ?>"><?= e($dates->toPersianDigits((string) $p)) ?></a>
      <?php endfor; ?>
    </div>
  <?php endif; ?>
</div>
