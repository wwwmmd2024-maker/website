<?php

declare(strict_types=1);

namespace IRJalali\Core\Security;

/**
 * Output escaping + filename sanitization helpers.
 */
final class Sanitize
{
    public static function html(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function attr(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function url(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        // Block dangerous schemes.
        if (preg_match('/^\s*(javascript|data|vbscript|file):/i', $url)) {
            return '#';
        }

        return htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function filename(string $name): string
    {
        $name = (string) preg_replace('/\x00/', '', $name);
        $name = basename(str_replace('\\', '/', $name));
        // Keep letters (incl. Persian/Arabic), digits, dot, dash, underscore.
        $name = (string) preg_replace('/[^\p{L}\p{N}._-]+/u', '-', $name);
        $name = trim($name, '.-');
        if ($name === '' || $name === '.' || $name === '..') {
            $name = 'file-' . date('Ymd-His');
        }
        if (strlen($name) > 150) {
            $ext = pathinfo($name, PATHINFO_EXTENSION);
            $name = substr($name, 0, 140) . ($ext !== '' ? '.' . $ext : '');
        }

        return $name;
    }

    public static function slug(string $text): string
    {
        $text = trim(mb_strtolower($text, 'UTF-8'));
        $text = (string) preg_replace('/[\s_]+/u', '-', $text);
        $text = (string) preg_replace('/[^\p{L}\p{N}\-]+/u', '', $text);
        $text = trim($text, '-');

        return $text === '' ? 'n-' . substr(md5((string) microtime(true)), 0, 8) : $text;
    }

    public static function stripTags(string $html, string $allowed = ''): string
    {
        return strip_tags($html, $allowed);
    }

    /**
     * Defense-in-depth filter for admin-authored rich text.
     * Removes executable constructs; NOT a full HTML purifier —
     * untrusted input must additionally be sanitized at save time.
     */
    public static function richText(?string $html): string
    {
        if ($html === null || $html === '') {
            return '';
        }
        $html = (string) preg_replace('/<\s*(script|iframe|object|embed|link|meta|base|form|input|button)[^>]*>.*?<\s*\/\s*\1\s*>/is', '', $html);
        $html = (string) preg_replace('/<\s*(script|iframe|object|embed|link|meta|base)[^>]*\/?>/i', '', $html);
        $html = (string) preg_replace('/\s+on\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);
        $html = (string) preg_replace('/(href|src|xlink:href)\s*=\s*(["\']?)\s*javascript:[^"\'>\s]*/i', '$1=$2#', $html);

        return $html;
    }
}
