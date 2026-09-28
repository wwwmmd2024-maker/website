<?php

declare(strict_types=1);

/** IR Default Child functions. Scope: $theme, $hooks, $app. */

$hooks->addFilter('theme.body_class', function (string $classes): string {
    return trim($classes . ' theme-ir-child');
});

$hooks->addFilter('theme.footer_credit', function (string $credit): string {
    return trim($credit . ' · نسخه چایلد');
});
