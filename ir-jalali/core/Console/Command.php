<?php

declare(strict_types=1);

namespace IRJalali\Core\Console;

/**
 * Base class for all `irj` commands (core + generated).
 *
 * @phpstan-type ArgvArgs list<string>
 * @phpstan-type ArgvOptions array<string, string|bool>
 */
abstract class Command
{
    abstract public function name(): string;

    abstract public function description(): string;

    /** e.g. "plugin:install <slug> [--zip=path] [--activate]" */
    public function usage(): string
    {
        return $this->name();
    }

    /**
     * @param list<string> $args
     * @param array<string, string|bool> $options
     */
    abstract public function handle(array $args, array $options, Output $out): int;

    /** @param array<string, string|bool> $options */
    protected function option(array $options, string $key, mixed $default = null): mixed
    {
        return $options[$key] ?? $default;
    }

    /** @param array<string, string|bool> $options */
    protected function flag(array $options, string $key): bool
    {
        $value = $options[$key] ?? false;
        if (is_string($value)) {
            return !in_array(strtolower($value), ['0', 'false', 'no'], true);
        }

        return (bool) $value;
    }
}
