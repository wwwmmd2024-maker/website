<?php

declare(strict_types=1);

namespace IRJalali\Core\Blocks;

use IRJalali\Core\Builder\RenderContext;

/**
 * Immutable block definition. Registered at runtime by core, themes,
 * plugins and modules via BlockRegistry::register() — never by editing core.
 */
final class BlockDefinition
{
    /**
     * @param array<string, mixed> $schema editor field schema
     * @param array<string, mixed> $defaults default data
     * @param callable $render fn(array $data, RenderContext $ctx): string
     * @param array{css?: list<string>, js?: list<string>} $assets
     * @param list<string> $supports e.g. ['align','spacing','dynamic']
     */
    public function __construct(
        public readonly string $slug,
        public readonly string $title,
        public readonly string $category = 'general',
        public readonly string $icon = '▣',
        public readonly string $description = '',
        public readonly array $schema = [],
        public readonly array $defaults = [],
        public readonly mixed $render = null,
        public readonly array $assets = [],
        public readonly array $supports = [],
        public readonly ?string $permission = null,
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
            'category' => $this->category,
            'icon' => $this->icon,
            'description' => $this->description,
            'schema' => $this->schema,
            'defaults' => $this->defaults,
            'assets' => $this->assets,
            'supports' => $this->supports,
            'permission' => $this->permission,
            'source' => $this->source,
            'version' => $this->version,
        ];
    }
}
