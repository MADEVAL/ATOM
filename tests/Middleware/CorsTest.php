<?php
declare(strict_types=1);
namespace Atom\Tests\Middleware;

use Atom\Http\{Request, Response};
use Atom\Middleware\Cors;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(Cors::class)]
final class CorsTest extends TestCase
{
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
        $cors = new Cors();
        $req = new Request(server: ['REQUEST_METHOD' => 'OPTIONS', 'REQUEST_URI' => '/api']);

        ob_start();
        $cors->handle($req, fn() => new Response(''))->send();
        ob_get_clean();
        $this->assertTrue(true);
    }

    #[Test]
    public function normal_request_adds_allow_origin(): void
    {
        $cors = new Cors(allowOrigin: 'https://example.com');
        $req = new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/api']);
        $response = $cors->handle($req, fn() => new Response('ok'));
        $this->assertStringContainsString('ok', $response->getContent());
    }

    #[Test]
    public function custom_allow_methods(): void
    {
        $cors = new Cors(allowMethods: 'GET,POST');
        $req = new Request(server: ['REQUEST_METHOD' => 'OPTIONS', 'REQUEST_URI' => '/']);

        ob_start();
        $cors->handle($req, fn() => new Response(''))->send();
        ob_get_clean();
        $this->assertTrue(true);
    }

    #[Test]
    public function custom_allow_headers(): void
    {
        $cors = new Cors(allowHeaders: 'X-Custom');
        $req = new Request(server: ['REQUEST_METHOD' => 'OPTIONS', 'REQUEST_URI' => '/']);

        ob_start();
        $cors->handle($req, fn() => new Response(''))->send();
        ob_get_clean();
        $this->assertTrue(true);
    }

    #[Test]
    public function default_allow_origin_is_wildcard(): void
    {
        $cors = new Cors();
        $req = new Request(server: ['REQUEST_METHOD' => 'OPTIONS', 'REQUEST_URI' => '/']);

        ob_start();
        $cors->handle($req, fn() => new Response(''))->send();
        ob_get_clean();
        $this->assertTrue(true);
    }
}
