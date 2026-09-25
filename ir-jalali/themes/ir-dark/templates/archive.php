<?php /** IR Dark — archive loop. Data: $posts, $pagination, $archiveTitle */ ?>
<!DOCTYPE html>
<html lang="<?= $e($lang) ?>" dir="<?= $e($dir) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $e($pageTitle) ?> — <?= $e($siteTitle) ?></title>
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
  <div class="hero"><h1><?= $e($archiveTitle ?? $pageTitle) ?></h1></div>
  <div class="ij-posts ij-posts-grid">
    <?php foreach (($posts ?? []) as $item): ?>
      <article class="ij-postcard"><a href="/<?= $e(ltrim($item['slug'], '/')) ?>">
        <?php if (!empty($item['featured_image'])): ?><img src="<?= $e($item['featured_image']) ?>" alt="" loading="lazy"><?php endif; ?>
        <h4><?= $e($item['title']) ?></h4>
        <?php if (!empty($item['excerpt'])): ?><p><?= $e($item['excerpt']) ?></p><?php endif; ?>
      </a></article>
    <?php endforeach; ?>
  </div>
  <?php if (!empty($pagination)): ?><div class="pagination"><?= $pagination ?></div><?php endif; ?>
</main>
<?= $footerHtml ?>
<script src="<?= $e($frontJsUrl) ?>"></script>
<?= $bodyClose ?? '' ?>
</body>
</html>
