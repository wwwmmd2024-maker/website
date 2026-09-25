<?php
$__layout = 'layouts.admin';
$active = 'users';
$title = $t->get('users.title');
?>
<div class="panel">
  <h3><?= e($t->get('users.title')) ?> (<?= e($dates->toPersianDigits((string) ($total ?? 0))) ?>)</h3>
  <table class="tbl">
    <tr>
      <th>#</th><th><?= e($t->get('users.username')) ?></th><th><?= e($t->get('users.email')) ?></th>
      <th><?= e($t->get('users.roles')) ?></th><th><?= e($t->get('users.created')) ?></th>
    </tr>
    <?php foreach (($users ?? []) as $u): ?>
      <tr>
        <td><?= e((string) $u->id) ?></td>
        <td><b><?= e($u->displayName) ?></b> <span style="color:var(--muted)" dir="ltr">@<?= e($u->username) ?></span></td>
        <td dir="ltr"><?= e($u->email) ?></td>
        <td><?php foreach ($u->roles as $role): ?><span class="badge"><?= e($role) ?></span> <?php endforeach; ?></td>
        <td><?= e($u->createdAt ? $dates->format($u->createdAt) : '—') ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
</div>
