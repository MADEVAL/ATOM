<?php
declare(strict_types=1);
namespace Atom\Test;

use Atom\Application;
use Atom\Http\{Request, Response};

final class HttpClient
{
    private ?Response $response = null;

    public function __construct(
        private Application $app,
    ) {}

    public function get(string $uri, array $headers = []): self
    {
        return $this->send('GET', $uri, headers: $headers);
    }

    public function post(string $uri, array $body = [], array $headers = []): self
    {
        return $this->send('POST', $uri, $body, $headers);
    }

    public function put(string $uri, array $body = [], array $headers = []): self
    {
        return $this->send('PUT', $uri, $body, $headers);
    }

    public function patch(string $uri, array $body = [], array $headers = []): self
    {
        return $this->send('PATCH', $uri, $body, $headers);
    }

    public function delete(string $uri, array $headers = []): self
    {
        return $this->send('DELETE', $uri, headers: $headers);
    }

    public function send(string $method, string $uri, array $body = [], array $headers = []): self
    {
        $server = ['REQUEST_METHOD' => strtoupper($method), 'REQUEST_URI' => $uri];
        $req = new Request(body: $body, server: $server, headers: $headers);
        $this->app->container->instance(Request::class, $req);
        $this->response = $this->captureResponse($req);
        return $this;
    }

    public function assertOk(): self { return $this->assertStatus(200); }
    public function assertCreated(): self { return $this->assertStatus(201); }
    public function assertNoContent(): self { return $this->assertStatus(204); }
    public function assertNotFound(): self { return $this->assertStatus(404); }
    public function assertForbidden(): self { return $this->assertStatus(403); }
    public function assertServerError(): self { return $this->assertStatus(500); }

    public function assertStatus(int $code): self
    {
        if ($this->response === null) {
            throw new \RuntimeException('No response — call get/post/put/patch/delete first');
        }
        $actual = $this->response->getStatusCode();
        if ($actual !== $code) {
            throw new \RuntimeException(
                "Expected status {$code}, got {$actual}: {$this->response->getContent()}"
            );
        }
        return $this;
    }

    public function assertJson(array $expected): self
    {
        $body = $this->response->getContent();
        $data = json_decode($body, true);
        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException("Response is not valid JSON: {$body}");
        }
        foreach ($expected as $key => $value) {
            if (!array_key_exists($key, $data) || $data[$key] !== $value) {
                throw new \RuntimeException(
                    "JSON key '{$key}' mismatch. Expected: " . json_encode($value) . ", got: " . json_encode($data[$key] ?? null)
                );
            }
        }
        return $this;
    }

    public function assertBodyContains(string $text): self
    {
        if (!str_contains($this->response->getContent(), $text)) {
            throw new \RuntimeException("Response body does not contain '{$text}'");
        }
        return $this;
    }

    public function getResponse(): Response
    {
        return $this->response;
    }

    /** Dispatches the request through the router and returns the response */
    private function captureResponse(Request $req): Response
    {
        try {
            return $this->app->router->dispatch($req);
        } catch (\Throwable $e) {
            if ($this->app->config->debug) throw $e;
            return new Response('', \Atom\Http\StatusCode::SERVER_ERROR);
        }
    }
}
