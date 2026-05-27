<?php
declare(strict_types=1);
namespace Atom\Cache;

final readonly class FileDriver implements Driver
{
    private string $dir;
    private int $cleanupChance;

    public function __construct(
        string $dir,
        int $cleanupChance = 100,
    ) {
        $this->dir = rtrim($dir, '/\\');
        $this->cleanupChance = $cleanupChance;
    }

    public function get(string $key): mixed
    {
        $file = $this->path($key);
        if (!is_file($file)) {
            return null;
        }
        $data = @file_get_contents($file);
        if ($data === false) {
            return null;
        }
        $parts = explode("\n", $data, 2);
        if (count($parts) !== 2) {
            return null;
        }
        $expiry = (int) $parts[0];
        if ($expiry > 0 && time() > $expiry) {
            @unlink($file);
            return null;
        }
        return unserialize($parts[1]);
    }

    public function set(string $key, mixed $value, int $ttl): void
    {
        $this->ensureDir();
        $this->maybeCleanup();

        $expiry = $ttl > 0 ? time() + $ttl : 0;
        $content = $expiry . "\n" . serialize($value);
        $file = $this->path($key);
        $tmp = $file . '.' . bin2hex(random_bytes(8)) . '.tmp';

        if (file_put_contents($tmp, $content, LOCK_EX) === false) {
            return;
        }
        rename($tmp, $file);
    }

    public function has(string $key): bool
    {
        $file = $this->path($key);
        if (!is_file($file)) {
            return false;
        }
        $data = @file_get_contents($file);
        if ($data === false) {
            return false;
        }
        $pos = strpos($data, "\n");
        if ($pos === false) {
            return false;
        }
        $expiry = (int) substr($data, 0, $pos);
        if ($expiry > 0 && time() > $expiry) {
            @unlink($file);
            return false;
        }
        return true;
    }

    public function delete(string $key): void
    {
        $file = $this->path($key);
        if (is_file($file)) {
            @unlink($file);
        }
    }

    public function flush(): void
    {
        if (!is_dir($this->dir)) {
            return;
        }
        foreach ((array) glob($this->dir . '/*.php') as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }

    private function path(string $key): string
    {
        return $this->dir . '/' . sha1($key) . '.php';
    }

    private function ensureDir(): void
    {
        if (!is_dir($this->dir) && !@mkdir($this->dir, \Atom\Constants::DIR_PERMISSIONS, true) && !is_dir($this->dir)) {
            throw new \RuntimeException("Cache: cannot create directory '{$this->dir}'");
        }
    }

    private function maybeCleanup(): void
    {
        if (random_int(1, $this->cleanupChance) !== 1) {
            return;
        }
        $now = time();
        foreach ((array) glob($this->dir . '/*.php') as $file) {
            if (!is_file($file)) continue;
            $data = @file_get_contents($file);
            if ($data === false) continue;
            $pos = strpos($data, "\n");
            if ($pos === false) continue;
            $expiry = (int) substr($data, 0, $pos);
            if ($expiry > 0 && $now > $expiry) {
                @unlink($file);
            }
        }
    }
}
