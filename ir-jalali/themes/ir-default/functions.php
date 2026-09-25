<?php

declare(strict_types=1);

/**
 * IR Default theme functions.
 * Available in scope: $theme (Theme), $hooks (Hooks), $app (Application).
 */

$hooks->addFilter('theme.body_class', function (string $classes): string {
    return trim($classes . ' theme-ir-default');
});

$hooks->addFilter('theme.footer_credit', function (string $credit): string {
    return $credit;
});
