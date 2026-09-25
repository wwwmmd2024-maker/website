<?php

declare(strict_types=1);

namespace IRJalali\Core\View;

use IRJalali\Core\Date\DateService;
use IRJalali\Core\Security\Csrf;
use IRJalali\Core\Security\Sanitize;
use IRJalali\Core\Translation\Translator;

/**
 * Native-PHP template renderer with layouts, sections and auto-escape helpers.
 * Views receive: $t (translator), $csrf, $dates, $auth user, flashed messages.
 */
final class View
{
    /** @var array<string, mixed> */
    private array $shared = [];

    public function __construct(
        private readonly string $viewPath,
        private readonly Translator $translator,
        private readonly Csrf $csrf,
        private readonly DateService $dates,
    ) {
    }

    public function share(string $key, mixed $value): void
    {
        $this->shared[$key] = $value;
    }

    /** @param array<string, mixed> $data */
    public function render(string $view, array $data = []): string
    {
        $file = $this->viewPath . '/' . str_replace('.', '/', $view) . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("View [{$view}] not found.");
        }

        $t = $this->translator;
        $csrf = $this->csrf;
        $dates = $this->dates;
        $data = array_merge($this->shared, $data);

        $sectionContents = [];
        $layout = null;

        $section = function (string $name) use (&$sectionContents): void {
            ob_start();
            $sectionContents[$name] = null; // placeholder, filled on endSection
        };
        // We implement sections via explicit start/end sweeping below instead.
        unset($section);

        extract($data, EXTR_SKIP);
        ob_start();
        require $file;
        $content = (string) ob_get_clean();

        // Layout support: views may set $__layout and $__sections.
        if (isset($__layout) && is_string($__layout)) {
            $layoutFile = $this->viewPath . '/' . str_replace('.', '/', $__layout) . '.php';
            if (!is_file($layoutFile)) {
                throw new \RuntimeException("Layout [{$__layout}] not found.");
            }
            $sections = isset($__sections) && is_array($__sections) ? $__sections : [];
            $sections['content'] = $sections['content'] ?? $content;
            extract($data, EXTR_SKIP);
            ob_start();
            require $layoutFile;

            return (string) ob_get_clean();
        }

        return $content;
    }

    public function esc(mixed $value): string
    {
        return Sanitize::html($value);
    }

    public function csrfField(): string
    {
        return $this->csrf->field();
    }

    public function translator(): Translator
    {
        return $this->translator;
    }

    public function dates(): DateService
    {
        return $this->dates;
    }
}
