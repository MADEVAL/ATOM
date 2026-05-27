<?php
declare(strict_types=1);
namespace Atom\Cache;

interface Driver
{
    public function get(string $key): mixed;
    public function set(string $key, mixed $value, int $ttl): void;
    public function has(string $key): bool;
    public function delete(string $key): void;
    public function flush(): void;
}
