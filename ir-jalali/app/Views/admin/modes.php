<?php
$__layout = 'layouts.admin';
$active = 'modes';
$title = $t->get('modes.title');
$pluginStatus = $pluginStatus ?? [];
?>
<div class="panel">
  <p class="muted" style="font-size:13px">«حالت وب‌سایت» فقط مشخص می‌کند کدام ماژول‌ها فعال و در دسترس باشند. تغییر حالت <b>هیچ داده‌ای را حذف نمی‌کند</b>؛ مثلاً غیرفعال‌کردن فروشگاه، محصولات و سفارش‌ها را نگه می‌دارد.</p>
</div>

<div class="mgrid">
  <?php foreach ($cards as $card): ?>
    <div class="mitem" style="display:flex;flex-direction:column">
      <div class="fileph">⚑</div>
      <div class="meta" style="flex:1">
        <b><?= e($card['label']) ?></b>
        <?php if (($current ?? '') === $card['slug']): ?><span class="badge green">فعال</span><?php endif; ?>
        <div style="margin-top:6px;color:var(--muted);font-size:11.5px"><?= e($card['description']) ?></div>
        <?php if (!empty($card['plugins'])): ?>
          <div style="margin-top:8px;font-size:11px">
            <?php foreach ($card['plugins'] as $p): ?>
              <span class="badge <?= !empty($pluginStatus[$p]) ? 'green' : '' ?>" dir="ltr"><?= e($p) ?></span>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
      <?php if (($current ?? '') !== $card['slug']): ?>
        <div style="padding:10px">
          <form method="post" action="/admin/modes/<?= e($card['slug']) ?>/apply" data-confirm="این حالت اعمال شود؟ افزونه‌های پیشنهادی نیز فعال می‌شوند.">
            <?= $csrf->field() ?? '' ?>
            <button class="btn small" type="submit" name="plugins" value="1">اعمال حالت + فعال‌سازی ماژول‌ها</button>
          </form>
        </div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>
