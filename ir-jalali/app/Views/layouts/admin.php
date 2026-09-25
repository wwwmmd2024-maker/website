<?php
/** @var \IRJalali\Core\Translation\Translator $t */
$rtl = $t->isRtl();
$active = $active ?? 'dashboard';
$user = $user ?? null;
$nav = [
    'dashboard' => ['url' => '/admin', 'label' => $t->get('nav.dashboard'), 'icon' => '◈'],
    'pages' => ['url' => '/admin/pages', 'label' => $t->get('nav.pages'), 'icon' => '▤'],
    'posts' => ['url' => '/admin/posts', 'label' => $t->get('nav.posts'), 'icon' => '✎'],
    'templates' => ['url' => '/admin/templates', 'label' => $t->get('nav.templates'), 'icon' => '▦'],
    'menus' => ['url' => '/admin/menus', 'label' => $t->get('nav.menus'), 'icon' => '☰'],
    'forms' => ['url' => '/admin/forms', 'label' => $t->get('nav.forms'), 'icon' => '⧉'],
    'media' => ['url' => '/admin/media', 'label' => $t->get('nav.media'), 'icon' => '◉'],
    'types' => ['url' => '/admin/types', 'label' => $t->get('nav.types'), 'icon' => '❖'],
    'fields' => ['url' => '/admin/fields', 'label' => $t->get('nav.fields'), 'icon' => '⌗'],
    'widgets' => ['url' => '/admin/widgets', 'label' => $t->get('nav.widgets'), 'icon' => '◧'],
    'plugins' => ['url' => '/admin/plugins', 'label' => $t->get('nav.plugins'), 'icon' => '⬡'],
    'themes' => ['url' => '/admin/themes', 'label' => $t->get('nav.themes'), 'icon' => '▦'],
    'marketplace' => ['url' => '/admin/marketplace', 'label' => $t->get('nav.marketplace'), 'icon' => '⇩'],
    'modes' => ['url' => '/admin/modes', 'label' => $t->get('nav.modes'), 'icon' => '⚑'],
    'users' => ['url' => '/admin/users', 'label' => $t->get('nav.users'), 'icon' => '⦿'],
    'notifications' => ['url' => '/admin/notifications', 'label' => $t->get('nav.notifications'), 'icon' => '✉'],
    'backups' => ['url' => '/admin/backups', 'label' => $t->get('nav.backups'), 'icon' => '⟳'],
    'updates' => ['url' => '/admin/updates', 'label' => $t->get('nav.updates'), 'icon' => '↻'],
    'system' => ['url' => '/admin/system', 'label' => $t->get('nav.system'), 'icon' => '♥'],
    'logs' => ['url' => '/admin/logs', 'label' => $t->get('nav.logs'), 'icon' => '☰'],
    'settings' => ['url' => '/admin/settings', 'label' => $t->get('nav.settings'), 'icon' => '⚙'],
];
// Plugin-registered admin pages join the nav dynamically.
try {
    $pluginNav = \IRJalali\Core\Kernel\Application::get()
        ->make(\IRJalali\Core\Plugins\PluginManager::class)->adminPages();
} catch (\Throwable) {
    $pluginNav = [];
}
?>
<!DOCTYPE html>
<html lang="<?= $t->locale() === 'fa_IR' ? 'fa' : 'en' ?>" dir="<?= $rtl ? 'rtl' : 'ltr' ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? $t->get('nav.dashboard')) ?> — <?= e($t->get('app.name')) ?></title>
<link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body>
<div class="shell">
  <aside class="sidebar">
    <div class="brand"><span class="mark">آ</span><span><?= e($t->get('app.name')) ?></span></div>
    <nav>
      <?php foreach ($nav as $key => $item): ?>
        <a href="<?= e($item['url']) ?>" class="<?= $key === $active ? 'active' : '' ?>"><span class="ic"><?= e($item['icon']) ?></span><?= e($item['label']) ?></a>
      <?php endforeach; ?>
      <?php if (!empty($pluginNav)): ?>
        <div style="margin-top:14px;padding-top:12px;border-top:1px solid rgba(255,255,255,.08);color:#8b93c9;font-size:11px;padding-inline:14px">افزونه‌ها</div>
        <?php foreach ($pluginNav as $page): ?>
          <a href="/admin/plugin/<?= e($page['slug']) ?>" class="<?= ($active ?? '') === 'plugin:' . $page['slug'] ? 'active' : '' ?>"><span class="ic"><?= e($page['icon']) ?></span><?= e($page['title']) ?></a>
        <?php endforeach; ?>
      <?php endif; ?>
    </nav>
    <div class="side-foot">
      <a href="/" target="_blank" rel="noopener">↗ <?= e($t->get('nav.view_site')) ?></a>
    </div>
  </aside>
  <div class="main">
    <header class="topbar">
      <strong><?= e($title ?? '') ?></strong>
      <span style="flex:1"></span>
      <button class="palette-trigger" id="paletteTrigger" type="button" title="Ctrl+K">⌘ <?= e($t->get('nav.palette')) ?></button>
      <div class="userbox">
        <?php if ($user): ?><span class="avatar"><?= e(mb_substr((string) ($user['display_name'] ?? $user['username']), 0, 1, 'UTF-8')) ?></span><span><?= e($user['display_name'] ?? $user['username']) ?></span><?php endif; ?>
        <form method="post" action="/admin/logout" style="display:inline"><?= $csrf->field() ?? '' ?><button class="link" type="submit"><?= e($t->get('nav.logout')) ?></button></form>
      </div>
    </header>
    <?php if (!empty($flash)): ?>
      <div class="flash <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
    <?php endif; ?>
    <div class="content">
      <?= $sections['content'] ?? '' ?>
    </div>
  </div>
</div>

<!-- Command palette (Ctrl+K) — Part 3 §24 -->
<div class="palette-overlay" id="paletteOverlay" hidden>
  <div class="palette" role="dialog" aria-modal="true" aria-label="<?= e($t->get('nav.palette')) ?>">
    <input type="search" id="paletteInput" class="palette-input" placeholder="<?= e($t->get('nav.palette_placeholder')) ?>" autocomplete="off">
    <div class="palette-list" id="paletteList"></div>
  </div>
</div>
<script id="paletteCommands" type="application/json"><?= json_encode(array_values(array_filter([
    ['label' => $t->get('nav.pages') . ' — ' . $t->get('palette.new'), 'url' => '/admin/pages/create'],
    ['label' => $t->get('nav.posts') . ' — ' . $t->get('palette.new'), 'url' => '/admin/posts/create'],
    ['label' => $t->get('nav.media'), 'url' => '/admin/media'],
    ['label' => $t->get('nav.menus'), 'url' => '/admin/menus'],
    ['label' => $t->get('nav.templates'), 'url' => '/admin/templates'],
    ['label' => $t->get('nav.plugins'), 'url' => '/admin/plugins'],
    ['label' => $t->get('nav.themes'), 'url' => '/admin/themes'],
    ['label' => $t->get('nav.marketplace'), 'url' => '/admin/marketplace'],
    ['label' => $t->get('nav.widgets'), 'url' => '/admin/widgets'],
    ['label' => $t->get('nav.types'), 'url' => '/admin/types'],
    ['label' => $t->get('nav.fields'), 'url' => '/admin/fields'],
    ['label' => $t->get('nav.users'), 'url' => '/admin/users'],
    ['label' => $t->get('nav.backups'), 'url' => '/admin/backups'],
    ['label' => $t->get('nav.updates'), 'url' => '/admin/updates'],
    ['label' => $t->get('nav.system'), 'url' => '/admin/system'],
    ['label' => $t->get('nav.settings'), 'url' => '/admin/settings'],
    ['label' => $t->get('palette.clear_cache'), 'url' => '/admin?clear_cache=1'],
    ['label' => $t->get('nav.view_site'), 'url' => '/'],
], fn ($c) => is_array($c) && isset($c['label'], $c['url']))), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
<script src="/assets/js/admin.js"></script>
</body>
</html>
