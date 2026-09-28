<?php

declare(strict_types=1);

namespace IRJalali\Core\Widgets;

use IRJalali\Core\Builder\RenderContext;

/**
 * Immutable widget definition (sidebar components).
 */
final class WidgetDefinition
{
    /**
     * @param array<string, mixed> $schema editor field schema
     * @param array<string, mixed> $defaults
     * @param callable $render fn(array $data, RenderContext $ctx): string
     */
    public function __construct(
        public readonly string $slug,
        public readonly string $title,
        public readonly string $description = '',
        public readonly string $icon = '◧',
        public readonly array $schema = [],
        public readonly array $defaults = [],
        public readonly mixed $render = null,
        public readonly string $source = 'core',
        public readonly string $version = '1.0.0',
    ) {
    }

    /** @param array<string, mixed> $data */
    public function render(array $data, RenderContext $ctx): string
    {
        if (!is_callable($this->render)) {
            return '';
        }

        return (string) ($this->render)(array_merge($this->defaults, $data), $ctx);
    }

    /** @return array<string, mixed> */
    public function metadata(): array
    {
        return [
            'slug' => $this->slug,
            'title' => $this->title,
            'description' => $this->description,
            'icon' => $this->icon,
            'schema' => $this->schema,
            'defaults' => $this->defaults,
            'source' => $this->source,
            'version' => $this->version,
        ];
    }
}
