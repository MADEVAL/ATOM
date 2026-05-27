<?php
declare(strict_types=1);
namespace Atom\Container;

use ReflectionClass, ReflectionNamedType;

final class Container
{
    private array $bindings = [];
    private array $singletons = [];
    private array $instances = [];
    private array $resolving = [];

    public function bind(string $abstract, mixed $concrete): void
    {
        $this->bindings[$abstract] = $concrete;
    }

    public function singleton(string $abstract, mixed $concrete): void
    {
        $this->singletons[$abstract] = $concrete;
    }

    public function instance(string $abstract, object $value): void
    {
        $this->instances[$abstract] = $value;
    }

    public function make(string $abstract, array $params = []): object
    {
        if (isset($this->instances[$abstract])) return $this->instances[$abstract];

        if (isset($this->resolving[$abstract])) {
            $chain = implode(' -> ', array_keys($this->resolving)) . " -> {$abstract}";
            throw new \RuntimeException("Circular dependency detected: {$chain}");
        }
        $this->resolving[$abstract] = true;

        try {
            $concrete = $this->singletons[$abstract] ?? $this->bindings[$abstract] ?? $abstract;

            $obj = match (true) {
                is_callable($concrete) && !is_string($concrete) => $concrete($this, $params),
                is_string($concrete) && class_exists($concrete) => $this->autowire($concrete, $params),
                default => throw new \RuntimeException("Cannot resolve {$abstract}"),
            };

            if (isset($this->singletons[$abstract])) $this->instances[$abstract] = $obj;
            return $obj;
        } finally {
            unset($this->resolving[$abstract]);
        }
    }

    private function autowire(string $class, array $params): object
    {
        $ref = new ReflectionClass($class);
        $ctor = $ref->getConstructor();
        if ($ctor === null) return new $class();

        $args = [];
        foreach ($ctor->getParameters() as $p) {
            $name = $p->getName();
            if (array_key_exists($name, $params)) { $args[] = $params[$name]; continue; }

            $type = $p->getType();
            if ($type instanceof ReflectionNamedType && !$type->isBuiltin() && (class_exists($type->getName()) || interface_exists($type->getName()))) {
                $args[] = $this->make($type->getName());
                continue;
            }
            if ($p->isDefaultValueAvailable()) { $args[] = $p->getDefaultValue(); continue; }
            throw new \RuntimeException("Cannot resolve param \${$name} of {$class}");
        }
        return $ref->newInstanceArgs($args);
    }
}
