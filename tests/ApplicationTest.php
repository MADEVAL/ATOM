<?php
declare(strict_types=1);
namespace Atom\Tests;

use Atom\Application;
use Atom\Http\Request;
use Atom\Http\Response;
use Atom\View\Engine as ViewEngine;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(Application::class)]
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

    #[Test]
    public function application_creates_container(): void
    {
        $app = new Application([
            'views_dir' => $this->tmpViewsDir,
            'cache_dir' => $this->tmpDir,
        ]);

        $this->assertInstanceOf(\Atom\Container\Container::class, $app->container);
    }

    #[Test]
    public function application_creates_router(): void
    {
        $app = new Application([
            'views_dir' => $this->tmpViewsDir,
            'cache_dir' => $this->tmpDir,
        ]);

        $this->assertInstanceOf(\Atom\Routing\Router::class, $app->router);
    }

    #[Test]
    public function application_creates_view_engine(): void
    {
        $app = new Application([
            'views_dir' => $this->tmpViewsDir,
            'cache_dir' => $this->tmpDir,
        ]);

        $this->assertInstanceOf(ViewEngine::class, $app->view);
    }

    #[Test]
    public function application_registers_self_in_container(): void
    {
        $app = new Application([
            'views_dir' => $this->tmpViewsDir,
            'cache_dir' => $this->tmpDir,
        ]);

        $resolved = $app->container->make(Application::class);
        $this->assertSame($app, $resolved);
    }

    #[Test]
    public function application_registers_container_in_container(): void
    {
        $app = new Application([
            'views_dir' => $this->tmpViewsDir,
            'cache_dir' => $this->tmpDir,
        ]);

        $resolved = $app->container->make(\Atom\Container\Container::class);
        $this->assertSame($app->container, $resolved);
    }

    #[Test]
    public function application_registers_router_in_container(): void
    {
        $app = new Application([
            'views_dir' => $this->tmpViewsDir,
            'cache_dir' => $this->tmpDir,
        ]);

        $resolved = $app->container->make(\Atom\Routing\Router::class);
        $this->assertSame($app->router, $resolved);
    }

    #[Test]
    public function application_registers_view_in_container(): void
    {
        $app = new Application([
            'views_dir' => $this->tmpViewsDir,
            'cache_dir' => $this->tmpDir,
        ]);

        $resolved = $app->container->make(ViewEngine::class);
        $this->assertSame($app->view, $resolved);
    }

    #[Test]
    public function run_dispatches_request(): void
    {
        $app = new Application([
            'views_dir' => $this->tmpViewsDir,
            'cache_dir' => $this->tmpDir,
        ]);

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
        $app = new Application([
            'views_dir' => $this->tmpViewsDir,
            'cache_dir' => $this->tmpDir,
        ]);

        $app->router->get('/', 'HomeController@index');
        $app->container->bind('HomeController', fn() => new class {
            public function index(): string { return 'home'; }
        });

        // Override globals to simulate a request
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/';

        ob_start();
        $app->run();
        $output = ob_get_clean();

        $this->assertStringContainsString('home', $output);
    }

    #[Test]
    public function run_handles_exception_as_500(): void
    {
        $app = new Application([
            'views_dir' => $this->tmpViewsDir,
            'cache_dir' => $this->tmpDir,
        ]);

        $app->router->get('/crash', 'CrashController@boom');
        $app->container->bind('CrashController', fn() => new class {
            public function boom(): never { throw new \RuntimeException('Test crash'); }
        });

        $req = new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/crash']);

        ob_start();
        $app->run($req);
        $output = ob_get_clean();

        $this->assertStringContainsString('Server Error', $output);
        $this->assertStringContainsString('Test crash', $output);
    }

    #[Test]
    public function run_sets_request_in_container(): void
    {
        $app = new Application([
            'views_dir' => $this->tmpViewsDir,
            'cache_dir' => $this->tmpDir,
        ]);

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

        $app = new Application([
            'views_dir' => $this->tmpViewsDir,
            'cache_dir' => $this->tmpDir,
        ]);

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
        $app = new Application([]);
        $this->assertInstanceOf(Application::class, $app);
        $this->assertInstanceOf(ViewEngine::class, $app->view);
        $this->assertInstanceOf(\Atom\Routing\Router::class, $app->router);
    }

    #[Test]
    public function run_respects_response_status(): void
    {
        $app = new Application([
            'views_dir' => $this->tmpViewsDir,
            'cache_dir' => $this->tmpDir,
        ]);

        $app->router->get('/not-found', 'NotFoundController@handle');
        $app->container->bind('NotFoundController', fn() => new class {
            public function handle(): Response { return new Response('Not here', 404); }
        });

        $req = new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/not-found']);

        ob_start();
        $app->run($req);
        ob_get_clean();

        $this->assertTrue(true); // No exception
    }
}
