<?php
$__layout = 'layouts.guest';
$title = $t->get('auth.login_title');
?>
<h1><?= e($t->get('auth.login_title')) ?></h1>
<p class="sub">برای مدیریت سایت وارد شوید.</p>
<form method="post" action="/admin/login">
  <?= $csrf->field() ?>
  <label><?= e($t->get('auth.username_email')) ?></label>
  <input type="text" name="login" dir="ltr" style="text-align:right" autocomplete="username" required autofocus>
  <label><?= e($t->get('auth.password')) ?></label>
  <input type="password" name="password" autocomplete="current-password" required>
  <button class="btn" type="submit"><?= e($t->get('auth.login')) ?></button>
</form>
