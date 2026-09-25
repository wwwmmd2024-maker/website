<?php
$__layout = 'layouts.admin';
$active = 'types';
$title = $type['name'];
?>
<div class="toolbar">
  <a class="btn small" href="/admin/cpt/<?= e($type['slug']) ?>/create">＋ افزودن <?= e($type['name']) ?></a>
  <span class="muted"><?= e((string) ($total ?? 0)) ?> آیتم</span>
</div>

<div class="panel">
  <?php if (empty($items)): ?>
    <p style="color:var(--muted)">هنوز آیتمی ثبت نشده است.</p>
  <?php else: ?>
    <table class="tbl">
      <tr><th>#</th><th>عنوان</th><th>نامک</th><th>وضعیت</th><th>تاریخ</th><th></th></tr>
      <?php foreach ($items as $item): ?>
        <tr>
          <td><?= e((string) $item->id) ?></td>
          <td><b><?= e($item->title) ?></b></td>
          <td dir="ltr" class="muted"><?= e($item->slug) ?></td>
          <td><span class="badge <?= $item->status === 'published' ? 'green' : '' ?>"><?= e($item->status) ?></span></td>
          <td class="muted" style="font-size:12px"><?= e($item->createdAt ? $dates->format($item->createdAt) : '—') ?></td>
          <td style="white-space:nowrap">
            <a class="btn small" style="background:#0891b2" href="/admin/cpt/<?= e($type['slug']) ?>/<?= e((string) $item->id) ?>/edit">ویرایش</a>
            <a class="btn small" style="background:#0891b2" href="/admin/builder/post/<?= e((string) $item->id) ?>">سازنده</a>
            <form method="post" action="/admin/cpt/<?= e($type['slug']) ?>/<?= e((string) $item->id) ?>/delete" class="inline-form" data-confirm="این آیتم به زباله‌دان منتقل شود؟">
              <?= $csrf->field() ?? '' ?><button class="btn small danger" type="submit">حذف</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
  <?php endif; ?>
</div>
