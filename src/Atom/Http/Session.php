<?php
declare(strict_types=1);
namespace Atom\Http;

final class Session
{
    private array $flashed = [];

    /** @param array{cookie_secure?:bool,cookie_httponly?:bool,cookie_samesite?:string} $options */
    public function __construct(
        private array $options = [],
    ) {
        if (session_status() === PHP_SESSION_NONE) {
            $opts = $this->options;
            if (isset($opts['cookie_secure']) || isset($opts['cookie_httponly']) || isset($opts['cookie_samesite'])) {
                session_set_cookie_params([
                    'secure'   => $opts['cookie_secure'] ?? false,
                    'httponly' => $opts['cookie_httponly'] ?? true,
                    'samesite' => $opts['cookie_samesite'] ?? 'Lax',
                ]);
            }
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

    public function csrfToken(string $form = ''): string
    {
        $key = $form !== '' ? "_csrf_{$form}" : '_csrf';
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = bin2hex(random_bytes(32));
        }
        return $_SESSION[$key];
    }

    public function validateCsrf(string $token, string $form = ''): bool
    {
        return hash_equals($this->csrfToken($form), $token);
    }
}
