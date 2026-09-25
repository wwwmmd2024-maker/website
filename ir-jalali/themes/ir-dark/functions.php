<?php

declare(strict_types=1);

/** IR Dark theme functions. Scope: $theme, $hooks, $app. */

$hooks->addFilter('theme.body_class', function (string $classes): string {
    return trim($classes . ' theme-ir-dark');
});
