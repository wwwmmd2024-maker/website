<?php

declare(strict_types=1);

namespace IRJalali\Core\Builder;

final class RenderedOutput
{
    /** @param list<string> $assets collected block asset handles */
    public function __construct(
        public readonly string $html,
        public readonly string $css,
        public readonly array $assets = [],
    ) {
    }
}
