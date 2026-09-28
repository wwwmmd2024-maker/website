<?php
/** @var \IRJalali\Core\Translation\Translator $t */
$rtl = $t->isRtl();
?>
<!DOCTYPE html>
<html lang="<?= $t->locale() === 'fa_IR' ? 'fa' : 'en' ?>" dir="<?= $rtl ? 'rtl' : 'ltr' ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle ?? $siteTitle) ?><?= isset($pageTitle) ? ' — ' . e($siteTitle) : '' ?></title>
<meta name="description" content="<?= e($tagline) ?>">
<link rel="stylesheet" href="/assets/css/front.css">
</head>
<body>
<header class="site-head">
  <div class="wrap head-in">
    <a class="logo" href="/"><span class="mark">آ</span><span><?= e($siteTitle) ?></span></a>
    <nav class="menu">
      <?php foreach (($menu ?? []) as $item): ?>
        <a href="<?= e($item['url'] ?: '/') ?>"><?= e($item['title']) ?></a>
      <?php endforeach; ?>
      <a href="/admin" class="login">ورود مدیریت</a>
    </nav>
  </div>
</header>
<main class="wrap">
  <?= $sections['content'] ?? '' ?>
</main>
<footer class="site-foot"><div class="wrap">قدرت‌گرفته از <b>IR-Jalali</b> · <?= e($tagline) ?></div></footer>
</body>
</html>
