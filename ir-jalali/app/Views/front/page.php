<?php
$__layout = 'layouts.front';
$pageTitle = $post->title;
?>
<article class="article">
  <h1><?= e($post->title) ?></h1>
  <div class="meta">منتشرشده در <?= e($post->publishedAt ? $dates->formatFull($post->publishedAt) : '') ?></div>
  <div class="body"><?= \IRJalali\Core\Security\Sanitize::richText($post->content) ?></div>
</article>
