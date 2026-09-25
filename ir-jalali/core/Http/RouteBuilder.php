<?php

declare(strict_types=1);

namespace IRJalali\Core\Http;

final class RouteBuilder
{
    public function __construct(
        private readonly Router $router,
        private readonly int $index,
    ) {
    }

    public function name(string $name): self
    {
        $this->router->setRouteName($this->index, $name);

        return $this;
    }

    public function middleware(string ...$middleware): self
    {
        $this->router->addRouteMiddleware($this->index, ...$middleware);

        return $this;
    }
}
