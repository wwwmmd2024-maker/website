<?php

declare(strict_types=1);

namespace IRJalali\Core\Kernel;

use Closure;
use ReflectionClass;
use RuntimeException;

/**
 * Simple PSR-11 inspired Dependency Injection Container with auto-wiring.
 */
final class Container
{
    /** @var array<string, array{concrete: mixed, shared: bool}> */
    private array $bindings = [];

    /** @var array<string, mixed> */
    private array $instances = [];

    public function bind(string $abstract, mixed $concrete = null): void
    {
        $this->bindings[$abstract] = ['concrete' => $concrete ?? $abstract, 'shared' => false];
    }

    public function singleton(string $abstract, mixed $concrete = null): void
    {
        $this->bindings[$abstract] = ['concrete' => $concrete ?? $abstract, 'shared' => true];
    }

    public function instance(string $abstract, mixed $instance): void
    {
        $this->instances[$abstract] = $instance;
    }

    public function has(string $abstract): bool
    {
        return isset($this->instances[$abstract]) || isset($this->bindings[$abstract]);
    }

    /**
     * @template T
     * @param class-string<T>|string $abstract
     * @return T|mixed
     */
    public function make(string $abstract): mixed
    {
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        $concrete = $this->bindings[$abstract]['concrete'] ?? $abstract;
        $shared = $this->bindings[$abstract]['shared'] ?? false;

        $object = $concrete instanceof Closure
            ? $concrete($this)
            : $this->build(is_string($concrete) ? $concrete : $abstract);

        if ($shared) {
            $this->instances[$abstract] = $object;
        }

        return $object;
    }

    private function build(string $class): object
    {
        if (!class_exists($class)) {
            throw new RuntimeException("Cannot resolve [{$class}]: class not found.");
        }

        $ref = new ReflectionClass($class);
        if (!$ref->isInstantiable()) {
            throw new RuntimeException("Cannot resolve [{$class}]: not instantiable.");
        }

        $ctor = $ref->getConstructor();
        if ($ctor === null || $ctor->getNumberOfParameters() === 0) {
            return $ref->newInstance();
        }

        $deps = [];
        foreach ($ctor->getParameters() as $param) {
            $type = $param->getType();
            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                $deps[] = $this->make($type->getName());
            } elseif ($param->isDefaultValueAvailable()) {
                $deps[] = $param->getDefaultValue();
            } else {
                throw new RuntimeException(
                    "Cannot resolve parameter [\${$param->getName()}] of [{$class}]."
                );
            }
        }

        return $ref->newInstanceArgs($deps);
    }
}
