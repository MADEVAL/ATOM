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
    public function default_allow_origin_is_wildcard(): void
    {
        $cors = new Cors();
        $req = new Request(server: ['REQUEST_METHOD' => 'OPTIONS', 'REQUEST_URI' => '/']);
        $response = $cors->handle($req, fn() => new Response(''));
        $headers = $this->getHeaders($response);

        $this->assertSame('*', $headers['Access-Control-Allow-Origin']);
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

    #[Test]
    public function allow_credentials_adds_header(): void
    {
        $cors = new Cors(allowOrigin: 'https://app.com', allowCredentials: true);
        $req = new Request(server: ['REQUEST_METHOD' => 'OPTIONS', 'REQUEST_URI' => '/']);
        $response = $cors->handle($req, fn() => new Response(''));
        $headers = $this->getHeaders($response);
        $this->assertSame('true', $headers['Access-Control-Allow-Credentials']);
    }

    #[Test]
    public function allow_credentials_on_normal_request(): void
    {
        $cors = new Cors(allowOrigin: 'https://app.com', allowCredentials: true);
        $req = new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/']);
        $response = $cors->handle($req, fn() => new Response('ok'));
        $headers = $this->getHeaders($response);
        $this->assertSame('true', $headers['Access-Control-Allow-Credentials']);
    }

    #[Test]
    public function cors_wildcard_with_credentials_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Cors(allowOrigin: '*', allowCredentials: true);
    }

    #[Test]
    public function expose_headers_adds_header(): void
    {
        $cors = new Cors(allowOrigin: '*', exposeHeaders: 'X-Total-Count,X-Page');
        $req = new Request(server: ['REQUEST_METHOD' => 'OPTIONS', 'REQUEST_URI' => '/']);
        $response = $cors->handle($req, fn() => new Response(''));
        $headers = $this->getHeaders($response);
        $this->assertSame('X-Total-Count,X-Page', $headers['Access-Control-Expose-Headers']);
    }

    #[Test]
    public function origin_reflects_request_origin_when_wildcard(): void
    {
        $cors = new Cors();
        $req = new Request(
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/'],
            headers: ['origin' => 'https://myapp.com'],
        );
        $response = $cors->handle($req, fn() => new Response('ok'));
        $headers = $this->getHeaders($response);
        $this->assertSame('https://myapp.com', $headers['Access-Control-Allow-Origin']);
        $this->assertSame('Origin', $headers['Vary']);
    }

    #[Test]
    public function wildcard_preflight_with_request_origin_adds_vary(): void
    {
        $cors = new Cors();
        $req = new Request(
            server: ['REQUEST_METHOD' => 'OPTIONS', 'REQUEST_URI' => '/'],
            headers: ['origin' => 'https://myapp.com'],
        );
        $response = $cors->handle($req, fn() => new Response(''));
        $headers = $this->getHeaders($response);
        $this->assertSame('Origin', $headers['Vary']);
    }

    #[Test]
    public function specific_origin_not_overridden_by_request(): void
    {
        $cors = new Cors(allowOrigin: 'https://allowed.com');
        $req = new Request(
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/'],
            headers: ['origin' => 'https://evil.com'],
        );
        $response = $cors->handle($req, fn() => new Response('ok'));
        $headers = $this->getHeaders($response);
        $this->assertSame('https://allowed.com', $headers['Access-Control-Allow-Origin']);
    }

    #[Test]
    public function constructor_with_all_params(): void
    {
        $cors = new Cors(
            allowOrigin: 'https://app.io',
            allowMethods: 'GET,POST',
            allowHeaders: 'X-API-Key',
            allowCredentials: true,
            exposeHeaders: 'X-Total',
        );
        $req = new Request(
            server: ['REQUEST_METHOD' => 'OPTIONS', 'REQUEST_URI' => '/'],
            headers: ['origin' => 'https://app.io'],
        );
        $response = $cors->handle($req, fn() => new Response(''));
        $headers = $this->getHeaders($response);
        $this->assertSame('https://app.io', $headers['Access-Control-Allow-Origin']);
        $this->assertSame('GET,POST', $headers['Access-Control-Allow-Methods']);
        $this->assertSame('X-API-Key', $headers['Access-Control-Allow-Headers']);
        $this->assertSame('true', $headers['Access-Control-Allow-Credentials']);
        $this->assertSame('X-Total', $headers['Access-Control-Expose-Headers']);
    }
}
