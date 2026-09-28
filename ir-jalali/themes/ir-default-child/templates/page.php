<?php
/**
 * IR Default Child — page override (proves child-first resolution).
 * Falls back to the parent singular wrapper via ThemeManager.
 */
$content = '<div class="ij-child-banner">✦ این برگه با قالب چایلد رندر شده است (اورراید موفق).</div>' . ($content ?? '');
$parentFile = $themeManager->file($theme->parent ?? 'ir-default', 'templates/singular.php');
if ($parentFile !== null) {
    require $parentFile;
} else {
    echo $content;
}
