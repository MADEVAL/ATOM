<?php
declare(strict_types=1);
namespace Atom;

use Atom\Container\Container;
use Atom\Http\{Request, Response, Session, StatusCode};
use Atom\Routing\Router;
use Atom\View\Engine as ViewEngine;

final class Application
{
    public readonly Container $container;
    public readonly Router $router;
    public readonly ViewEngine $view;

    public function __construct(
        public readonly Config $config = new Config,
    ) {
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
        $this->container->instance(Session::class, new Session());
    }

    public function run(?Request $request = null): void
    {
        $req = $request ?? Request::capture();
        $this->container->instance(Request::class, $req);

        try {
            $response = $this->router->dispatch($req);
        } catch (\Exception $e) {
            $response = $this->config->debug
                ? new Response("Error: {$e->getMessage()}", StatusCode::SERVER_ERROR)
                : new Response('', StatusCode::SERVER_ERROR);
        } catch (\Error $e) {
            if ($this->config->debug) throw $e;
            $response = new Response('', StatusCode::SERVER_ERROR);
        }
        $response->send();
    }
}
