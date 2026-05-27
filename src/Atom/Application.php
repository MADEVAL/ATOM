<?php
declare(strict_types=1);
namespace Atom;

use Atom\Container\Container;
use Atom\Http\{Request, Response, Session, StatusCode};
use Atom\Middleware\{MiddlewareInterface, Pipeline};
use Atom\Routing\Router;
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
            $this->wsServer = new WsServer($this, $wsHost, $wsPort);
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
            $response = new Response('', StatusCode::SERVER_ERROR);
        }
        $response->send();
    }
}
