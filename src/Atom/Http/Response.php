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

    public static function redirect(string $url, StatusCode $s = StatusCode::FOUND): self
    {
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

    public function withCookie(string $name, string $value, int $ttl = 3600, string $path = '/'): self
    {
        $clone = clone $this;
        $clone->cookies[] = [$name, $value, $ttl, $path];
        return $clone;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status->value);
            foreach ($this->headers as $k => $v) {
                $v = Regex::replace('#[\r\n]+#', ' ', $v);
                header("{$k}: {$v}");
            }
            foreach ($this->cookies as [$name, $value, $ttl, $path]) {
                setcookie($name, $value, [
                    'expires'  => time() + $ttl,
                    'path'     => $path,
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]);
            }
        }
        echo $this->content;
    }

    public function getContent(): string { return $this->content; }
    public function getStatusCode(): int { return $this->status->value; }
}
