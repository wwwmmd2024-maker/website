<?php
$__layout = 'layouts.admin';
$active = 'updates';
$title = $t->get('updates.title');
$total = count($pluginUpdates ?? []) + count($themeUpdates ?? []) + ($coreUpdate !== null ? 1 : 0);
?>
<div class="cards">
  <div class="card"><div class="num"><?= e((string) $total) ?></div><div class="lbl">به‌روزرسانی موجود</div></div>
  <div class="card"><div class="num" dir="ltr"><?= e($coreVersion ?? '') ?></div><div class="lbl">نسخه هسته</div></div>
</div>

<div class="panel">
  <h3>هسته</h3>
  <?php if ($coreUpdate === null): ?>
    <p style="color:var(--muted)">هسته به‌روز است (نسخه <?= e($coreVersion ?? '') ?>).</p>
  <?php else: ?>
    <p>نسخه جدید هسته: <b dir="ltr"><?= e($coreUpdate['version']) ?></b> — به‌روزرسانی هسته از طریق بسته نصب انجام می‌شود.</p>
  <?php endif; ?>
</div>

<div class="panel">
  <h3>به‌روزرسانی افزونه‌ها و قالب‌ها</h3>
  <?php if ($total === 0): ?>
    <p style="color:var(--muted)">همه چیز به‌روز است. 🎉</p>
  <?php else: ?>
    <table class="tbl">
      <tr><th>نام</th><th>نوع</th><th>نسخه فعلی</th><th>نسخه جدید</th><th>عملیات</th></tr>
      <?php foreach (array_merge($pluginUpdates ?? [], $themeUpdates ?? []) as $u): ?>
        <tr>
          <td><b><?= e($u['name']) ?></b> <span class="muted" dir="ltr"><?= e($u['slug']) ?></span></td>
          <td><span class="badge"><?= $u['kind'] === 'plugin' ? 'افزونه' : 'قالب' ?></span></td>
          <td dir="ltr"><?= e($u['current']) ?></td>
          <td dir="ltr"><?= e($u['latest']) ?></td>
          <td>
            <form method="post" action="/admin/updates/<?= e($u['kind']) ?>/<?= e($u['slug']) ?>/apply" class="inline-form" data-confirm="قبل از به‌روزرسانی، پشتیبان کامل سایت گرفته می‌شود. ادامه می‌دهید؟">
              <?= $csrf->field() ?? '' ?>
              <button class="btn small" type="submit">به‌روزرسانی</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
  <?php endif; ?>
</div>
<p class="muted" style="font-size:12.5px">قبل از هر به‌روزرسانی: پشتیبان‌گیری خودکار ← بررسی نسخه ← بررسی سازگاری ← مایگریشن ← در صورت شکست، بازگشت خودکار (Rollback).</p>
