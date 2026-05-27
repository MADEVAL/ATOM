<?php
declare(strict_types=1);
namespace Atom\Cache;

final class ArrayDriver implements Driver
{
    /** @var array<string,int> */
    private array $expiry = [];
    /** @var array<string,mixed> */
    private array $store = [];

    public function get(string $key): mixed
    {
        if (!isset($this->store[$key])) {
            return null;
        }
        if ($this->expired($key)) {
            $this->delete($key);
            return null;
        }
        return $this->store[$key];
    }

    public function set(string $key, mixed $value, int $ttl): void
    {
        $this->store[$key] = $value;
        $this->expiry[$key] = $ttl > 0 ? time() + $ttl : 0;
    }

    public function has(string $key): bool
    {
        if (!array_key_exists($key, $this->store)) {
            return false;
        }
        if ($this->expired($key)) {
            $this->delete($key);
            return false;
        }
        return true;
    }

    public function delete(string $key): void
    {
        unset($this->store[$key], $this->expiry[$key]);
    }

    public function flush(): void
    {
        $this->store = [];
        $this->expiry = [];
    }

    private function expired(string $key): bool
    {
        $exp = $this->expiry[$key] ?? 0;
        return $exp > 0 && time() > $exp;
    }
}
