<?php

declare(strict_types=1);

namespace IRJalali\Core\Version;

/**
 * Semantic version constraint checker (composer-style subset):
 * "1.2.3", ">=1.0.0", "^1.2", "~1.2", ">=1.0 <2.0".
 */
final class Compatibility
{
    public static function satisfies(string $version, string $constraint): bool
    {
        $constraint = trim($constraint);
        if ($constraint === '' || $constraint === '*') {
            return true;
        }
        foreach (preg_split('/\s+/', $constraint) ?: [] as $part) {
            if (!self::satisfiesOne($version, $part)) {
                return false;
            }
        }

        return true;
    }

    private static function satisfiesOne(string $version, string $part): bool
    {
        if (str_starts_with($part, '^')) {
            $base = substr($part, 1);
            $segments = explode('.', $base);
            $major = (int) ($segments[0] ?? 0);
            $upper = $major > 0 ? ($major + 1) . '.0.0' : '0.' . ((int) ($segments[1] ?? 0) + 1) . '.0';

            return version_compare($version, $base, '>=') && version_compare($version, $upper, '<');
        }
        if (str_starts_with($part, '~')) {
            $base = substr($part, 1);
            $segments = explode('.', $base);
            $upper = ((int) ($segments[0] ?? 0)) . '.' . ((int) ($segments[1] ?? 0) + 1) . '.0';

            return version_compare($version, $base, '>=') && version_compare($version, $upper, '<');
        }
        if (preg_match('/^(>=|<=|>|<|=|==)?(.+)$/', $part, $m)) {
            $operator = $m[1] !== '' ? $m[1] : '==';

            return version_compare($version, trim($m[2]), $operator);
        }

        return false;
    }

    public static function isNewer(string $a, string $b): bool
    {
        return version_compare($a, $b, '>');
    }

    public static function valid(string $version): bool
    {
        return (bool) preg_match('/^\d+\.\d+\.\d+(-[a-z0-9.]+)?$/i', $version);
    }
}
