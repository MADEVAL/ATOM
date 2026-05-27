<?php
declare(strict_types=1);
namespace Atom\Http;

use Atom\Support\Regex;

final class Response
{
    private string $content = '';
    private StatusCode $status = StatusCode::OK;
    private array $headers = [];
    private array $cookies = [];

    public function __construct(string $content = '', StatusCode|int $status = StatusCode::OK, array $headers = [])
    {
        $this->content = $content;
        $this->status  = $status instanceof StatusCode ? $status : StatusCode::from($status);
        $this->headers = $headers;
    }

    public static function html(string $body, StatusCode $s = StatusCode::OK): self
    {
        return new self($body, $s, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    public static function json(mixed $data, StatusCode $s = StatusCode::OK, bool $pretty = false): self
    {
        $flags = JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | ($pretty ? JSON_PRETTY_PRINT : 0);
        return new self(json_encode($data, $flags), $s, ['Content-Type' => 'application/json']);
    }

    public static function text(string $body, StatusCode $s = StatusCode::OK): self
    {
        return new self($body, $s, ['Content-Type' => 'text/plain; charset=utf-8']);
    }

    public static function noContent(): self
    {
        return new self('', StatusCode::NO_CONTENT);
    }

    public static function redirect(string $url, StatusCode $s = StatusCode::FOUND): self
    {
        $low = strtolower($url);
        if (str_starts_with($low, 'javascript:') || str_starts_with($low, 'data:') || str_starts_with($low, 'vbscript:')) {
            $url = '/';
        }
        return new self('', $s, ['Location' => $url]);
    }

    public function withHeader(string $name, string $value): self
    {
        $clone = clone $this;
        $clone->headers[$name] = $value;
        return $clone;
    }

    public function withStatus(StatusCode $s): self
    {
        $clone = clone $this;
        $clone->status = $s;
        return $clone;
    }

    /** @param array{ttl?:int, path?:string, secure?:bool, httponly?:bool, samesite?:string, domain?:string} $options */
    public function withCookie(string $name, string $value, int|array $ttl_or_options = 3600, string $path = '/'): self
    {
        $clone = clone $this;
        if (is_array($ttl_or_options)) {
            $clone->cookies[] = ['name' => $name, 'value' => $value, ...$ttl_or_options];
        } else {
            $clone->cookies[] = ['name' => $name, 'value' => $value, 'ttl' => $ttl_or_options, 'path' => $path];
        }
        return $clone;
    }

    public function withCache(int $ttl): self
    {
        return $this->withHeader('Cache-Control', "public, max-age={$ttl}");
    }

    public function send(?bool $isHttps = null): void
    {
        $secure = $isHttps ?? (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        if (!headers_sent()) {
            http_response_code($this->status->value);
            foreach ($this->headers as $k => $v) {
                $k = Regex::replace('#[\r\n]+#', '', $k);
                $v = Regex::replace('#[\r\n]+#', '', $v);
                header("{$k}: {$v}");
            }
            foreach ($this->cookies as $cookie) {
                setcookie(
                    $cookie['name'],
                    $cookie['value'] ?? '',
                    [
                        'expires'  => time() + ($cookie['ttl'] ?? 3600),
                        'path'     => $cookie['path'] ?? '/',
                        'domain'   => $cookie['domain'] ?? '',
                        'httponly' => $cookie['httponly'] ?? true,
                        'samesite' => $cookie['samesite'] ?? 'Lax',
                        'secure'   => $cookie['secure'] ?? $secure,
                    ],
                );
            }
        }
        echo $this->content;
    }

    public function getContent(): string { return $this->content; }
    public function getStatusCode(): int { return $this->status->value; }
}
