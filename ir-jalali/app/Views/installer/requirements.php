<?php
$__layout = 'layouts.guest';
$title = $t->get('install.requirements');
?>
<h1><?= e($t->get('install.title')) ?></h1>
<p class="sub">گام ۱ از ۴ — <?= e($t->get('install.requirements')) ?></p>
<div class="steps"><span class="on">پیش‌نیازها</span><span>دیتابیس</span><span>مدیر</span><span>سایت</span></div>
<div class="checks">
  <?php foreach (($checks ?? []) as $check): ?>
    <div class="check <?= $check['ok'] ? 'ok' : 'bad' ?>">
      <span class="st"><?= $check['ok'] ? '✓' : '✕' ?></span>
      <span><?= e($check['label']) ?><small><?= e($check['detail']) ?></small></span>
    </div>
  <?php endforeach; ?>
</div>
<?php if (!empty($ok)): ?>
  <a class="btn" href="/install?step=database"><?= e($t->get('install.next')) ?> ←</a>
<?php else: ?>
  <p class="sub" style="margin-top:16px">موارد قرمز را برطرف کنید و صفحه را تازه‌سازی کنید.</p>
<?php endif; ?>
