<?php

declare(strict_types=1);

namespace IRJalali\Core\Translation;

/**
 * Array-file based translator. Default locale fa_IR, fallback en_US.
 */
final class Translator
{
    /** @var array<string, array<string, string>> */
    private array $loaded = [];

    private string $fallback = 'en_US';

    public function __construct(
        private readonly string $langPath,
        private string $locale = 'fa_IR',
    ) {
    }

    public function setLocale(string $locale): void
    {
        $this->locale = $locale;
    }

    public function locale(): string
    {
        return $this->locale;
    }

    public function setFallback(string $locale): void
    {
        $this->fallback = $locale;
    }

    public function isRtl(): bool
    {
        return in_array($this->locale, ['fa_IR', 'ar_SA', 'ur_PK', 'he_IL'], true);
    }

    /** @param array<string, string> $replace */
    public function get(string $key, array $replace = []): string
    {
        $line = $this->line($this->locale, $key) ?? $this->line($this->fallback, $key) ?? $key;
        foreach ($replace as $name => $value) {
            $line = str_replace(':' . $name, (string) $value, $line);
        }

        return $line;
    }

    private function line(string $locale, string $key): ?string
    {
        if (!isset($this->loaded[$locale])) {
            $file = $this->langPath . '/' . $locale . '.php';
            $this->loaded[$locale] = is_file($file) ? (require $file) : [];
            if (!is_array($this->loaded[$locale])) {
                $this->loaded[$locale] = [];
            }
        }

        $value = $this->loaded[$locale][$key] ?? null;

        return is_string($value) ? $value : null;
    }
}
