<?php

declare(strict_types=1);

return [
    // Days after expiry during which updates keep working.
    'grace_days' => 14,
    // Remote re-verification cadence (hours). Offline keeps last status.
    'verify_cache_hours' => 24,
];
