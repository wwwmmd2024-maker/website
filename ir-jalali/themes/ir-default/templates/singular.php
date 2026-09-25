<?php /** IR Default — singular wrapper (page + single). */ ?>
<!DOCTYPE html>
<html lang="<?= $e($lang) ?>" dir="<?= $e($dir) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $e($pageTitle) ?> — <?= $e($siteTitle) ?></title>
<meta name="description" content="<?= $e($metaDescription ?? $tagline) ?>">
<link rel="stylesheet" href="<?= $e($frontCssUrl) ?>">
<?php foreach (($themeCssUrls ?? []) as $cssUrl): ?><link rel="stylesheet" href="<?= $e($cssUrl) ?>"><?php endforeach; ?>
<style><?= $headCss ?></style>
<?= $headMeta ?? '' ?>
</head>
<body class="<?= $e($bodyClass ?? '') ?>
<?= $bodyOpen ?? '' ?>">
<?= $headerHtml ?>
<?php require __DIR__ . '/../parts/flash.php'; ?>
<main class="wrap">
  <div class="<?= $sidebar !== '' ? 'with-sidebar' : '' ?>">
    <article class="article">
      <h1><?= $e($post['title'] ?? '') ?></h1>
      <?php if (!empty($postMeta)): ?><div class="meta"><?= $e($postMeta) ?></div><?php endif; ?>
      <?= $content ?>
    </article>
    <?php if ($sidebar !== ''): ?><aside class="sidebar"><?= $sidebar ?></aside><?php endif; ?>
  </div>
</main>
<?= $footerHtml ?>
<script src="<?= $e($frontJsUrl) ?>"></script>
<?= $bodyClose ?? '' ?>
</body>
</html>
