<?php
declare(strict_types=1);
namespace Atom\Console;

use Atom\Application;
use Atom\Support\Regex;

final class Console
{
    private array $commands = [];

    private const COLOR = [
        'reset'  => "\033[0m",
        'bold'   => "\033[1m",
        'green'  => "\033[32m",
        'yellow' => "\033[33m",
        'cyan'   => "\033[36m",
        'red'    => "\033[31m",
    ];

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
        $args = array_slice($argv, 2);

        // Parse options: --flag and --key=value
        $options = [];
        $positional = [];
        foreach ($args as $arg) {
            if ($m = Regex::match('#^--([a-zA-Z][a-zA-Z0-9_-]*)(?:=(.+))?$#', $arg)) {
                $options[$m[1]] = $m[2] ?? true;
            } else {
                $positional[] = $arg;
            }
        }

        return match ($cmd) {
            'list'    => $this->listCommands(),
            'routes'  => $this->listRoutes(),
            'cache'   => $this->clearCache(),
            default   => $this->executeCommand($cmd, $positional, $options),
        };
    }

    private function listCommands(): int
    {
        $this->out('bold', "Atom CLI\n");
        $this->out('cyan', "  list        Show available commands\n");
        $this->out('cyan', "  routes      List registered routes\n");
        $this->out('cyan', "  cache       Clear compiled cache\n");
        foreach (array_keys($this->commands) as $name) {
            $this->out('green', "  {$name}\n");
        }
        return 0;
    }

    private function listRoutes(): int
    {
        $routes = $this->app->router->routes();
        if ($routes === []) { echo "No routes registered.\n"; return 0; }

        foreach ($routes as $r) {
            $methods = $this->color('yellow', implode('|', $r->methods));
            $name = $r->name !== '' ? $this->color('green', " [{$r->name}]") : '';
            echo "  {$methods}  {$r->path}  → {$r->controller}@{$r->action}{$name}\n";
        }
        return 0;
    }

    private function clearCache(): int
    {
        $dir = $this->app->config->cacheDir;
        if ($dir === '' || !is_dir($dir)) {
            $this->out('yellow', "No cache dir configured.\n");
            return 1;
        }
        $files = array_merge(
            glob($dir . '/*.php') ?: [],
            glob($dir . '/**/*.php') ?: [],
            glob($dir . '/*') ?: [],
        );
        $files = array_unique($files);
        $count = 0;
        foreach ($files as $f) {
            if (is_file($f)) {
                unlink($f);
                $count++;
            }
        }
        $this->out('green', "Cleared {$count} cached file(s).\n");
        return 0;
    }

    private function executeCommand(string $name, array $args, array $options): int
    {
        if (!isset($this->commands[$name])) {
            $this->out('red', "Unknown command: {$name}\n");
            return 1;
        }
        $result = ($this->commands[$name])($args, $options);
        return is_int($result) ? $result : 0;
    }

    private function color(string $c, string $text): string
    {
        return (self::COLOR[$c] ?? '') . $text . self::COLOR['reset'];
    }

    private function out(string $c, string $text): void
    {
        echo $this->color($c, $text);
    }
}
