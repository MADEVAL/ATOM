<?php
declare(strict_types=1);
namespace Atom;

use Atom\Container\Container;
use Atom\Cache\{Cache, FileDriver, ArrayDriver};
use Atom\Http\{Request, Response, Session, StatusCode};
use Atom\Middleware\{MiddlewareInterface, Pipeline};
use Atom\Routing\Router;
use Atom\Support\Logger;
use Atom\Validation\ValidationException;
use Atom\View\Engine as ViewEngine;
use Atom\WebSocket\Server as WsServer;

final class Application
{
    public readonly Container $container;
    public readonly Router $router;
    public readonly ViewEngine $view;

    private array $middleware = [];
    private ?WsServer $wsServer = null;
    private ?Cache $cache = null;

    public function __construct(
        public readonly Config $config = new Config,
    ) {
        if ($config->timezone !== 'UTC') {
            date_default_timezone_set($config->timezone);
        }
        $this->container = new Container();
        $this->router    = new Router($this->container, $config->cacheDir ?: sys_get_temp_dir() . '/atom');
        $this->view      = new ViewEngine(
            $config->viewsDir ?: __DIR__ . '/../../views',
            $config->cacheDir ?: sys_get_temp_dir() . '/atom/views',
        );

        $this->container->instance(self::class, $this);
        $this->container->instance(Container::class, $this->container);
        $this->container->instance(Router::class, $this->router);
        $this->container->instance(ViewEngine::class, $this->view);
        $this->container->instance(Config::class, $config);
        $this->container->singleton(Session::class, fn() => new Session());
        $this->container->singleton(Logger::class, fn() => new Logger(
            $config->logFile ?: sys_get_temp_dir() . '/atom/app.log',
            $config->logLevel,
            $config->logMaxSize,
        ));
    }

    public function use(\Closure|MiddlewareInterface|string $middleware): self
    {
        $this->middleware[] = $middleware;
        return $this;
    }

    /**
     * Lazily initializes the WebSocket server and registers a route handler.
     *
     * @param string $path Route pattern, e.g. '/chat/{room}'
     * @param callable $handler fn(Connection $conn, mixed $data, string $event, array $params)
     */
    public function ws(string $path, callable $handler): self
    {
        if ($this->wsServer === null) {
            $wsHost = $this->config->env['WS_HOST'] ?? '0.0.0.0';
            $wsPort = (int) ($this->config->env['WS_PORT'] ?? 8080);
            $this->wsServer = new WsServer($wsHost, $wsPort);
            $this->container->instance(WsServer::class, $this->wsServer);
        }
        $this->wsServer->add($path, $handler);
        return $this;
    }

    /** Returns the WebSocket server instance if initialized */
    public function wsServer(): ?WsServer
    {
        return $this->wsServer;
    }

    /**
     * Returns the cache instance, lazily initialized with the driver
     * specified in APP_CACHE_DRIVER env var ('array' or 'file').
     * Defaults to file driver when cacheDir is configured, array otherwise.
     */
    public function cache(): Cache
    {
        if ($this->cache === null) {
            $driver = $this->config->env['APP_CACHE_DRIVER'] ?? null;
            if ($driver === 'array') {
                $this->cache = new Cache(new ArrayDriver());
            } else {
                $dir = $this->config->cacheDir ?: sys_get_temp_dir() . '/atom/cache';
                $this->cache = new Cache(new FileDriver($dir));
            }
            $this->container->instance(Cache::class, $this->cache);
        }
        return $this->cache;
    }

    public function log(): Logger
    {
        return $this->container->make(Logger::class);
    }

    public function run(?Request $request = null): void
    {
        $req = $request ?? Request::capture();
        if ($request !== null || !$this->container->has(Request::class)) {
            $this->container->instance(Request::class, $req);
        }

        try {
            $handler = fn(): Response => $this->router->dispatch($req);
            $response = $this->middleware !== []
                ? Pipeline::run($this->middleware, $req, $handler, $this->container)
                : $handler();
        } catch (ValidationException $e) {
            $response = Response::json($e->errors, StatusCode::UNPROCESSABLE_ENTITY);
        } catch (\Throwable $e) {
            if ($this->config->debug) {
                throw $e;
            }
            try {
                $this->log()->error($e->getMessage(), ['exception' => $e::class, 'uri' => $req->uri]);
            } catch (\Throwable) {}
            $response = new Response('', StatusCode::SERVER_ERROR);
        }
        $response->send();
    }
}
