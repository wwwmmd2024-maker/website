<?php /** Flash message (form submissions, newsletter). Data: $flash, $e. */ ?>
<?php if (!empty($flash) && is_array($flash)): ?>
<div class="ij-flash ij-flash-<?= $e($flash['type'] ?? 'info') ?>" role="status"><?= $e($flash['message'] ?? '') ?></div>
<?php endif; ?>
