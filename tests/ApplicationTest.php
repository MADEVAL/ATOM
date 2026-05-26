<?php
declare(strict_types=1);
namespace Atom\Tests;

use Atom\Application;
use Atom\Config;
use Atom\Http\Request;
use Atom\Http\Response;
use Atom\View\Engine as ViewEngine;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(Application::class)]
#[CoversClass(Config::class)]
final class ApplicationTest extends TestCase
{
    private string $tmpDir;
    private string $tmpViewsDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/atom_app_' . uniqid();
        $this->tmpViewsDir = sys_get_temp_dir() . '/atom_app_views_' . uniqid();
        mkdir($this->tmpDir, 0777, true);
        mkdir($this->tmpViewsDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->rmDir($this->tmpDir);
        $this->rmDir($this->tmpViewsDir);
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

    private function makeApp(bool $debug = false): Application
    {
        return new Application(new Config(
            debug: $debug,
            cacheDir: $this->tmpDir,
            viewsDir: $this->tmpViewsDir,
        ));
    }

    #[Test]
    public function application_creates_container(): void
    {
        $app = $this->makeApp();
        $this->assertInstanceOf(\Atom\Container\Container::class, $app->container);
    }

    #[Test]
    public function application_creates_router(): void
    {
        $app = $this->makeApp();
        $this->assertInstanceOf(\Atom\Routing\Router::class, $app->router);
    }

    #[Test]
    public function application_creates_view_engine(): void
    {
        $app = $this->makeApp();
        $this->assertInstanceOf(ViewEngine::class, $app->view);
    }

    #[Test]
    public function application_stores_config(): void
    {
        $app = $this->makeApp();
        $this->assertInstanceOf(Config::class, $app->config);
        $this->assertFalse($app->config->debug);
    }

    #[Test]
    public function application_registers_self_in_container(): void
    {
        $app = $this->makeApp();
        $resolved = $app->container->make(Application::class);
        $this->assertSame($app, $resolved);
    }

    #[Test]
    public function application_registers_container_in_container(): void
    {
        $app = $this->makeApp();
        $resolved = $app->container->make(\Atom\Container\Container::class);
        $this->assertSame($app->container, $resolved);
    }

    #[Test]
    public function application_registers_router_in_container(): void
    {
        $app = $this->makeApp();
        $resolved = $app->container->make(\Atom\Routing\Router::class);
        $this->assertSame($app->router, $resolved);
    }

    #[Test]
    public function application_registers_view_in_container(): void
    {
        $app = $this->makeApp();
        $resolved = $app->container->make(ViewEngine::class);
        $this->assertSame($app->view, $resolved);
    }

    #[Test]
    public function run_dispatches_request(): void
    {
        $app = $this->makeApp();
        $app->router->get('/hello', 'HelloController@say');
        $app->container->bind('HelloController', fn() => new class {
            public function say(): string { return 'Hello from app'; }
        });

        $req = new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/hello']);

        ob_start();
        $app->run($req);
        $output = ob_get_clean();

        $this->assertStringContainsString('Hello from app', $output);
    }

    #[Test]
    public function run_captures_request_if_none_given(): void
    {
        $app = $this->makeApp();
        $app->router->get('/', 'HomeController@index');
        $app->container->bind('HomeController', fn() => new class {
            public function index(): string { return 'home'; }
        });

        $prevMethod = $_SERVER['REQUEST_METHOD'] ?? null;
        $prevUri = $_SERVER['REQUEST_URI'] ?? null;
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/';

        ob_start();
        $app->run();
        $output = ob_get_clean();

        if ($prevMethod === null) { unset($_SERVER['REQUEST_METHOD']); } else { $_SERVER['REQUEST_METHOD'] = $prevMethod; }
        if ($prevUri === null) { unset($_SERVER['REQUEST_URI']); } else { $_SERVER['REQUEST_URI'] = $prevUri; }

        $this->assertStringContainsString('home', $output);
    }

    #[Test]
    public function run_handles_exception_as_500_in_debug(): void
    {
        $app = $this->makeApp(debug: true);
        $app->router->get('/crash', 'CrashController@boom');
        $app->container->bind('CrashController', fn() => new class {
            public function boom(): never { throw new \RuntimeException('Test crash'); }
        });

        $req = new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/crash']);

        ob_start();
        $app->run($req);
        $output = ob_get_clean();

        $this->assertStringContainsString('Test crash', $output);
        $this->assertStringContainsString('Test crash', $output);
    }

    #[Test]
    public function run_hides_exception_details_in_production(): void
    {
        $app = $this->makeApp(debug: false);
        $app->router->get('/crash', 'CrashController@boom');
        $app->container->bind('CrashController', fn() => new class {
            public function boom(): never { throw new \RuntimeException('secret info'); }
        });

        $req = new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/crash']);

        ob_start();
        $app->run($req);
        $output = ob_get_clean();

        $this->assertStringNotContainsString('secret info', $output);
        $this->assertStringNotContainsString('Server Error', $output);
        $this->assertEmpty($output);
    }

    #[Test]
    public function run_sets_request_in_container(): void
    {
        $app = $this->makeApp();
        $req = new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/']);
        $app->router->get('/', 'ReqController@check');

        $requestInController = null;
        $app->container->bind('ReqController', function () use (&$requestInController) {
            return new class($requestInController) {
                public function __construct(private ?Request &$captured) {}
                public function check(Request $request): string {
                    $this->captured = $request;
                    return 'ok';
                }
            };
        });

        ob_start();
        $app->run($req);
        ob_get_clean();

        $this->assertNotNull($requestInController);
        $this->assertInstanceOf(Request::class, $requestInController);
    }

    #[Test]
    public function run_view_rendering(): void
    {
        file_put_contents($this->tmpViewsDir . '/welcome.twig', '<h1>{{ title }}</h1>');

        $app = $this->makeApp();
        $app->router->get('/', 'WelcomeController@show');
        $app->container->bind('WelcomeController', fn() => new class($app) {
            public function __construct(private Application $app) {}
            public function show(): string { return $this->app->view->render('welcome.twig', ['title' => 'Welcome']); }
        });

        $req = new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/']);

        ob_start();
        $app->run($req);
        $output = ob_get_clean();

        $this->assertStringContainsString('<h1>Welcome</h1>', $output);
    }

    #[Test]
    public function application_uses_default_dirs_when_not_configured(): void
    {
        $app = new Application();
        $this->assertInstanceOf(Application::class, $app);
        $this->assertInstanceOf(ViewEngine::class, $app->view);
        $this->assertInstanceOf(\Atom\Routing\Router::class, $app->router);
        $this->assertFalse($app->config->debug);
    }

    #[Test]
    public function config_debug_flag(): void
    {
        $cfg = new Config(debug: true);
        $this->assertTrue($cfg->debug);

        $cfg2 = new Config(debug: false);
        $this->assertFalse($cfg2->debug);

        $cfg3 = new Config();
        $this->assertFalse($cfg3->debug);
    }

    #[Test]
    public function config_stores_dirs(): void
    {
        $cfg = new Config(cacheDir: '/tmp/cache', viewsDir: '/tmp/views');
        $this->assertSame('/tmp/cache', $cfg->cacheDir);
        $this->assertSame('/tmp/views', $cfg->viewsDir);
    }

    #[Test]
    public function run_respects_response_status(): void
    {
        $app = $this->makeApp();
        $app->router->get('/not-found', 'NotFoundController@handle');
        $app->container->bind('NotFoundController', fn() => new class {
            public function handle(): Response { return new Response('Not here', 404); }
        });

        $req = new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/not-found']);

        ob_start();
        $app->run($req);
        $output = ob_get_clean();
        $this->assertStringContainsString('Not here', $output);
    }

    #[Test]
    public function run_error_in_production_returns_empty_500(): void
    {
        $app = $this->makeApp(debug: false);
        $app->router->get('/boom', 'BoomController@explode');
        $app->container->bind('BoomController', fn() => new class {
            public function explode(): never { throw new \RuntimeException('Boom'); }
        });
        $req = new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/boom']);

        ob_start();
        $app->run($req);
        $output = ob_get_clean();
        $this->assertEmpty($output);
    }

    #[Test]
    public function run_php_error_rethrows_in_debug(): void
    {
        $app = $this->makeApp(debug: true);
        $app->router->get('/type', 'TypeController@fail');
        $app->container->bind('TypeController', fn() => new class {
            public function fail(): never { throw new \TypeError('Type mismatch'); }
        });
        $req = new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/type']);

        $this->expectException(\TypeError::class);
        ob_start();
        try {
            $app->run($req);
        } finally {
            ob_end_clean();
        }
    }
}
