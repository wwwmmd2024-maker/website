<?php
/** @var \IRJalali\Core\Translation\Translator $t */
$rtl = $t->isRtl();
?>
<!DOCTYPE html>
<html lang="<?= $t->locale() === 'fa_IR' ? 'fa' : 'en' ?>" dir="<?= $rtl ? 'rtl' : 'ltr' ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? $t->get('app.name')) ?> — <?= e($t->get('app.tagline')) ?></title>
<link rel="stylesheet" href="/assets/css/guest.css">
</head>
<body>
<div class="bg"><span class="b1"></span><span class="b2"></span><span class="b3"></span></div>
<main class="wrap">
  <div class="brand"><span class="mark">آ</span><span><?= e($t->get('app.name')) ?></span></div>
  <div class="card">
    <?= $sections['content'] ?? '' ?>
  </div>
  <?php if (!empty($flash)): ?>
    <div class="flash <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
  <?php endif; ?>
  <p class="foot">IR-Jalali · <?= e($t->get('app.tagline')) ?></p>
</main>
</body>
</html>
