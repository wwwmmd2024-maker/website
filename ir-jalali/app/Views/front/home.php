<?php
$__layout = 'layouts.front';
?>
<div class="hero">
  <h1><?= e($siteTitle) ?></h1>
  <?php if (!empty($tagline)): ?><p><?= e($tagline) ?></p><?php endif; ?>
</div>
<?php if (!empty($pages)): ?>
  <h2 class="sec">برگه‌ها</h2>
  <div class="cards">
    <?php foreach ($pages as $page): ?>
      <a class="pcard" href="/<?= e($page->slug) ?>">
        <h3><?= e($page->title) ?></h3>
        <?php if ($page->excerpt): ?><p><?= e($page->excerpt) ?></p><?php endif; ?>
      </a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
<?php if (!empty($posts)): ?>
  <h2 class="sec">تازه‌ترین نوشته‌ها</h2>
  <div class="cards">
    <?php foreach ($posts as $post): ?>
      <a class="pcard" href="/<?= e($post->slug) ?>">
        <h3><?= e($post->title) ?></h3>
        <?php if ($post->excerpt): ?><p><?= e($post->excerpt) ?></p><?php endif; ?>
      </a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
