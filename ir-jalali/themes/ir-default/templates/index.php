<?php
/**
 * IR Default — index fallback.
 * Data: $e, $lang, $dir, $pageTitle, $siteTitle, $headerHtml, $footerHtml,
 * $headCss, $themeCssUrl, $frontCssUrl, $frontJsUrl, $content, $sidebar
 */
?>
<!DOCTYPE html>
<html lang="<?= $e($lang) ?>" dir="<?= $e($dir) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $e($pageTitle) ?></title>
<link rel="stylesheet" href="<?= $e($frontCssUrl) ?>">
<?php foreach (($themeCssUrls ?? []) as $cssUrl): ?><link rel="stylesheet" href="<?= $e($cssUrl) ?>"><?php endforeach; ?>
<style><?= $headCss ?></style>
<?= $headMeta ?? '' ?>
</head>
<body class="<?= $e($bodyClass ?? '') ?>
<?= $bodyOpen ?? '' ?>">
<?= $headerHtml ?>
<?php require __DIR__ . '/../parts/flash.php'; ?>
<main class="wrap"><?= $content ?></main>
<?= $footerHtml ?>
<script src="<?= $e($frontJsUrl) ?>"></script>
<?= $bodyClose ?? '' ?>
</body>
</html>
