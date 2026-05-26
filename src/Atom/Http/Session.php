<?php
declare(strict_types=1);
namespace Atom\Http;

final class Session
{
    private array $flashed = [];

    public function __construct()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $this->flashed = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $this->flashed[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public function flash(string $key, mixed $value): void
    {
        $_SESSION['_flash'][$key] = $value;
    }

    public function regenerate(bool $deleteOld = true): void
    {
        session_regenerate_id($deleteOld);
    }

    public function csrfToken(): string
    {
        if (!isset($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    public function validateCsrf(string $token): bool
    {
        return hash_equals($this->csrfToken(), $token);
    }
}
