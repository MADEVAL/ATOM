<?php
declare(strict_types=1);
namespace Atom\Tests\Middleware;

use Atom\Container\Container;
use Atom\Http\Request;
use Atom\Http\Response;
use Atom\Middleware\MiddlewareInterface;
use Atom\Middleware\Pipeline;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(Pipeline::class)]
final class PipelineTest extends TestCase
{
    #[Test]
    public function run_without_layers_returns_core_result(): void
    {
        $container = new Container();
        $core = fn(Request $req): Response => new Response('core-response');

        $req = new Request();
        $response = Pipeline::run([], $req, $core, $container);

        $this->assertSame('core-response', $response->getContent());
    }

    #[Test]
    public function run_with_middleware_wraps_core(): void
    {
        $container = new Container();
        $core = fn(Request $req): Response => new Response('core');

        $mw = new class implements MiddlewareInterface {
            public function handle(Request $request, \Closure $next): Response
            {
                $response = $next($request);
                return new Response('[' . $response->getContent() . ']');
            }
        };

        $req = new Request();
        $response = Pipeline::run([$mw], $req, $core, $container);

        $this->assertSame('[core]', $response->getContent());
    }

    #[Test]
    public function run_executes_middleware_in_order(): void
    {
        $container = new Container();
        $order = [];

        $core = function (Request $req) use (&$order): Response {
            $order[] = 'core';
            return new Response('done');
        };

        $mw1 = new class($order) implements MiddlewareInterface {
            public function __construct(private array &$log) {}
            public function handle(Request $request, \Closure $next): Response
            {
                $this->log[] = 'mw1-before';
                $response = $next($request);
                $this->log[] = 'mw1-after';
                return $response;
            }
        };

        $mw2 = new class($order) implements MiddlewareInterface {
            public function __construct(private array &$log) {}
            public function handle(Request $request, \Closure $next): Response
            {
                $this->log[] = 'mw2-before';
                $response = $next($request);
                $this->log[] = 'mw2-after';
                return $response;
            }
        };

        $req = new Request();
        Pipeline::run([$mw1, $mw2], $req, $core, $container);

        $this->assertSame([
            'mw1-before',
            'mw2-before',
            'core',
            'mw2-after',
            'mw1-after',
        ], $order);
    }

    #[Test]
    public function run_resolves_string_middleware_from_container(): void
    {
        $container = new Container();
        $container->bind('test.middleware', fn() => new class implements MiddlewareInterface {
            public function handle(Request $request, \Closure $next): Response
            {
                $resp = $next($request);
                return new Response('mw:' . $resp->getContent());
            }
        });

        $core = fn(Request $req): Response => new Response('core');

        $req = new Request();
        $response = Pipeline::run(['test.middleware'], $req, $core, $container);

        $this->assertSame('mw:core', $response->getContent());
    }

    #[Test]
    public function run_passes_request_through_middleware(): void
    {
        $container = new Container();
        $receivedUri = '';

        $core = fn(Request $req): Response => new Response($req->uri);
        $mw = new class($receivedUri) implements MiddlewareInterface {
            public function __construct(private string &$captured) {}
            public function handle(Request $request, \Closure $next): Response
            {
                $this->captured = $request->uri;
                return $next($request);
            }
        };

        $req = new Request(server: ['REQUEST_URI' => '/api/data']);
        Pipeline::run([$mw], $req, $core, $container);
        $this->assertSame('/api/data', $receivedUri);
    }

    #[Test]
    public function run_middleware_can_modify_response(): void
    {
        $container = new Container();
        $core = fn(Request $req): Response => new Response('body', 200);
        $mw = new class implements MiddlewareInterface {
            public function handle(Request $request, \Closure $next): Response
            {
                $resp = $next($request);
                return $resp->withStatus(\Atom\Http\StatusCode::CREATED);
            }
        };

        $req = new Request();
        $response = Pipeline::run([$mw], $req, $core, $container);
        $this->assertSame(201, $response->getStatusCode());
    }

    #[Test]
    public function run_middleware_can_short_circuit(): void
    {
        $container = new Container();
        $coreCalled = false;
        $core = function (Request $req) use (&$coreCalled): Response {
            $coreCalled = true;
            return new Response('core');
        };

        $mw = new class implements MiddlewareInterface {
            public function handle(Request $request, \Closure $next): Response
            {
                return new Response('blocked', 403);
            }
        };

        $req = new Request();
        $response = Pipeline::run([$mw], $req, $core, $container);
        $this->assertSame('blocked', $response->getContent());
        $this->assertSame(403, $response->getStatusCode());
        $this->assertFalse($coreCalled);
    }

    #[Test]
    public function run_with_multiple_mw_and_string_resolution(): void
    {
        $container = new Container();
        $container->bind('mw1', fn() => new class implements MiddlewareInterface {
            public function handle(Request $request, \Closure $next): Response {
                $resp = $next($request);
                return new Response('A' . $resp->getContent() . 'A');
            }
        });

        $mw2 = new class implements MiddlewareInterface {
            public function handle(Request $request, \Closure $next): Response {
                $resp = $next($request);
                return new Response('B' . $resp->getContent() . 'B');
            }
        };

        $core = fn(Request $req): Response => new Response('C');

        $req = new Request();
        $response = Pipeline::run(['mw1', $mw2], $req, $core, $container);
        $this->assertSame('ABCBA', $response->getContent());
    }

    #[Test]
    public function run_with_closure_middleware(): void
    {
        $container = new Container();
        $core = fn(Request $req): Response => new Response('core');

        $mw = fn(Request $req, \Closure $next): Response => new Response('[' . $next($req)->getContent() . ']');

        $req = new Request();
        $response = Pipeline::run([$mw], $req, $core, $container);
        $this->assertSame('[core]', $response->getContent());
    }
}
