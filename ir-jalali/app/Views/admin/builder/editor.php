<?php
/** @var array<string, mixed> $boot */
$title = $title ?? 'ویرایشگر';
$bootJson = json_encode($boot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?><!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> — IR-Jalali</title>
<link rel="stylesheet" href="/assets/css/builder-admin.css">
<link rel="stylesheet" href="<?= e($boot['frontCssUrl'] ?? '/assets/css/builder-front.css') ?>">
<style id="ij-tokens"><?= $boot['tokensCss'] ?? '' ?></style>
</head>
<body class="ijb">
<div id="ijb-app" data-loading="1">
  <!-- ══ Top bar ══ -->
  <header id="ijb-topbar">
    <div class="ijb-brand"><span class="ijb-logo">آ</span><span class="ijb-doc" id="ijb-doc-title">ویرایشگر</span></div>
    <div class="ijb-devices" role="group" aria-label="دستگاه">
      <button type="button" data-device="desktop" class="on" title="دسکتاپ">🖥</button>
      <button type="button" data-device="laptop" title="لپ‌تاپ">💻</button>
      <button type="button" data-device="tablet" title="تبلت">📱</button>
      <button type="button" data-device="mobile" title="موبایل">🤳</button>
    </div>
    <div class="ijb-actions">
      <span id="ijb-status" class="ijb-status">در حال بارگذاری…</span>
      <button type="button" id="ijb-undo" title="برگردان (Ctrl+Z)" disabled>↩</button>
      <button type="button" id="ijb-redo" title="از نو (Ctrl+Shift+Z)" disabled>↪</button>
      <button type="button" id="ijb-history" title="تاریخچه نسخه‌ها">🕘</button>
      <button type="button" id="ijb-preview" title="پیش‌نمایش">👁</button>
      <button type="button" id="ijb-save" class="primary">ذخیره</button>
      <a id="ijb-exit" href="/admin" title="خروج">✕</a>
    </div>
  </header>

  <div id="ijb-main">
    <!-- ══ Left panel: palette / settings ══ -->
    <aside id="ijb-left">
      <nav id="ijb-left-tabs">
        <button type="button" data-tab="components" class="on">🧩 اجزا</button>
        <button type="button" data-tab="navigator">🗂 ساختار</button>
        <button type="button" data-tab="tokens" id="ijb-tokens-btn">🎨 استایل سراسری</button>
      </nav>
      <div id="ijb-tab-components" class="ijb-tab on">
        <input type="search" id="ijb-search" placeholder="جستجوی اجزا…">
        <div id="ijb-palette"></div>
      </div>
      <div id="ijb-tab-navigator" class="ijb-tab"><div id="ijb-tree"></div></div>
      <div id="ijb-tab-tokens" class="ijb-tab"><div id="ijb-tokens-form"></div>
        <button type="button" id="ijb-tokens-save" class="primary">ذخیره استایل سراسری</button>
      </div>
    </aside>

    <!-- ══ Canvas ══ -->
    <main id="ijb-canvas-wrap">
      <div id="ijb-canvas-device">
        <style id="ijb-canvas-css"></style>
        <div id="ijb-canvas"><p class="ijb-empty">در حال بارگذاری بوم…</p></div>
      </div>
    </main>

    <!-- ══ Right panel: properties ══ -->
    <aside id="ijb-right">
      <nav id="ijb-right-tabs">
        <button type="button" data-tab="content" class="on">محتوا</button>
        <button type="button" data-tab="style">استایل</button>
        <button type="button" data-tab="advanced">پیشرفته</button>
      </nav>
      <div id="ijb-props"><p class="ijb-empty">یک جزء را در بوم انتخاب کنید.<br>یا از پنل چپ جزء جدید بکشید.</p></div>
    </aside>
  </div>

  <!-- ══ Overlays ══ -->
  <div id="ijb-modal" hidden><div id="ijb-modal-box">
    <button type="button" id="ijb-modal-close">✕</button>
    <div id="ijb-modal-body"></div>
  </div></div>
  <div id="ijb-toast" role="status"></div>
</div>
<noscript><p style="padding:40px;text-align:center">ویرایشگر به جاوااسکریپت نیاز دارد.</p></noscript>
<script>window.__BUILDER_BOOT__ = <?= $bootJson !== false ? $bootJson : '{}' ?>;</script>
<script src="/assets/js/builder-app.js"></script>
</body>
</html>
