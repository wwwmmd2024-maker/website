<?php
$__layout = 'layouts.admin';
$active = 'marketplace';
$title = $t->get('marketplace.title');
$isTheme = ($type ?? 'plugin') === 'theme';
?>
<div class="panel">
  <div class="logtabs">
    <a href="/admin/marketplace?type=plugin" class="<?= !$isTheme ? 'active' : '' ?>">افزونه‌ها</a>
    <a href="/admin/marketplace?type=theme" class="<?= $isTheme ? 'active' : '' ?>">قالب‌ها</a>
  </div>
  <form method="get" action="/admin/marketplace" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
    <input type="hidden" name="type" value="<?= e($type ?? 'plugin') ?>">
    <input type="text" name="q" value="<?= e($query ?? '') ?>" placeholder="جستجو در مارکت‌پلیس…" style="max-width:360px">
    <button class="btn small" type="submit">جستجو</button>
  </form>
</div>

<?php if (empty($cards)): ?>
  <div class="panel"><p style="color:var(--muted)">موردی یافت نشد. مطمئن شوید مخزن محلی <span dir="ltr">marketplace/catalog.json</span> یا آدرس مخزن ریموت در <span dir="ltr">.env</span> تنظیم شده است.</p></div>
<?php else: ?>
  <div class="mgrid">
    <?php foreach ($cards as $card): ?>
      <div class="mitem" style="display:flex;flex-direction:column">
        <div class="fileph"><?= $isTheme ? '▦' : '◈' ?></div>
        <div class="meta" style="flex:1">
          <b><?= e($card['name']) ?></b>
          <span dir="ltr">v<?= e($card['version']) ?></span>
          <span class="badge"><?= e($card['category']) ?></span>
          <?php if ($card['license'] !== 'free'): ?><span class="badge">پرمیوم</span><?php endif; ?>
          <?php if ($card['installed']): ?><span class="badge green">نصب‌شده</span><?php endif; ?>
          <div style="margin-top:6px;color:var(--muted);font-size:11.5px"><?= e($card['description']) ?></div>
          <div style="margin-top:6px;color:var(--muted);font-size:11px"><?= e($card['author']) ?> · <?= e((string) $card['downloads']) ?> نصب</div>
        </div>
        <?php if (!$card['installed']): ?>
          <div style="display:flex;gap:6px;padding:10px;flex-wrap:wrap">
            <form method="post" action="/admin/marketplace/install">
              <?= $csrf->field() ?? '' ?>
              <input type="hidden" name="slug" value="<?= e($card['slug']) ?>">
              <input type="hidden" name="type" value="<?= e($type ?? 'plugin') ?>">
              <button class="btn small" type="submit">نصب</button>
              <?php if (!$isTheme): ?><button class="btn small" type="submit" name="activate" value="1" style="background:#059669">نصب و فعال‌سازی</button><?php endif; ?>
            </form>
          </div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
