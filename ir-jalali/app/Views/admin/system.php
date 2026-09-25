<?php
$__layout = 'layouts.admin';
$active = 'system';
$title = $t->get('system.title');
?>
<div class="cards">
  <div class="card"><div class="num" style="color:#059669"><?= e((string) ($pass ?? 0)) ?></div><div class="lbl">بررسی موفق</div></div>
  <div class="card"><div class="num" style="color:#e11d48"><?= e((string) ($fail ?? 0)) ?></div><div class="lbl">نیازمند توجه</div></div>
</div>

<?php $groups = []; foreach (($checks ?? []) as $c) { $groups[$c['group']][] = $c; } ?>
<?php foreach ($groups as $group => $items): ?>
  <div class="panel">
    <h3><?= e($group) ?></h3>
    <table class="tbl">
      <?php foreach ($items as $c): ?>
        <tr>
          <td style="width:26px"><span class="status-dot <?= $c['ok'] ? 'ok' : 'bad' ?>"></span></td>
          <td><b><?= e($c['label']) ?></b></td>
          <td class="muted" dir="auto"><?= e($c['detail']) ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>
<?php endforeach; ?>
