<?php
declare(strict_types=1);
namespace Atom\Http;

use Atom\Support\Regex;

final class Request
{
    public array $query;
    public array $body;
    public array $cookies;
    public array $files;
    public array $server;
    public array $headers;

    public string $method  { get => strtoupper($this->server['REQUEST_METHOD'] ?? 'GET'); }
    public string $path    { get => $this->server['PATH_INFO'] ?? parse_url($this->uri, PHP_URL_PATH) ?: '/'; }
    public string $uri     { get => $this->server['REQUEST_URI'] ?? '/'; }
    public string $scheme  { get => ($this->server['HTTPS'] ?? '') === 'on' ? 'https' : 'http'; }
    public string $host    { get => (string)($this->server['HTTP_HOST'] ?? 'localhost'); }
    public string $ip      { get => (string)($this->server['REMOTE_ADDR'] ?? '127.0.0.1'); }
    public bool   $isAjax  { get => ($this->server['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest'; }
    public string $accept  { get => (string)($this->server['HTTP_ACCEPT'] ?? '*/*'); }

    public function __construct(
        array $query   = [],
        array $body    = [],
        array $cookies = [],
        array $files   = [],
        array $server  = [],
        ?array $headers = null,
    ) {
        $this->query   = $query;
        $this->body    = $body;
        $this->cookies = $cookies;
        $this->files   = $files;
        $this->server  = $server ?: $_SERVER;
        $this->headers = $headers ?? $this->extractHeaders($this->server);
    }

    public static function capture(): self
    {
        return new self($_GET, $_POST, $_COOKIE, $_FILES, $_SERVER);
    }

    public function header(string $name, string $default = ''): string
    {
        $key = strtoupper($name);
        $key = str_replace('-', '_', $key);
        $key = "HTTP_{$key}";
        return (string)($this->headers[$name] ?? $this->server[$key] ?? $default);
    }

    public function wantsJson(): bool
    {
        return (bool) Regex::match('#application/(.*\+)?json#i', $this->accept);
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    private function extractHeaders(array $server): array
    {
        $out = [];
        foreach ($server as $k => $v) {
            if ($m = Regex::match('#^HTTP_(.+)$#', $k)) {
                $out[strtolower(str_replace('_', '-', $m[1]))] = $v;
            }
        }
        return $out;
    }
}
