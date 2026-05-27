<?php
declare(strict_types=1);
namespace Atom\Tests\Routing;

use Atom\Routing\CompiledRoute;
use Atom\Routing\RouteCompiler;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(RouteCompiler::class)]
final class RouteCompilerTest extends TestCase
{
    #[Test]
    public function compile_creates_regex_and_map(): void
    {
        $compiler = new RouteCompiler();
        $result = $compiler->compile([]);
        $this->assertArrayHasKey('regex', $result);
        $this->assertArrayHasKey('map', $result);
    }

    #[Test]
    public function compile_empty_routes_produces_valid_regex(): void
    {
        $compiler = new RouteCompiler();
        $result = $compiler->compile([]);
        $this->assertIsString($result['regex']);
        $this->assertIsArray($result['map']);
        $this->assertEmpty($result['map']);
    }

    #[Test]
    public function compile_simple_route(): void
    {
        $compiler = new RouteCompiler();
        $route = $this->makeRoute('/hello', 'GET', 'Test', 'hello');
        $result = $compiler->compile([$route]);

        $this->assertArrayHasKey(0, $result['map']);
        $this->assertSame('Test', $result['map'][0]['controller']);
        $this->assertSame('hello', $result['map'][0]['action']);
    }

    #[Test]
    public function compile_route_with_id_parameter(): void
    {
        $compiler = new RouteCompiler();
        $route = $this->makeRoute('/users/{id}', 'GET', 'User', 'show');
        $result = $compiler->compile([$route]);

        $regex = $result['regex'];
        $this->assertStringContainsString('(?<id>', $regex);
        $this->assertStringContainsString('[0-9]+', $regex);
    }

    #[Test]
    public function compile_route_with_slug_parameter(): void
    {
        $compiler = new RouteCompiler();
        $route = $this->makeRoute('/posts/{slug}', 'GET', 'Post', 'show');
        $result = $compiler->compile([$route]);

        $regex = $result['regex'];
        $this->assertStringContainsString('(?<slug>', $regex);
        $this->assertStringContainsString('[a-z0-9\\-]+', $regex);
    }

    #[Test]
    public function compile_route_with_any_parameter(): void
    {
        $compiler = new RouteCompiler();
        $route = $this->makeRoute('/anything/{any}', 'GET', 'Page', 'show');
        $result = $compiler->compile([$route]);
        $regex = $result['regex'];
        $this->assertStringContainsString('[^', $regex);
    }

    #[Test]
    public function compile_route_with_custom_pattern(): void
    {
        $compiler = new RouteCompiler();
        $route = $this->makeRoute('/items/{code:[a-z]+}', 'GET', 'Item', 'show');
        $result = $compiler->compile([$route]);

        $regex = $result['regex'];
        $this->assertStringContainsString('(?<code>[a-z]+)', $regex);
    }

    #[Test]
    public function compile_route_with_all_parameter(): void
    {
        $compiler = new RouteCompiler();
        $route = $this->makeRoute('/files/{all}', 'GET', 'File', 'serve');
        $result = $compiler->compile([$route]);
        $regex = $result['regex'];
        $this->assertStringContainsString('.+', $regex);
    }

    #[Test]
    public function compile_route_with_extra_patterns(): void
    {
        $compiler = new RouteCompiler();
        $route = $this->makeRoute('/files/{hash}', 'GET', 'File', 'download');
        $result = $compiler->compile([$route], ['hash' => '[a-f0-9]{32}']);

        $regex = $result['regex'];
        $this->assertStringContainsString('(?<hash>[a-f0-9]{32})', $regex);
    }

    #[Test]
    public function compile_multiple_methods(): void
    {
        $compiler = new RouteCompiler();
        $route = $this->makeRoute('/resource', 'POST', 'Resource', 'store');
        $result = $compiler->compile([$route]);

        $this->assertCount(1, $result['map']);
        $this->assertArrayHasKey(0, $result['map']);
    }

    #[Test]
    public function compile_multiple_routes(): void
    {
        $compiler = new RouteCompiler();
        $routes = [
            $this->makeRoute('/users', 'GET', 'User', 'index', 'users.index'),
            $this->makeRoute('/users/{id}', 'GET', 'User', 'show', 'users.show'),
            $this->makeRoute('/users/{id}/edit', 'GET', 'User', 'edit', 'users.edit'),
        ];
        $result = $compiler->compile($routes);

        $this->assertCount(3, $result['map']);
    }

    #[Test]
    public function compiled_regex_uses_branch_reset(): void
    {
        $compiler = new RouteCompiler();
        $route = $this->makeRoute('/test', 'GET', 'Test', 'index');
        $result = $compiler->compile([$route]);

        $this->assertStringStartsWith('#^(?|', $result['regex']);
        $this->assertStringEndsWith(')$#xs', $result['regex']);
    }

    #[Test]
    public function compiled_regex_matches_correct_uri(): void
    {
        $compiler = new RouteCompiler();
        $route = $this->makeRoute('/api/users/{id}', 'GET', 'User', 'show');
        $result = $compiler->compile([$route]);

        $this->assertSame(1, preg_match($result['regex'], 'GET/api/users/42', $m));
        $this->assertSame('42', $m['id'] ?? null);
    }

    #[Test]
    public function compiled_regex_does_not_match_wrong_method(): void
    {
        $compiler = new RouteCompiler();
        $route = $this->makeRoute('/api/users/{id}', 'GET', 'User', 'show');
        $result = $compiler->compile([$route]);

        $this->assertSame(0, preg_match($result['regex'], 'POST/api/users/42'));
    }

    #[Test]
    public function compile_preserves_name_in_map(): void
    {
        $compiler = new RouteCompiler();
        $route = $this->makeRoute('/home', 'GET', 'Home', 'index', 'home.page');
        $result = $compiler->compile([$route]);

        $this->assertSame('home.page', $result['map'][0]['name']);
    }

    #[Test]
    public function compile_preserves_middleware_in_map(): void
    {
        $compiler = new RouteCompiler();
        $route = new CompiledRoute(
            path: '/admin',
            methods: ['GET'],
            name: '',
            middleware: ['auth', 'admin'],
            controller: 'Admin',
            action: 'dashboard',
        );
        $result = $compiler->compile([$route]);

        $this->assertSame(['auth', 'admin'], $result['map'][0]['middleware']);
    }

    #[Test]
    public function compile_handles_multiple_params(): void
    {
        $compiler = new RouteCompiler();
        $route = $this->makeRoute('/users/{id}/posts/{slug}', 'GET', 'Post', 'show');
        $result = $compiler->compile([$route]);

        $this->assertSame(1, preg_match($result['regex'], 'GET/users/42/posts/hello-world', $m));
        $this->assertSame('42', $m['id']);
        $this->assertSame('hello-world', $m['slug']);
    }

    private function makeRoute(string $path, string $method, string $controller, string $action, string $name = ''): CompiledRoute
    {
        return new CompiledRoute(
            path: $path,
            methods: [strtoupper($method)],
            name: $name,
            middleware: [],
            controller: $controller,
            action: $action,
        );
    }

    #[Test]
    public function default_patterns_constants_exist(): void
    {
        $this->assertSame('[0-9]+', RouteCompiler::DEFAULT_PATTERNS['id']);
        $this->assertSame('[a-z0-9\-]+', RouteCompiler::DEFAULT_PATTERNS['slug']);
        $this->assertSame('[^/]+', RouteCompiler::DEFAULT_PATTERNS['any']);
        $this->assertSame('.+', RouteCompiler::DEFAULT_PATTERNS['all']);
    }

    #[Test]
    public function compile_with_invalid_custom_pattern_throws(): void
    {
        $compiler = new RouteCompiler();
        $route = new CompiledRoute(
            path: '/bad/{p:[}', methods: ['GET'], name: '',
            middleware: [], controller: 'C', action: 'a',
        );
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Bad compiled route regex');
        $compiler->compile([$route]);
    }
}
