<?php
declare(strict_types=1);
namespace Atom\Tests\Container;

use Atom\Container\Container;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(Container::class)]
final class ContainerTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        $this->container = new Container();
    }

    #[Test]
    public function bind_resolves_with_callback(): void
    {
        $this->container->bind('test.service', fn() => new class {
            public function value(): string { return 'bound'; }
        });

        $instance = $this->container->make('test.service');
        $this->assertSame('bound', $instance->value());
    }

    #[Test]
    public function bind_creates_new_instance_each_time(): void
    {
        $this->container->bind('counter', fn() => new class { public int $n = 0; });

        $a = $this->container->make('counter');
        $a->n = 5;
        $b = $this->container->make('counter');
        $this->assertSame(0, $b->n);
        $this->assertNotSame($a, $b);
    }

    #[Test]
    public function singleton_returns_same_instance(): void
    {
        $this->container->singleton('shared', fn() => new class { public string $id = ''; });

        $a = $this->container->make('shared');
        $a->id = 'first';
        $b = $this->container->make('shared');
        $this->assertSame('first', $b->id);
        $this->assertSame($a, $b);
    }

    #[Test]
    public function instance_returns_exact_value(): void
    {
        $obj = new \stdClass();
        $obj->name = 'custom';
        $this->container->instance('my.object', $obj);

        $this->assertSame($obj, $this->container->make('my.object'));
    }

    #[Test]
    public function instance_overrides_singleton(): void
    {
        $this->container->singleton('key', fn() => new \stdClass());
        $direct = new \stdClass();
        $direct->marker = 'override';
        $this->container->instance('key', $direct);

        $this->assertSame($direct, $this->container->make('key'));
    }

    #[Test]
    public function make_autowires_class(): void
    {
        $obj = $this->container->make(SimpleService::class);
        $this->assertInstanceOf(SimpleService::class, $obj);
    }

    #[Test]
    public function make_autowires_class_with_dependencies(): void
    {
        $obj = $this->container->make(ServiceWithDependency::class);
        $this->assertInstanceOf(ServiceWithDependency::class, $obj);
        $this->assertInstanceOf(SimpleService::class, $obj->dep);
    }

    #[Test]
    public function make_autowires_deep_dependencies(): void
    {
        $obj = $this->container->make(DeepService::class);
        $this->assertInstanceOf(DeepService::class, $obj);
        $this->assertInstanceOf(ServiceWithDependency::class, $obj->inner);
        $this->assertInstanceOf(SimpleService::class, $obj->inner->dep);
    }

    #[Test]
    public function make_resolves_classname_from_bindings(): void
    {
        $this->container->bind(SimpleInterface::class, SimpleImpl::class);
        $obj = $this->container->make(SimpleInterface::class);
        $this->assertInstanceOf(SimpleImpl::class, $obj);
    }

    #[Test]
    public function make_pass_params_to_constructor(): void
    {
        $obj = $this->container->make(RequestIdService::class, ['requestId' => 'abc-123']);
        $this->assertInstanceOf(RequestIdService::class, $obj);
        $this->assertSame('abc-123', $obj->requestId);
    }

    #[Test]
    public function make_uses_default_values(): void
    {
        $obj = $this->container->make(OptionalDepService::class);
        $this->assertInstanceOf(OptionalDepService::class, $obj);
        $this->assertSame('default', $obj->name);
    }

    #[Test]
    public function make_uses_custom_default_over_autowire(): void
    {
        $obj = $this->container->make(OptionalDepService::class, ['name' => 'custom']);
        $this->assertSame('custom', $obj->name);
    }

    #[Test]
    public function make_throws_for_unresolvable(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->container->make('NonExistentClass12345');
    }

    #[Test]
    public function make_throws_for_unknown_binding(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->container->make('unknown.key');
    }

    #[Test]
    public function make_bind_with_closure_receives_container_and_params(): void
    {
        $this->container->bind('test', function (Container $c, array $params) {
            $obj = new SimpleService();
            return $obj;
        });
        $result = $this->container->make('test');
        $this->assertInstanceOf(SimpleService::class, $result);
    }

    #[Test]
    public function autowire_throws_for_unresolvable_param(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot resolve param');
        $this->container->make(UnresolvableService::class);
    }

    #[Test]
    public function autowire_no_constructor_instantiates_directly(): void
    {
        $obj = $this->container->make(NoConstructorService::class);
        $this->assertInstanceOf(NoConstructorService::class, $obj);
    }

    #[Test]
    public function make_throws_for_non_class_string(): void
    {
        $this->container->bind('bad', 'this_is_not_a_class');
        $this->expectException(\RuntimeException::class);
        $this->container->make('bad');
    }

    #[Test]
    public function make_callback_receives_container(): void
    {
        $this->container->bind('wrapped', function (Container $c) {
            return new SimpleService();
        });
        $obj = $this->container->make('wrapped');
        $this->assertInstanceOf(SimpleService::class, $obj);
    }

    #[Test]
    public function multiple_singletons_are_independent(): void
    {
        $this->container->singleton('a', fn() => new SimpleService());
        $this->container->singleton('b', fn() => new ServiceWithDependency(new SimpleService()));

        $a = $this->container->make('a');
        $b = $this->container->make('b');
        $this->assertNotSame($a, $b->dep);
    }

    #[Test]
    public function bind_with_class_string_resolves(): void
    {
        $this->container->bind('my.simple', SimpleService::class);
        $obj = $this->container->make('my.simple');
        $this->assertInstanceOf(SimpleService::class, $obj);
    }

    #[Test]
    public function autowire_resolves_interface_dependency(): void
    {
        $this->container->bind(SimpleInterface::class, SimpleImpl::class);
        $obj = $this->container->make(ServiceWithInterfaceDep::class);
        $this->assertInstanceOf(ServiceWithInterfaceDep::class, $obj);
        $this->assertInstanceOf(SimpleImpl::class, $obj->dep);
    }

    #[Test]
    public function detect_circular_dependency(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Circular dependency detected');
        $this->container->make(CircularA::class);
    }
}

// Test fixtures
interface SimpleInterface {}
class SimpleImpl implements SimpleInterface {}

class SimpleService
{
    public function __construct() {}
}

class ServiceWithDependency
{
    public function __construct(public readonly SimpleService $dep) {}
}

class ServiceWithInterfaceDep
{
    public function __construct(public readonly SimpleInterface $dep) {}
}

class DeepService
{
    public function __construct(public readonly ServiceWithDependency $inner) {}
}

class RequestIdService
{
    public function __construct(public readonly string $requestId) {}
}

class OptionalDepService
{
    public function __construct(public readonly string $name = 'default') {}
}

class NoConstructorService
{
}

class UnresolvableService
{
    public function __construct(public readonly int $required) {}
}

class CircularA
{
    public function __construct(public readonly CircularB $b) {}
}

class CircularB
{
    public function __construct(public readonly CircularA $a) {}
}
