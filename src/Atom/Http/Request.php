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

    public string $method  { get => ($this->server['REQUEST_METHOD'] ?? 'GET') === 'POST'
        ? strtoupper($this->body['_method'] ?? 'POST')
        : strtoupper($this->server['REQUEST_METHOD'] ?? 'GET'); }
    public string $path    { get => ($this->server['PATH_INFO'] ?? '') !== '' ? $this->server['PATH_INFO'] : (parse_url($this->uri, PHP_URL_PATH) ?: '/'); }
    public string $uri     { get => $this->server['REQUEST_URI'] ?? '/'; }
    public string $scheme  { get => ($this->server['HTTPS'] ?? '') === 'on' ? 'https' : 'http'; }
    public string $host    { get => (string)($this->server['HTTP_HOST'] ?? 'localhost'); }
    public string $ip      { get => (string)($this->server['REMOTE_ADDR'] ?? '127.0.0.1'); }
    public bool   $isAjax  { get => ($this->server['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest'; }
    public string $accept  { get => (string)($this->server['HTTP_ACCEPT'] ?? '*/*'); }
    public string $bearer  { get {
        $m = Regex::match('/^Bearer\s+(.+)$/i', $this->header('Authorization'));
        return $m !== null ? $m[1] : '';
    }}

    public function __construct(
        array $query   = [],
        array $body    = [],
        array $cookies = [],
        array $files   = [],
        ?array $server = null,
        ?array $headers = null,
    ) {
        $this->query   = $query;
        $this->cookies = $cookies;
        $this->files   = $files;
        $this->server  = $server !== null ? $server : $_SERVER;
        $this->body    = $body !== [] ? $body : $this->parseJsonBody($this->server);
        $this->headers = $headers ?? $this->extractHeaders($this->server);
    }

    public static function capture(): self
    {
        return new self($_GET, $_POST, $_COOKIE, $_FILES, $_SERVER);
    }

    public function header(string $name, string $default = ''): string
    {
        $low = strtolower($name);
        $key = strtoupper(str_replace('-', '_', $low));
        $key = "HTTP_{$key}";
        return (string)($this->headers[$low] ?? $this->server[$key] ?? $default);
    }

    public function wantsJson(): bool
    {
        return (bool) Regex::match('#application/(.*\+)?json#i', $this->accept);
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    public function file(string $key): UploadedFile
    {
        return isset($this->files[$key]) && $this->files[$key]['error'] !== UPLOAD_ERR_NO_FILE
            ? UploadedFile::fromFileArray($this->files[$key])
            : UploadedFile::empty();
    }

    /**
     * @template T of object
     * @param class-string<T> $dtoClass
     * @return T
     */
    public function validate(string $dtoClass): object
    {
        $ref = new \ReflectionClass($dtoClass);
        $dto = $ref->newInstanceWithoutConstructor();
        foreach ($ref->getProperties(\ReflectionProperty::IS_PUBLIC) as $prop) {
            $name = $prop->getName();
            if (array_key_exists($name, $this->body)) {
                $prop->setValue($dto, $this->body[$name]);
            }
        }
        $errors = \Atom\Validation\Validator::validate($dto);
        if ($errors !== []) {
            throw new \Atom\Validation\ValidationException($errors);
        }
        return $dto;
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

    private function parseJsonBody(array $server): array
    {
        if (!isset($server['HTTP_CONTENT_TYPE']) || !str_starts_with($server['HTTP_CONTENT_TYPE'], 'application/json')) {
            return [];
        }
        $maxLength = ini_parse_quantity(ini_get('post_max_size')) ?: 8_388_608;
        $contentLength = (int) ($server['HTTP_CONTENT_LENGTH'] ?? $server['CONTENT_LENGTH'] ?? 0);
        if ($contentLength > $maxLength) {
            return [];
        }
        $raw = file_get_contents('php://input', false, null, 0, $maxLength);
        return $raw !== false ? (json_decode($raw, true) ?? []) : [];
    }
}
