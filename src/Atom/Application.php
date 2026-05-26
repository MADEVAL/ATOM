<?php
declare(strict_types=1);
namespace Atom;

use Atom\Container\Container;
use Atom\Http\{Request, Response};
use Atom\Routing\Router;
use Atom\View\Engine as ViewEngine;

final class Application
{
    public readonly Container $container;
    public readonly Router    $router;
    public readonly ViewEngine $view;

    private array $booted = [];

    public function __construct(array $config = [])
    {
        $this->container = new Container();
        $this->router    = new Router($this->container, $config['cache_dir']  ?? sys_get_temp_dir() . '/atom');
        $this->view      = new ViewEngine($config['views_dir'] ?? __DIR__ . '/../../views',
                                          $config['cache_dir'] ?? sys_get_temp_dir() . '/atom/views');

        $this->container->instance(self::class,    $this);
        $this->container->instance(Container::class, $this->container);
        $this->container->instance(Router::class,  $this->router);
        $this->container->instance(ViewEngine::class, $this->view);
    }

    public function run(?Request $request = null): void
    {
        $req = $request ?? Request::capture();
        $this->container->instance(Request::class, $req);

        try {
            $response = $this->router->dispatch($req);
        } catch (\Throwable $e) {
            $response = new Response(
                "Server Error: {$e->getMessage()}\n{$e->getTraceAsString()}",
                \Atom\Http\StatusCode::SERVER_ERROR,
            );
        }
        $response->send();
    }
}
