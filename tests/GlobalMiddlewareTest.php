<?php
declare(strict_types=1);
namespace Atom\Tests;

use Atom\Application;
use Atom\Config;
use Atom\Http\{Request, Response, StatusCode};
use Atom\Middleware\{Pipeline, RateLimit};
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(Application::class)]
#[CoversClass(Pipeline::class)]
final class GlobalMiddlewareTest extends TestCase
{
    private Application $app;
    private string $tmpCache;

    protected function setUp(): void
    {
        $this->tmpCache = sys_get_temp_dir() . '/atom_gmw_' . uniqid();
        $this->app = new Application(new Config(cacheDir: $this->tmpCache, debug: true));
    }

    protected function tearDown(): void
    {
        foreach ((array) glob($this->tmpCache . '/*') as $f) @unlink($f);
        if (is_dir($this->tmpCache)) rmdir($this->tmpCache);
    }

    #[Test]
    public function global_closure_middleware_runs_on_every_request(): void
    {
        $this->app->use(function (Request $req, \Closure $next): Response {
            $res = $next($req);
            return $res->withHeader('X-Global', '1');
        });
        $this->app->router->get('/test', 'Ctrl@test');
        $this->app->container->bind('Ctrl', fn() => new class {
            public function test(): string { return 'ok'; }
        });

        $req = new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/test']);
        ob_start();
        $this->app->run($req);
        $output = ob_get_clean();
        $this->assertStringContainsString('ok', $output);
    }

    #[Test]
    public function global_middleware_can_short_circuit(): void
    {
        $this->app->use(function (): Response {
            return new Response('blocked', StatusCode::FORBIDDEN);
        });
        $this->app->router->get('/test', 'Ctrl@test');
        $this->app->container->bind('Ctrl', fn() => new class {
            public function test(): string { return 'ok'; }
        });

        $req = new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/test']);
        ob_start();
        $this->app->run($req);
        $output = ob_get_clean();
        $this->assertStringContainsString('blocked', $output);
    }

    #[Test]
    public function global_middleware_wraps_route_middleware(): void
    {
        $order = [];
        $this->app->use(function (Request $req, \Closure $next) use (&$order): Response {
            $order[] = 'global_before';
            $res = $next($req);
            $order[] = 'global_after';
            return $res;
        });
        $this->app->router->get('/test', 'Ctrl@test', '', [
            function (Request $req, \Closure $next) use (&$order): Response {
                $order[] = 'route_before';
                $res = $next($req);
                $order[] = 'route_after';
                return $res;
            }
        ]);
        $this->app->container->bind('Ctrl', fn() => new class {
            public function test(): string { return 'ok'; }
        });

        $req = new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/test']);
        ob_start();
        $this->app->run($req);
        ob_end_clean();
        $this->assertSame(
            ['global_before', 'route_before', 'route_after', 'global_after'],
            $order
        );
    }

    #[Test]
    public function global_middleware_with_rate_limit(): void
    {
        $this->app->use(new RateLimit(max: 5, window: 60));
        $this->app->router->get('/api', 'Ctrl@api');
        $this->app->container->bind('Ctrl', fn() => new class {
            public function api(): Response { return Response::json(['ok' => true]); }
        });

        $req = new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/api']);
        ob_start();
        $this->app->run($req);
        $output = ob_get_clean();
        $this->assertStringContainsString('ok', $output);
    }

    #[Test]
    public function global_middleware_with_health_check(): void
    {
        $this->app->use(function (Request $req, \Closure $next): Response {
            $res = $next($req);
            return $res->withHeader('X-App', 'atom');
        });
        $this->app->router->health('/health', fn() => ['db' => true]);

        $req = new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/health']);
        ob_start();
        $this->app->run($req);
        $output = ob_get_clean();
        $this->assertStringContainsString('db', $output);
    }

    #[Test]
    public function string_global_middleware_resolved_from_container(): void
    {
        $this->app->container->bind('GlobalAuth', fn() => new class implements \Atom\Middleware\MiddlewareInterface {
            public function handle(Request $req, \Closure $next): Response {
                if (!$req->header('X-Token')) {
                    return new Response('unauthorized', StatusCode::UNAUTHORIZED);
                }
                return $next($req);
            }
        });
        $this->app->use('GlobalAuth');
        $this->app->router->get('/secure', 'Ctrl@secure');
        $this->app->container->bind('Ctrl', fn() => new class {
            public function secure(): string { return 'secret'; }
        });

        $req = new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/secure']);
        ob_start();
        $this->app->run($req);
        $output = ob_get_clean();
        $this->assertStringContainsString('unauthorized', $output);
    }

    #[Test]
    public function multiple_global_middleware_run_in_order(): void
    {
        $order = [];
        $this->app->use(function (Request $req, \Closure $next) use (&$order): Response {
            $order[] = 1;
            return $next($req);
        });
        $this->app->use(function (Request $req, \Closure $next) use (&$order): Response {
            $order[] = 2;
            return $next($req);
        });
        $this->app->router->get('/test', 'Ctrl@test');
        $this->app->container->bind('Ctrl', fn() => new class {
            public function test(): string { return 'ok'; }
        });

        $req = new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/test']);
        ob_start();
        $this->app->run($req);
        ob_end_clean();
        $this->assertSame([1, 2], $order);
    }

    #[Test]
    public function global_middleware_with_validation_exception_returns_422(): void
    {
        $this->app->use(function (Request $req, \Closure $next): Response {
            return $next($req)->withHeader('X-Wrapped', '1');
        });
        $this->app->router->post('/users', 'Ctrl@create');
        $this->app->container->bind('Ctrl', fn() => new class {
            public function create(): never { throw new \Atom\Validation\ValidationException(['name' => ['Required']]); }
        });

        $req = new Request(server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/users']);
        ob_start();
        $this->app->run($req);
        $output = ob_get_clean();
        $this->assertStringContainsString('Required', $output);
    }
}
