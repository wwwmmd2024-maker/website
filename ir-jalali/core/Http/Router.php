<?php

declare(strict_types=1);

namespace IRJalali\Core\Http;

use Closure;
use IRJalali\Core\Kernel\Container;

/**
 * Small but real router: named routes, {params}, groups, middleware pipeline.
 *
 * Handler formats:
 *  - [ControllerClass::class, 'method']
 *  - Closure(Request): Response
 */
final class Router
{
    /** @var list<array{methods: list<string>, path: string, regex: string, params: list<string>, handler: mixed, middleware: list<string>, name: string|null}> */
    private array $routes = [];

    /** @var array<string, string> name => path */
    private array $namedPaths = [];

    /** @var list<array{prefix: string, middleware: list<string>}> */
    private array $groupStack = [];

    private ?Container $container = null;

    public function setContainer(Container $container): void
    {
        $this->container = $container;
    }

    public function get(string $path, mixed $handler): RouteBuilder
    {
        return $this->add(['GET'], $path, $handler);
    }

    public function post(string $path, mixed $handler): RouteBuilder
    {
        return $this->add(['POST'], $path, $handler);
    }

    /** @param list<string> $methods */
    public function match(array $methods, string $path, mixed $handler): RouteBuilder
    {
        return $this->add(array_map('strtoupper', $methods), $path, $handler);
    }

    /** @param list<string> $methods */
    private function add(array $methods, string $path, mixed $handler): RouteBuilder
    {
        $prefix = '';
        $middleware = [];
        foreach ($this->groupStack as $group) {
            $prefix .= $group['prefix'];
            array_push($middleware, ...$group['middleware']);
        }
        $fullPath = '/' . trim($prefix . '/' . trim($path, '/'), '/');
        if ($fullPath === '') {
            $fullPath = '/';
        }

        [$regex, $params] = $this->compile($fullPath);
        $index = count($this->routes);
        $this->routes[] = [
            'methods' => $methods,
            'path' => $fullPath,
            'regex' => $regex,
            'params' => $params,
            'handler' => $handler,
            'middleware' => $middleware,
            'name' => null,
        ];

        return new RouteBuilder($this, $index);
    }

    /** @param array{prefix?: string, middleware?: list<string>} $options */
    public function group(array $options, callable $callback): void
    {
        $this->groupStack[] = [
            'prefix' => isset($options['prefix']) ? '/' . trim($options['prefix'], '/') : '',
            'middleware' => $options['middleware'] ?? [],
        ];
        $callback($this);
        array_pop($this->groupStack);
    }

    /** @return array{0: string, 1: list<string>} */
    private function compile(string $path): array
    {
        $params = [];
        $regex = preg_replace_callback('/\{([a-zA-Z_][a-zA-Z0-9_]*)([?*])?\}/', function ($m) use (&$params) {
            $params[] = $m[1];
            $modifier = $m[2] ?? '';
            if ($modifier === '*') {
                return '(.+)'; // wildcard: may contain slashes (theme assets)
            }

            return $modifier === '?' ? '(?:/([^/]+))?' : '([^/]+)';
        }, $path);
        // Fix optional leading slash duplication: pattern above already includes slash.
        $regex = str_replace('//(?:', '/(?:', (string) $regex);

        return ['#^' . $regex . '/?$#u', $params];
    }

    public function setRouteName(int $index, string $name): void
    {
        $this->routes[$index]['name'] = $name;
        $this->namedPaths[$name] = $this->routes[$index]['path'];
    }

    public function addRouteMiddleware(int $index, string ...$middleware): void
    {
        array_push($this->routes[$index]['middleware'], ...$middleware);
    }

    /** @param array<string, string> $params */
    public function url(string $name, array $params = []): string
    {
        if (!isset($this->namedPaths[$name])) {
            return '/';
        }
        $path = $this->namedPaths[$name];
        foreach ($params as $key => $value) {
            $path = str_replace('{' . $key . '}', $value, $path);
            $path = str_replace('{' . $key . '?}', $value, $path);
        }
        $path = (string) preg_replace('/\{[a-zA-Z_][a-zA-Z0-9_]*\?\}/', '', $path);

        return $path === '' ? '/' : $path;
    }

    public function dispatch(Request $request): Response
    {
        $pathMatched = false;
        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $request->path(), $matches)) {
                continue;
            }
            $pathMatched = true;
            if (!in_array($request->method(), $route['methods'], true)) {
                continue;
            }
            foreach ($route['params'] as $i => $name) {
                $request->setRouteParam($name, $matches[$i + 1] ?? null);
            }

            $handler = $route['handler'];
            $pipeline = fn(Request $req) => $this->invoke($handler, $req);

            foreach (array_reverse($route['middleware']) as $middlewareClass) {
                $pipeline = function (Request $req) use ($middlewareClass, $pipeline) {
                    $middleware = $this->container !== null
                        ? $this->container->make($middlewareClass)
                        : new $middlewareClass();
                    if (!$middleware instanceof Middleware) {
                        throw new \RuntimeException("Middleware [{$middlewareClass}] must implement Middleware.");
                    }

                    return $middleware->handle($req, $pipeline);
                };
            }

            return $pipeline($request);
        }

        if ($pathMatched) {
            return Response::text('Method Not Allowed', 405);
        }

        return Response::text('Not Found', 404);
    }

    private function invoke(mixed $handler, Request $request): Response
    {
        if ($handler instanceof Closure) {
            $result = $handler($request);
        } elseif (is_array($handler) && count($handler) === 2 && is_string($handler[0])) {
            $controller = $this->container !== null
                ? $this->container->make($handler[0])
                : new $handler[0]();
            $result = $controller->{$handler[1]}($request);
        } else {
            throw new \RuntimeException('Invalid route handler.');
        }

        if ($result instanceof Response) {
            return $result;
        }
        if (is_string($result)) {
            return Response::html($result);
        }

        throw new \RuntimeException('Route handler must return Response or string.');
    }
}
