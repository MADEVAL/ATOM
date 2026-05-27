<?php
declare(strict_types=1);
namespace Atom\Console;

use Atom\Application;
use Atom\Support\Regex;

final class Console
{
    private array $commands = [];
    private array $descriptions = [];
    private bool $noColor;

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
    ) {
        $this->noColor = ($_ENV['NO_COLOR'] ?? getenv('NO_COLOR')) !== false
            && ($_ENV['NO_COLOR'] ?? getenv('NO_COLOR')) !== ''
            && ($_ENV['NO_COLOR'] ?? getenv('NO_COLOR')) !== '0';
    }

    public function add(string $name, callable $handler, string $desc = ''): self
    {
        $this->commands[$name] = $handler;
        if ($desc !== '') {
            $this->descriptions[$name] = $desc;
        }
        return $this;
    }

    public function run(array $argv): int
    {
        $cmd = $argv[1] ?? 'list';
        $args = array_slice($argv, 2);

        $options = [];
        $positional = [];
        foreach ($args as $arg) {
            if ($arg === '--no-color') {
                $this->noColor = true;
            } elseif ($m = Regex::match('#^--([a-zA-Z][a-zA-Z0-9_-]*)(?:=(.+))?$#', $arg)) {
                $options[$m[1]] = $m[2] ?? true;
            } else {
                $positional[] = $arg;
            }
        }

        return match ($cmd) {
            'list', 'help' => $this->listCommands(),
            'routes'       => $this->listRoutes(),
            'cache'        => $this->clearCache(),
            default        => $this->executeCommand($cmd, $positional, $options),
        };
    }

    private function listCommands(): int
    {
        $this->out('bold', "Atom CLI\n");
        $this->out('cyan', "  list        Show available commands\n");
        $this->out('cyan', "  help        Show available commands\n");
        $this->out('cyan', "  routes      List registered routes\n");
        $this->out('cyan', "  cache       Clear compiled cache\n");
        foreach ($this->commands as $name => $handler) {
            $desc = $this->descriptions[$name] ?? '';
            $line = "  {$name}";
            if ($desc !== '') {
                $line .= str_repeat(' ', max(1, 14 - strlen($name))) . $desc;
            }
            $this->out('green', "{$line}\n");
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
        try {
            $result = ($this->commands[$name])($args, $options);
            return is_int($result) ? $result : 0;
        } catch (\Throwable $e) {
            $this->out('red', "Command '{$name}' failed: " . $e->getMessage() . "\n");
            return 1;
        }
    }

    private function color(string $c, string $text): string
    {
        if ($this->noColor) return $text;
        return (self::COLOR[$c] ?? '') . $text . self::COLOR['reset'];
    }

    private function out(string $c, string $text): void
    {
        echo $this->color($c, $text);
    }
}
