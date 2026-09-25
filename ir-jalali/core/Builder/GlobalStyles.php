<?php

declare(strict_types=1);

namespace IRJalali\Core\Builder;

use IRJalali\App\Repositories\OptionRepository;

/**
 * Global design tokens → CSS variables (--ij-*).
 * Stored in options.global_styles as JSON; live-editable from the builder.
 */
final class GlobalStyles
{
    public const DEFAULTS = [
        'primary' => '#7c3aed',
        'secondary' => '#4f46e5',
        'accent' => '#06b6d4',
        'background' => '#ffffff',
        'surface' => '#f6f7fb',
        'text' => '#1c2240',
        'muted' => '#7a84a6',
        'border' => '#e3e7f2',
        'radius' => '14px',
        'shadow' => '0 18px 40px -18px rgba(80,70,180,.35)',
        'container' => '1200px',
        'font_base' => 'Tahoma, "Segoe UI", sans-serif',
        'font_head' => 'Tahoma, "Segoe UI", sans-serif',
    ];

    public function __construct(private readonly OptionRepository $options)
    {
    }

    /** @return array<string, string> */
    public function all(): array
    {
        $raw = $this->options->get('global_styles', '');
        $stored = is_string($raw) && $raw !== '' ? json_decode($raw, true) : [];
        if (!is_array($stored)) {
            $stored = [];
        }

        return array_merge(self::DEFAULTS, array_intersect_key($stored, self::DEFAULTS));
    }

    /** @param array<string, mixed> $tokens */
    public function save(array $tokens): void
    {
        $clean = [];
        foreach (self::DEFAULTS as $key => $default) {
            if (!array_key_exists($key, $tokens) || !is_scalar($tokens[$key])) {
                continue;
            }
            $value = trim((string) $tokens[$key]);
            if ($value === '' || strlen($value) > 200) {
                continue;
            }
            // Tokens must be plain CSS values — no tags, braces or url().
            if (str_contains($value, '<') || str_contains($value, '{') || str_contains($value, '}')
                || preg_match('/url\s*\(|expression\s*\(|javascript\s*:/i', $value)) {
                continue;
            }
            $clean[$key] = $value;
        }
        $merged = array_merge($this->all(), $clean);
        $this->options->set('global_styles', json_encode($merged, JSON_UNESCAPED_UNICODE));
    }

    public function cssVariables(): string
    {
        $tokens = $this->all();
        $lines = [':root{'];
        foreach ($tokens as $key => $value) {
            $var = '--ij-' . str_replace('_', '-', $key);
            $lines[] = $var . ':' . $value . ';';
        }
        $lines[] = '}';

        return implode('', $lines);
    }
}
