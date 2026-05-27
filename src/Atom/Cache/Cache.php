<?php
declare(strict_types=1);
namespace Atom\Cache;

final class Cache
{
    public function __construct(
        private readonly Driver $driver,
    ) {}

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->driver->get($key) ?? $default;
    }

    public function set(string $key, mixed $value, int $ttl = 0): void
    {
        $this->driver->set($key, $value, $ttl);
    }

    public function has(string $key): bool
    {
        return $this->driver->has($key);
    }

    public function delete(string $key): void
    {
        $this->driver->delete($key);
    }

    public function flush(): void
    {
        $this->driver->flush();
    }

    public function increment(string $key, int $amount = 1): int
    {
        $value = ((int) $this->get($key)) + $amount;
        $this->set($key, $value);
        return $value;
    }

    public function decrement(string $key, int $amount = 1): int
    {
        return $this->increment($key, -$amount);
    }

    public function remember(string $key, callable $factory, int $ttl = 0): mixed
    {
        if ($this->has($key)) {
            return $this->get($key);
        }
        $value = $factory();
        $this->set($key, $value, $ttl);
        return $value;
    }

    public function rememberForever(string $key, callable $factory): mixed
    {
        return $this->remember($key, $factory);
    }

    public function driver(): Driver
    {
        return $this->driver;
    }
}
