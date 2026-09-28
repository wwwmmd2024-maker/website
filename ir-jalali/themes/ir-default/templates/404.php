<?php /** IR Default — 404. */ ?>
<!DOCTYPE html>
<html lang="<?= $e($lang) ?>" dir="<?= $e($dir) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>یافت نشد — <?= $e($siteTitle) ?></title>
<link rel="stylesheet" href="<?= $e($frontCssUrl) ?>">
<?php foreach (($themeCssUrls ?? []) as $cssUrl): ?><link rel="stylesheet" href="<?= $e($cssUrl) ?>"><?php endforeach; ?>
<style><?= $headCss ?></style>
<?= $headMeta ?? '' ?>
</head>
<body class="<?= $e($bodyClass ?? '') ?>
<?= $bodyOpen ?? '' ?>">
<?= $headerHtml ?>
<?php require __DIR__ . '/../parts/flash.php'; ?>
<main class="wrap"><div class="err"><h1>۴۰۴</h1><p>صفحه موردنظر یافت نشد.</p><p style="margin-top:16px"><a href="/">بازگشت به خانه</a></p></div></main>
<?= $footerHtml ?>
<script src="<?= $e($frontJsUrl) ?>"></script>
<?= $bodyClose ?? '' ?>
</body>
</html>
