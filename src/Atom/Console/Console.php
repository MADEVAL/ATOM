<?php
declare(strict_types=1);
namespace Atom\Console;

use Atom\Application;
use Atom\Routing\Router;
use ReflectionClass;

final class Console
{
    private array $commands = [];

    public function __construct(
        private Application $app,
    ) {}

    public function add(string $name, callable $handler): self
    {
        $this->commands[$name] = $handler;
        return $this;
    }

    public function run(array $argv): int
    {
        $cmd = $argv[1] ?? 'list';
        return match ($cmd) {
            'list'    => $this->listCommands(),
            'routes'  => $this->listRoutes(),
            'cache'   => $this->clearCache(),
            default   => $this->executeCommand($cmd, array_slice($argv, 2)),
        };
    }

    private function listCommands(): int
    {
        echo "Atom CLI\n\n";
        echo "  list        Show available commands\n";
        echo "  routes      List registered routes\n";
        echo "  cache       Clear compiled cache\n";
        foreach (array_keys($this->commands) as $name) {
            echo "  {$name}\n";
        }
        return 0;
    }

    private function listRoutes(): int
    {
        $ref = new ReflectionClass($this->app->router);
        $routes = $ref->getProperty('routes')->getValue($this->app->router);
        if ($routes === []) { echo "No routes registered.\n"; return 0; }

        foreach ($routes as $r) {
            $methods = implode('|', $r->methods);
            $name = $r->name !== '' ? " [{$r->name}]" : '';
            echo "  {$methods}  {$r->path}  → {$r->controller}@{$r->action}{$name}\n";
        }
        return 0;
    }

    private function clearCache(): int
    {
        $dir = $this->app->config->cacheDir;
        if ($dir === '' || !is_dir($dir)) { echo "No cache dir configured.\n"; return 1; }
        $count = 0;
        foreach ((array) glob($dir . '/*.php') as $f) {
            unlink($f);
            $count++;
        }
        echo "Cleared {$count} cached file(s).\n";
        return 0;
    }

    private function executeCommand(string $name, array $args): int
    {
        if (!isset($this->commands[$name])) {
            echo "Unknown command: {$name}\n";
            return 1;
        }
        return ($this->commands[$name])($args) ?? 0;
    }
}
