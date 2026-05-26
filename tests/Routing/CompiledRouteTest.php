<?php
declare(strict_types=1);
namespace Atom\Tests\Routing;

use Atom\Routing\CompiledRoute;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(CompiledRoute::class)]
final class CompiledRouteTest extends TestCase
{
    #[Test]
    public function stores_all_properties(): void
    {
        $route = new CompiledRoute(
            path: '/test/{id}',
            methods: ['GET', 'POST'],
            name: 'test.route',
            middleware: ['auth'],
            controller: 'TestController',
            action: 'index',
        );

        $this->assertSame('/test/{id}', $route->path);
        $this->assertSame(['GET', 'POST'], $route->methods);
        $this->assertSame('test.route', $route->name);
        $this->assertSame(['auth'], $route->middleware);
        $this->assertSame('TestController', $route->controller);
        $this->assertSame('index', $route->action);
    }

    #[Test]
    public function with_defaults(): void
    {
        $route = new CompiledRoute(
            path: '/',
            methods: ['GET'],
            name: '',
            middleware: [],
            controller: 'Home',
            action: 'index',
        );

        $this->assertSame('/', $route->path);
        $this->assertSame(['GET'], $route->methods);
        $this->assertEmpty($route->name);
        $this->assertEmpty($route->middleware);
    }
}
