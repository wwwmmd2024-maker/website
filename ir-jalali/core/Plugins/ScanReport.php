<?php

declare(strict_types=1);

namespace IRJalali\Core\Plugins;

/** Immutable result of a package security scan. */
final class ScanReport
{
    /** @param list<string> $errors @param list<string> $warnings */
    public function __construct(
        public readonly array $errors,
        public readonly array $warnings,
        public readonly int $files,
    ) {
    }

    public function safe(): bool
    {
        return $this->errors === [];
    }
}
