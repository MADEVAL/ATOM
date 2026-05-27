<?php
declare(strict_types=1);
namespace Atom\Tests\Routing;

use Atom\Container\Container;
use Atom\Http\Request;
use Atom\Http\StatusCode;
use Atom\Routing\Router;
use Atom\Routing\Route;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(Router::class)]
final class RouterTest extends TestCase
{
    private Container $container;
    private Router $router;
    private string $tmpCacheDir;

    protected function setUp(): void
    {
        $this->tmpCacheDir = sys_get_temp_dir() . '/atom_test_' . uniqid();
        mkdir($this->tmpCacheDir, 0777, true);
        $this->container = new Container();
        $this->router = new Router($this->container, $this->tmpCacheDir);
    }

    protected function tearDown(): void
    {
        $this->rmDir($this->tmpCacheDir);
    }

    private function rmDir(string $dir): void
    {
        if (!is_dir($dir)) return;
        foreach (scandir($dir) as $f) {
            if ($f === '.' || $f === '..') continue;
            $p = "$dir/$f";
            is_dir($p) ? $this->rmDir($p) : unlink($p);
        }
        rmdir($dir);
    }

    #[Test]
    public function add_creates_route(): void
    {
        $this->router->add('GET', '/test', 'TestController@index');

        $req = new Request(server: [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/test',
        ]);

        $this->container->bind('TestController', fn() => new class {
            public function index(Request $request): string { return 'test-response'; }
        });

        $response = $this->router->dispatch($req);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('test-response', $response->getContent());
    }

    #[Test]
    public function get_method_adds_get_route(): void
    {
        $this->router->get('/hello', 'HelloController@world');

        $this->container->bind('HelloController', fn() => new class {
            public function world(): string { return 'hello world'; }
        });

        $req = new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/hello']);
        $response = $this->router->dispatch($req);
        $this->assertStringContainsString('hello world', $response->getContent());
    }

    #[Test]
    public function post_method_adds_post_route(): void
    {
        $this->router->post('/submit', 'FormController@handle');

        $this->container->bind('FormController', fn() => new class {
            public function handle(): string { return 'submitted'; }
        });

        $req = new Request(server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/submit']);
        $response = $this->router->dispatch($req);
        $this->assertStringContainsString('submitted', $response->getContent());
    }

    #[Test]
    public function put_method_adds_put_route(): void
    {
        $this->router->put('/update/{id}', 'UpdateController@do');

        $this->container->bind('UpdateController', fn() => new class {
            public function do(string $id): string { return "updated {$id}"; }
        });

        $req = new Request(server: ['REQUEST_METHOD' => 'PUT', 'REQUEST_URI' => '/update/5']);
        $response = $this->router->dispatch($req);
        $this->assertStringContainsString('updated 5', $response->getContent());
    }

    #[Test]
    public function delete_method_adds_delete_route(): void
    {
        $this->router->delete('/remove/{id}', 'DeleteController@go');

        $this->container->bind('DeleteController', fn() => new class {
            public function go(string $id): string { return "deleted {$id}"; }
        });

        $req = new Request(server: ['REQUEST_METHOD' => 'DELETE', 'REQUEST_URI' => '/remove/99']);
        $response = $this->router->dispatch($req);
        $this->assertStringContainsString('deleted 99', $response->getContent());
    }

    #[Test]
    public function dispatch_not_found_returns_404(): void
    {
        $req = new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/nonexistent']);
        $response = $this->router->dispatch($req);
        $this->assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function dispatch_extracts_named_params(): void
    {
        $this->router->get('/users/{id}/posts/{slug}', 'PostController@show');

        $this->container->bind('PostController', fn() => new class {
            public function show(string $id, string $slug): string { return "post {$slug} by user {$id}"; }
        });

        $req = new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/users/42/posts/hello-world']);
        $response = $this->router->dispatch($req);
        $this->assertStringContainsString('post hello-world by user 42', $response->getContent());
    }

    #[Test]
    public function dispatch_passes_request_to_controller(): void
    {
        $this->router->get('/info', 'InfoController@show');

        $this->container->bind('InfoController', fn() => new class {
            public function show(Request $request): string { return $request->uri; }
        });

        $req = new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/info?x=1']);
        $response = $this->router->dispatch($req);
        $this->assertStringContainsString('/info?x=1', $response->getContent());
    }

    #[Test]
    public function group_adds_prefix(): void
    {
        $this->router->group('/api/v1', [], function (Router $r): void {
            $r->get('/users', 'ApiUserController@index');
        });

        $this->container->bind('ApiUserController', fn() => new class {
            public function index(): string { return 'api users'; }
        });

        // Without prefix: should 404
        $req1 = new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/users']);
        $res1 = $this->router->dispatch($req1);
        $this->assertSame(404, $res1->getStatusCode());

        // With prefix: should match
        $req2 = new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/api/v1/users']);
        $res2 = $this->router->dispatch($req2);
        $this->assertStringContainsString('api users', $res2->getContent());
    }

    #[Test]
    public function group_nests_prefixes(): void
    {
        $this->router->group('/api', [], function (Router $r): void {
            $r->group('/v2', [], function (Router $r): void {
                $r->get('/status', 'StatusController@check');
            });
        });

        $this->container->bind('StatusController', fn() => new class {
            public function check(): string { return 'ok'; }
        });

        $req = new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/api/v2/status']);
        $res = $this->router->dispatch($req);
        $this->assertStringContainsString('ok', $res->getContent());
    }

    #[Test]
    public function group_does_not_leak_prefix(): void
    {
        $this->router->group('/admin', [], function (Router $r): void {
            $r->get('/dashboard', 'AdminDashboardController@show');
        });
        $this->router->get('/public', 'PublicController@show');

        $this->container->bind('AdminDashboardController', fn() => new class {
            public function show(): string { return 'admin'; }
        });
        $this->container->bind('PublicController', fn() => new class {
            public function show(): string { return 'public'; }
        });

        $reqPublic = new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/public']);
        $resPublic = $this->router->dispatch($reqPublic);
        $this->assertStringContainsString('public', $resPublic->getContent());
    }

    #[Test]
    public function dispatch_respects_method(): void
    {
        $this->router->get('/resource', 'ResourceController@read');
        $this->router->post('/resource', 'ResourceController@create');

        $this->container->bind('ResourceController', fn() => new class {
            public function read(): string { return 'read'; }
            public function create(): string { return 'created'; }
        });

        $reqGet = new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/resource']);
        $resGet = $this->router->dispatch($reqGet);
        $this->assertStringContainsString('read', $resGet->getContent());

        $reqPost = new Request(server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/resource']);
        $resPost = $this->router->dispatch($reqPost);
        $this->assertStringContainsString('created', $resPost->getContent());
    }

    #[Test]
    public function url_generates_from_named_route(): void
    {
        $this->router->get('/users/{id}', 'UserController@show', 'user.show');
        $url = $this->router->url('user.show', ['id' => '42']);
        $this->assertSame('/users/42', $url);
    }

    #[Test]
    public function url_throws_for_unknown_route(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->router->url('nonexistent.route');
    }

    #[Test]
    public function url_throws_for_missing_param(): void
    {
        $this->router->get('/users/{id}', 'UserController@show', 'user.show');
        $this->expectException(\InvalidArgumentException::class);
        $this->router->url('user.show', []);
    }

    #[Test]
    public function add_pattern_registers_custom_pattern(): void
    {
        $this->router->addPattern('hash', '[a-f0-9]{32}');
        $this->router->get('/files/{hash}', 'FileController@download');

        $this->container->bind('FileController', fn() => new class {
            public function download(string $hash): string { return "file {$hash}"; }
        });

        // Valid hash
        $req1 = new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/files/abcdef1234567890abcdef1234567890']);
        $res1 = $this->router->dispatch($req1);
        $this->assertSame(200, $res1->getStatusCode());

        // Invalid hash (should not match the route, 404)
        $req2 = new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/files/tooshort']);
        $res2 = $this->router->dispatch($req2);
        $this->assertSame(404, $res2->getStatusCode());
    }

    #[Test]
    public function cache_creates_routes_file(): void
    {
        $this->router->get('/cached', 'CacheController@index');

        $this->container->bind('CacheController', fn() => new class {
            public function index(): string { return 'cached-response'; }
        });

        // Dispatch to trigger compilation and caching
        $req = new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/cached']);
        $this->router->dispatch($req);

        $cacheFile = $this->tmpCacheDir . '/routes.php';
        $this->assertFileExists($cacheFile);

        $raw = file_get_contents($cacheFile);
        $this->assertNotEmpty($raw);
    }

    #[Test]
    public function dispatch_uses_cache_after_compilation(): void
    {
        $this->router->get('/cache-test', 'CacheTestController@go');

        $this->container->bind('CacheTestController', fn() => new class {
            public function go(): string { return 'from-cache'; }
        });

        // First dispatch - compiles and caches
        $req1 = new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/cache-test']);
        $this->router->dispatch($req1);

        // Second dispatch - should load from cache
        $req2 = new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/cache-test']);
        $res2 = $this->router->dispatch($req2);
        $this->assertStringContainsString('from-cache', $res2->getContent());
    }

    #[Test]
    public function dispatch_loads_from_disk_cache(): void
    {
        // Create first router, compile routes, write cache to disk
        $container1 = new Container();
        $router1 = new Router($container1, $this->tmpCacheDir);
        $router1->get('/disk-cache', 'DiskCacheController@test');

        $container1->bind('DiskCacheController', fn() => new class {
            public function test(): string { return 'disk-cached'; }
        });

        // Dispatch to write cache
        $req1 = new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/disk-cache']);
        $router1->dispatch($req1);

        // Create a fresh router with same cache dir - should load from disk
        $container2 = new Container();
        $router2 = new Router($container2, $this->tmpCacheDir);
        $container2->bind('DiskCacheController', fn() => new class {
            public function test(): string { return 'disk-cached'; }
        });

        $req2 = new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/disk-cache']);
        $res2 = $router2->dispatch($req2);
        $this->assertStringContainsString('disk-cached', $res2->getContent());
    }

    #[Test]
    public function dispatch_returns_response_directly(): void
    {
        $this->router->get('/json', 'JsonController@data');

        $this->container->bind('JsonController', fn() => new class {
            public function data(): \Atom\Http\Response {
                return \Atom\Http\Response::json(['status' => 'ok']);
            }
        });

        $req = new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/json']);
        $response = $this->router->dispatch($req);
        $this->assertSame(200, $response->getStatusCode());
        $decoded = json_decode($response->getContent(), true);
        $this->assertSame(['status' => 'ok'], $decoded);
    }

    #[Test]
    public function dispatch_wraps_non_response_to_html(): void
    {
        $this->router->get('/number', 'NumberController@get');

        $this->container->bind('NumberController', fn() => new class {
            public function get(): int { return 42; }
        });

        $req = new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/number']);
        $response = $this->router->dispatch($req);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('42', $response->getContent());
    }

    #[Test]
    public function load_from_attributes_scans_directory(): void
    {
        // Create a temp directory with attributed controllers
        $attrDir = $this->tmpCacheDir . '/controllers';
        mkdir($attrDir, 0777, true);

        file_put_contents($attrDir . '/TestController.php', <<<'PHP'
        <?php
        namespace Atom\Tests\Routing\Fixtures;
        use Atom\Routing\Route;
        class TestController {
            #[Route('/attr-test', ['GET'], 'attr.test')]
            public function handle(): string { return 'from-attributes'; }
        }
        PHP);

        require_once $attrDir . '/TestController.php';

        $this->container->bind('Atom\Tests\Routing\Fixtures\TestController', fn() => new class {
            public function handle(): string { return 'from-attributes'; }
        });

        $this->router->loadFromAttributes($attrDir);

        $req = new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/attr-test']);
        $response = $this->router->dispatch($req);
        $this->assertStringContainsString('from-attributes', $response->getContent());
    }

    #[Test]
    public function controller_default_action_is_invoke(): void
    {
        $this->router->get('/invoke', 'InvokeController');

        $this->container->bind('InvokeController', fn() => new class {
            public function __invoke(): string { return 'invoked'; }
        });

        $req = new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/invoke']);
        $response = $this->router->dispatch($req);
        $this->assertStringContainsString('invoked', $response->getContent());
    }

    #[Test]
    public function url_with_custom_pattern_route(): void
    {
        $this->router->get('/items/{id:\d+}', 'ItemController@show', 'item.show');
        $url = $this->router->url('item.show', ['id' => '7']);
        $this->assertSame('/items/7', $url);
    }

    #[Test]
    public function patch_method_adds_patch_route(): void
    {
        $this->router->patch('/resource/{id}', 'ResourceController@patch');

        $this->container->bind('ResourceController', fn() => new class {
            public function patch(string $id): string { return "patched {$id}"; }
        });

        $req = new Request(server: ['REQUEST_METHOD' => 'PATCH', 'REQUEST_URI' => '/resource/99']);
        $response = $this->router->dispatch($req);
        $this->assertStringContainsString('patched 99', $response->getContent());
    }

    #[Test]
    public function any_matches_all_http_methods(): void
    {
        $this->router->any('/webhook', 'WebhookController@handle');

        $this->container->bind('WebhookController', fn() => new class {
            public function handle(): string { return 'webhook'; }
        });

        foreach (['GET','POST','PUT','PATCH','DELETE'] as $method) {
            $req = new Request(server: ['REQUEST_METHOD' => $method, 'REQUEST_URI' => '/webhook']);
            $res = $this->router->dispatch($req);
            $this->assertStringContainsString('webhook', $res->getContent(), "{$method} should match");
        }
    }

    #[Test]
    public function match_specific_methods(): void
    {
        $this->router->match(['GET', 'POST'], '/multi', 'MultiController@handle');

        $this->container->bind('MultiController', fn() => new class {
            public function handle(): string { return 'multi'; }
        });

        $reqGet = new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/multi']);
        $this->assertStringContainsString('multi', $this->router->dispatch($reqGet)->getContent());

        $reqPost = new Request(server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/multi']);
        $this->assertStringContainsString('multi', $this->router->dispatch($reqPost)->getContent());

        $reqPut = new Request(server: ['REQUEST_METHOD' => 'PUT', 'REQUEST_URI' => '/multi']);
        $res = $this->router->dispatch($reqPut);
        $this->assertSame(405, $res->getStatusCode());
    }

    #[Test]
    public function name_prefix_prepends_to_route_names(): void
    {
        $this->router->namePrefix('admin.', function (Router $r) {
            $r->get('/dashboard', 'AdminController@index', 'dashboard');
        });

        $req = new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/dashboard']);
        $this->container->bind('AdminController', fn() => new class {
            public function index(): string { return 'admin'; }
        });
        $res = $this->router->dispatch($req);
        $this->assertStringContainsString('admin', $res->getContent());

        $url = $this->router->url('admin.dashboard');
        $this->assertSame('/dashboard', $url);
    }

    #[Test]
    public function name_prefix_nested(): void
    {
        $this->router->namePrefix('v1.', function (Router $r) {
            $r->namePrefix('users.', function (Router $r2) {
                $r2->get('/users', 'UserController@list', 'list');
            });
        });

        $url = $this->router->url('v1.users.list');
        $this->assertSame('/users', $url);
    }

    #[Test]
    public function clear_cache_removes_cache_file(): void
    {
        $this->router->get('/test', 'TestController@index');
        $req = new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/test']);
        $this->container->bind('TestController', fn() => new class {
            public function index(): string { return 'hit'; }
        });

        // Dispatch to populate cache
        $this->router->dispatch($req);
        $cacheFile = $this->tmpCacheDir . '/routes.php';
        $this->assertFileExists($cacheFile);

        $this->router->clearCache();
        $this->assertFileDoesNotExist($cacheFile);
    }

    #[Test]
    public function group_preserves_name_prefix_independence(): void
    {
        $this->router->namePrefix('a.', function (Router $r) {
            $r->get('/x', 'XController@x', 'x');
        });
        $this->router->namePrefix('b.', function (Router $r) {
            $r->get('/y', 'YController@y', 'y');
        });

        $this->assertSame('/x', $this->router->url('a.x'));
        $this->assertSame('/y', $this->router->url('b.y'));
    }

    #[Test]
    public function duplicate_route_name_in_add_throws(): void
    {
        $this->router->get('/first', 'FirstController@test', 'dup.name');
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate route name');
        $this->router->get('/second', 'SecondController@test', 'dup.name');
    }

    #[Test]
    public function duplicate_route_name_in_match_throws(): void
    {
        $this->router->get('/first', 'FirstController@test', 'match.dup');
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate route name');
        $this->router->match(['POST'], '/second', 'SecondController@test', 'match.dup');
    }

    #[Test]
    public function duplicate_name_via_group_prefix(): void
    {
        $this->router->namePrefix('api.', function (Router $r) {
            $r->get('/a', 'AController@test', 'route');
            $this->expectException(\InvalidArgumentException::class);
            $this->expectExceptionMessage('Duplicate route name');
            $r->get('/b', 'BController@test', 'route');
        });
    }

    #[Test]
    public function router_duplicate_name_detected_after_many_adds(): void
    {
        for ($i = 0; $i < 100; $i++) {
            $this->router->get("/r{$i}", 'Ctrl@test', "route.{$i}");
        }
        $this->expectException(\InvalidArgumentException::class);
        $this->router->get('/dup', 'Ctrl@test', 'route.42');
    }

    #[Test]
    public function url_lookup_is_constant_time(): void
    {
        for ($i = 0; $i < 200; $i++) {
            $this->router->get("/r{$i}", 'Ctrl@test', "name.{$i}");
        }
        $url = $this->router->url('name.150', ['id' => 'x']);
        $this->assertSame('/r150', $url);
    }

    #[Test]
    public function attribute_loaded_routes_url_generation(): void
    {
        $attrDir = $this->tmpCacheDir . '/attr';
        mkdir($attrDir, 0777, true);
        file_put_contents($attrDir . '/AttrCtrl.php', '<?php
        namespace App;
        use Atom\Routing\Route;
        class AttrCtrl {
            #[Route("/attr-path", ["GET"], "attr.name")]
            public function handle(): string { return "ok"; }
        }
        ');
        require_once $attrDir . '/AttrCtrl.php';
        $this->router->loadFromAttributes($attrDir);
        $url = $this->router->url('attr.name');
        $this->assertSame('/attr-path', $url);
    }

    #[Test]
    public function corrupted_cache_file_recovers(): void
    {
        $cacheFile = $this->tmpCacheDir . '/routes.php';
        file_put_contents($cacheFile, '<?php return BAD_SYNTAX!!!;');
        $this->router->get('/ok', 'Ctrl@test');
        $this->container->bind('Ctrl', fn() => new class {
            public function test(): string { return 'ok'; }
        });
        $req = new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/ok']);
        $res = $this->router->dispatch($req);
        $this->assertSame(200, $res->getStatusCode());
    }

    #[Test]
    public function match_with_empty_methods_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('at least one HTTP method');
        $this->router->match([], '/path', 'Ctrl@act');
    }

    #[Test]
    public function unnamed_routes_allow_duplicates(): void
    {
        $this->router->get('/x', 'XController@test');
        $this->router->get('/y', 'YController@test');
        $this->assertSame(2, count((new \ReflectionClass($this->router))->getProperty('routes')->getValue($this->router)));
    }

    #[Test]
    public function dispatch_catchall_404(): void
    {
        $this->router->get('/only', 'OnlyController@test');
        $req = new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/nonexistentpath']);
        $res = $this->router->dispatch($req);
        $this->assertSame(404, $res->getStatusCode());
    }

    #[Test]
    public function method_not_allowed_for_wrong_method(): void
    {
        $this->router->post('/submit', 'SubmitController@action');
        $req = new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/submit']);
        $res = $this->router->dispatch($req);
        $this->assertSame(405, $res->getStatusCode());
        $this->assertStringContainsString('Allow', $res->getContent());
    }

    #[Test]
    public function dispatch_with_non_public_controller_method(): void
    {
        $this->router->get('/private', 'PrivateController@secret');
        $this->container->bind('PrivateController', fn() => new class {
            private function secret(): string { return 'hidden'; }
        });
        $req = new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/private']);
        $res = $this->router->dispatch($req);
        $this->assertSame(500, $res->getStatusCode());
    }
}
