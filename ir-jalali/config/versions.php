<?php

declare(strict_types=1);

/**
 * API compatibility versions. Every package declares which API it targets;
 * installers refuse incompatible combinations instead of breaking at runtime.
 */
return [
    'core' => '1.1.0',
    'api' => [
        'plugin' => 'v1',
        'theme' => 'v1',
        'block' => 'v1',
        'widget' => 'v1',
        'module' => 'v1',
    ],
];
