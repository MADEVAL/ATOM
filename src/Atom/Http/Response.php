<?php
declare(strict_types=1);
namespace Atom\Http;

use Atom\Support\Regex;

final class Response
{
    private string $content = '';
    private StatusCode $status = StatusCode::OK;
    private array $headers = [];

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

    public static function json(mixed $data, StatusCode $s = StatusCode::OK): self
    {
        $json = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        return new self($json, $s, ['Content-Type' => 'application/json']);
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

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status->value);
            foreach ($this->headers as $k => $v) {
                $v = Regex::replace('#[\r\n]+#', ' ', $v);
                header("{$k}: {$v}");
            }
        }
        echo $this->content;
    }

    public function getContent(): string { return $this->content; }
    public function getStatusCode(): int { return $this->status->value; }
}
