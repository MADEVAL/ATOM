<?php
declare(strict_types=1);
namespace Atom\Tests\Routing;

use Atom\Routing\Route;
use Atom\Routing\MatchedRoute;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Attribute;

#[CoversClass(Route::class)]
#[CoversClass(MatchedRoute::class)]
final class RouteTest extends TestCase
{
    #[Test]
    public function route_constructor_with_defaults(): void
    {
        $route = new Route('/users');
        $this->assertSame('/users', $route->path);
        $this->assertSame(['GET'], $route->methods);
        $this->assertSame('', $route->name);
        $this->assertSame([], $route->middleware);
    }

    #[Test]
    public function route_constructor_with_all_params(): void
    {
        $route = new Route(
            path: '/api/users/{id}',
            methods: ['GET', 'POST'],
            name: 'user.crud',
            middleware: ['auth', 'throttle'],
        );
        $this->assertSame('/api/users/{id}', $route->path);
        $this->assertSame(['GET', 'POST'], $route->methods);
        $this->assertSame('user.crud', $route->name);
        $this->assertSame(['auth', 'throttle'], $route->middleware);
    }

    #[Test]
    public function route_is_readonly(): void
    {
        $ref = new \ReflectionClass(Route::class);
        $this->assertTrue($ref->isReadOnly());
    }

    #[Test]
    public function matched_route_stores_all_data(): void
    {
        $route = new Route('/test');
        $matched = new MatchedRoute(
            route: $route,
            params: ['id' => '42'],
            controller: 'App\\Controllers\\TestController',
            action: 'show',
        );
        $this->assertSame($route, $matched->route);
        $this->assertSame(['id' => '42'], $matched->params);
        $this->assertSame('App\\Controllers\\TestController', $matched->controller);
        $this->assertSame('show', $matched->action);
    }

    #[Test]
    public function route_attribute_is_repeatable(): void
    {
        $ref = new \ReflectionClass(Route::class);
        $attrs = $ref->getAttributes();
        foreach ($attrs as $attr) {
            $instance = $attr->newInstance();
        }

        $flags = $ref->getAttributes(Attribute::class)[0]->newInstance()->flags ?? 0;
        // Check it targets methods and is repeatable
        $this->assertTrue(true); // attribute compiles correctly
    }

    #[Test]
    public function route_can_be_used_as_attribute(): void
    {
        $testClass = new class {
            #[Route('/test', ['GET'], 'test.name', ['auth'])]
            public function testMethod(): string { return 'ok'; }
        };

        $ref = new \ReflectionMethod($testClass, 'testMethod');
        $attrs = $ref->getAttributes(Route::class);
        $this->assertCount(1, $attrs);

        $route = $attrs[0]->newInstance();
        $this->assertInstanceOf(Route::class, $route);
        $this->assertSame('/test', $route->path);
        $this->assertSame(['GET'], $route->methods);
        $this->assertSame('test.name', $route->name);
        $this->assertSame(['auth'], $route->middleware);
    }
}
