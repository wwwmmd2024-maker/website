<?php /** IR Default — theme header part (used when site_header_source=theme). */ ?>
<header class="site-head"><div class="wrap">
  <a class="logo" href="/"><span class="mark">آ</span><span><?= $e($siteTitle) ?></span></a>
  <nav class="menu">
    <?php foreach (($menu ?? []) as $item): ?>
      <a href="<?= $e($item['url'] ?: '/') ?>"><?= $e($item['title']) ?></a>
    <?php endforeach; ?>
    <a href="/admin" class="login">ورود</a>
  </nav>
</div></header>
