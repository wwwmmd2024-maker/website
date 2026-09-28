<?php
$__layout = 'layouts.guest';
$title = $t->get('setup.title');
?>
<div class="card wide" style="margin:-36px -34px 0;max-width:none;background:none;border:none;box-shadow:none;padding:0">
<h1><?= e($t->get('setup.title')) ?></h1>
<p class="sub"><?= e($t->get('setup.subtitle')) ?> — با انتخاب نوع سایت، برگه‌ها، فهرست و تنظیمات اولیه به‌صورت خودکار ساخته می‌شوند.</p>
<form method="post" action="/setup">
  <?= $csrf->field() ?>
  <div class="types">
    <?php foreach (($types ?? []) as $i => $type): ?>
      <div class="type">
        <input type="radio" id="wt-<?= e($type['value']) ?>" name="website_type" value="<?= e($type['value']) ?>" <?= $i === 0 ? 'checked' : '' ?>>
        <label for="wt-<?= e($type['value']) ?>"><?= e($type['label']) ?><small><?= e($type['description']) ?></small></label>
      </div>
    <?php endforeach; ?>
  </div>
  <button class="btn" type="submit"><?= e($t->get('setup.apply')) ?> ←</button>
</form>
</div>
