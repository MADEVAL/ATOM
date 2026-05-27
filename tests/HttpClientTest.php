<?php
declare(strict_types=1);
namespace Atom\Tests;

use Atom\Application;
use Atom\Config;
use Atom\Http\Response;
use Atom\Test\HttpClient;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(HttpClient::class)]
final class HttpClientTest extends TestCase
{
    private Application $app;
    private string $tmpCache;

    protected function setUp(): void
    {
        $this->tmpCache = sys_get_temp_dir() . '/atom_hc_' . uniqid();
        $this->app = new Application(new Config(cacheDir: $this->tmpCache, debug: true));
    }

    protected function tearDown(): void
    {
        foreach ((array) glob($this->tmpCache . '/*') as $f) @unlink($f);
        if (is_dir($this->tmpCache)) rmdir($this->tmpCache);
    }

    #[Test]
    public function get_returns_200(): void
    {
        $this->app->router->get('/hello', 'Ctrl@hello');
        $this->app->container->bind('Ctrl', fn() => new class {
            public function hello(): string { return 'world'; }
        });
        (new HttpClient($this->app))->get('/hello')->assertOk()->assertBodyContains('world');
        $this->assertTrue(true);
    }

    #[Test]
    public function post_with_json_body(): void
    {
        $this->app->router->post('/echo', 'Ctrl@echo');
        $this->app->container->bind('Ctrl', fn() => new class {
            public function echo(\Atom\Http\Request $request): Response {
                return Response::json(['received' => $request->body['name'] ?? '']);
            }
        });
        $c = new HttpClient($this->app);
        $c->post('/echo', ['name' => 'test'])->assertOk();
        $this->assertTrue(true);
    }

    #[Test]
    public function not_found_returns_404(): void
    {
        (new HttpClient($this->app))->get('/nonexistent')->assertNotFound();
        $this->assertTrue(true);
    }

    #[Test]
    public function put_and_delete_supported(): void
    {
        $this->app->router->put('/res/{id}', 'Ctrl@put');
        $this->app->router->delete('/res/{id}', 'Ctrl@del');
        $this->app->container->bind('Ctrl', fn() => new class {
            public function put(string $id): Response { return Response::json(['ok' => 'put']); }
            public function del(string $id): Response { return Response::json(['ok' => 'del']); }
        });
        $c = new HttpClient($this->app);
        $c->put('/res/1', [])->assertOk();
        $c->delete('/res/2')->assertOk();
        $this->assertTrue(true);
    }
}
