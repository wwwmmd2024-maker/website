<?php
$__layout = 'layouts.admin';
$active = 'media';
$title = $t->get('media.title');
?>
<div class="drop" id="dropzone" data-token="<?= e($csrf->token()) ?>">
  <div style="font-size:34px">☁️⬆️</div>
  <b><?= e($t->get('media.drop')) ?></b>
  <div id="upload-status" style="margin-top:8px;color:var(--muted);font-size:12.5px"></div>
  <input type="file" id="fileinput" multiple hidden>
</div>
<div class="panel">
  <h3><?= e($t->get('media.title')) ?> (<?= e($dates->toPersianDigits((string) ($total ?? 0))) ?>)</h3>
  <?php if (empty($items)): ?>
    <p style="color:var(--muted)"><?= e($t->get('media.empty')) ?></p>
  <?php else: ?>
    <div class="mgrid">
      <?php foreach ($items as $item): ?>
        <div class="mitem">
          <?php if (str_starts_with($item['mime'], 'image/')): ?>
            <a href="<?= e($item['url']) ?>" target="_blank" rel="noopener"><img src="<?= e($item['thumb']) ?>" alt="<?= e($item['alt'] ?? '') ?>" loading="lazy"></a>
          <?php else: ?>
            <div class="fileph">📄</div>
          <?php endif; ?>
          <div class="meta"><b dir="ltr"><?= e($item['original_name']) ?></b><?= e(strtoupper($item['extension'])) ?> · <?= e(number_format($item['size_bytes'] / 1024)) ?> KB</div>
          <form method="post" action="/admin/media/<?= e((string) $item['id']) ?>/delete" data-confirm="<?= e($t->get('common.confirm_delete')) ?>">
            <?= $csrf->field() ?>
            <button class="btn danger small" type="submit"><?= e($t->get('media.delete')) ?></button>
          </form>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
