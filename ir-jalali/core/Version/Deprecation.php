<?php

declare(strict_types=1);

namespace IRJalali\Core\Version;

use IRJalali\Core\Logging\Logger;

/**
 * Deprecation system: deprecated APIs keep working, emit a warning
 * (once per request) and point to the migration guide.
 */
final class Deprecation
{
    /** @var array<string, true> */
    private static array $emitted = [];

    public function __construct(private readonly ?Logger $logger = null)
    {
    }

    public static function trigger(string $old, string $new, string $removeIn, ?Logger $logger = null): void
    {
        $key = $old . '|' . $new;
        if (isset(self::$emitted[$key])) {
            return;
        }
        self::$emitted[$key] = true;
        $message = "[DEPRECATED] {$old} is deprecated, use {$new}. It will be removed in {$removeIn}. See docs/PLUGIN_API.md#deprecations.";
        $logger?->channel('app')->warning($message);
        if (function_exists('trigger_error')) {
            @trigger_error($message, E_USER_DEPRECATED);
        }
    }

    public function warn(string $old, string $new, string $removeIn): void
    {
        self::trigger($old, $new, $removeIn, $this->logger);
    }
}
