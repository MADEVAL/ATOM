<?php
declare(strict_types=1);
namespace Atom\Tests\Middleware;

use Atom\Http\{Request, Response};
use Atom\Middleware\Cors;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use ReflectionProperty;

#[CoversClass(Cors::class)]
final class CorsTest extends TestCase
{
    private function getHeaders(Response $response): array
    {
        $prop = new ReflectionProperty(Response::class, 'headers');
        return $prop->getValue($response);
    }

    #[Test]
    public function options_request_returns_preflight(): void
    {
        $cors = new Cors();
        $req = new Request(server: ['REQUEST_METHOD' => 'OPTIONS', 'REQUEST_URI' => '/api']);
        $core = fn(Request $r): Response => new Response('should not reach');

        $response = $cors->handle($req, $core);
        $this->assertSame(204, $response->getStatusCode());
    }

    #[Test]
    public function preflight_includes_cors_headers(): void
    {
        $cors = new Cors(allowOrigin: 'https://example.com');
        $req = new Request(server: ['REQUEST_METHOD' => 'OPTIONS', 'REQUEST_URI' => '/api']);
        $response = $cors->handle($req, fn() => new Response(''));
        $headers = $this->getHeaders($response);

        $this->assertSame('https://example.com', $headers['Access-Control-Allow-Origin']);
        $this->assertStringContainsString('GET', $headers['Access-Control-Allow-Methods']);
        $this->assertStringContainsString('Content-Type', $headers['Access-Control-Allow-Headers']);
        $this->assertSame('86400', $headers['Access-Control-Max-Age']);
    }

    #[Test]
    public function normal_request_adds_allow_origin(): void
    {
        $cors = new Cors(allowOrigin: 'https://example.com');
        $req = new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/api']);
        $response = $cors->handle($req, fn() => new Response('ok'));
        $headers = $this->getHeaders($response);

        $this->assertStringContainsString('ok', $response->getContent());
        $this->assertSame('https://example.com', $headers['Access-Control-Allow-Origin']);
    }

    #[Test]
    public function custom_allow_methods(): void
    {
        $cors = new Cors(allowOrigin: '*', allowMethods: 'GET,POST');
        $req = new Request(server: ['REQUEST_METHOD' => 'OPTIONS', 'REQUEST_URI' => '/']);
        $response = $cors->handle($req, fn() => new Response(''));
        $headers = $this->getHeaders($response);

        $this->assertSame('GET,POST', $headers['Access-Control-Allow-Methods']);
    }

    #[Test]
    public function custom_allow_headers(): void
    {
        $cors = new Cors(allowOrigin: '*', allowHeaders: 'X-Custom');
        $req = new Request(server: ['REQUEST_METHOD' => 'OPTIONS', 'REQUEST_URI' => '/']);
        $response = $cors->handle($req, fn() => new Response(''));
        $headers = $this->getHeaders($response);

        $this->assertSame('X-Custom', $headers['Access-Control-Allow-Headers']);
    }

    #[Test]
    public function default_allow_origin_is_empty(): void
    {
        $cors = new Cors();
        $req = new Request(server: ['REQUEST_METHOD' => 'OPTIONS', 'REQUEST_URI' => '/']);
        $response = $cors->handle($req, fn() => new Response(''));
        $headers = $this->getHeaders($response);

        $this->assertSame('', $headers['Access-Control-Allow-Origin']);
    }

    #[Test]
    public function csrf_state_changing_methods_handled(): void
    {
        $cors = new Cors(allowOrigin: 'https://example.com');
        foreach (['PUT', 'PATCH', 'DELETE'] as $method) {
            $req = new Request(server: ['REQUEST_METHOD' => $method, 'REQUEST_URI' => '/api']);
            $response = $cors->handle($req, fn() => new Response('ok'));
            $headers = $this->getHeaders($response);

            $this->assertSame('ok', $response->getContent());
            $this->assertSame('https://example.com', $headers['Access-Control-Allow-Origin']);
        }
    }
}
