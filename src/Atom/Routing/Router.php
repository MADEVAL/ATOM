<?php
declare(strict_types=1);
namespace Atom\Routing;

use Atom\Http\{Request, Response, StatusCode};
use Atom\Container\Container;
use Atom\Middleware\Pipeline;
use Atom\Support\Regex;
use ReflectionClass, ReflectionMethod;

final class Router
{
    /** @var Route[] */
    private array $routes = [];
    private ?array $compiled = null;
    private string $cacheFile;
    private array $patterns = [];
    private array $groupMiddleware = [];
    private string $groupPrefix = '';

    public function __construct(
        private readonly Container $container,
        string $cacheDir = __DIR__ . '/../../storage/cache',
    ) {
        $this->cacheFile = $cacheDir . '/routes.php';
    }

    public function addPattern(string $name, string $regex): self
    {
        $this->patterns[$name] = $regex;
        $this->compiled = null;
        return $this;
    }

    public function group(string $prefix, array $middleware, callable $fn): self
    {
        $prevPrefix = $this->groupPrefix;
        $prevMw     = $this->groupMiddleware;
        $this->groupPrefix     = $prevPrefix . $prefix;
        $this->groupMiddleware = [...$prevMw, ...$middleware];
        $fn($this);
        $this->groupPrefix     = $prevPrefix;
        $this->groupMiddleware = $prevMw;
        return $this;
    }

    public function add(string $method, string $path, string $controllerAction, string $name = '', array $mw = []): self
    {
        [$controller, $action] = explode('@', $controllerAction, 2) + [1 => '__invoke'];
        $this->routes[] = new CompiledRoute(
            path:       $this->groupPrefix . $path,
            methods:    [strtoupper($method)],
            name:       $name,
            middleware: [...$this->groupMiddleware, ...$mw],
            controller: $controller,
            action:     $action,
        );
        $this->compiled = null;
        return $this;
    }

    public function get(string $p, string $h, string $n = '', array $mw = []): self   { return $this->add('GET', $p, $h, $n, $mw); }
    public function post(string $p, string $h, string $n = '', array $mw = []): self  { return $this->add('POST', $p, $h, $n, $mw); }
    public function put(string $p, string $h, string $n = '', array $mw = []): self   { return $this->add('PUT', $p, $h, $n, $mw); }
    public function patch(string $p, string $h, string $n = '', array $mw = []): self { return $this->add('PATCH', $p, $h, $n, $mw); }
    public function delete(string $p, string $h, string $n = '', array $mw = []): self { return $this->add('DELETE', $p, $h, $n, $mw); }
    public function any(string $p, string $h, string $n = '', array $mw = []): self   { return $this->match(['GET','POST','PUT','PATCH','DELETE','OPTIONS','HEAD'], $p, $h, $n, $mw); }
    /** @param string[] $methods */
    public function match(array $methods, string $p, string $h, string $n = '', array $mw = []): self
    {
        [$controller, $action] = explode('@', $h, 2) + [1 => '__invoke'];
        $this->routes[] = new CompiledRoute(
            path: $this->groupPrefix . $p, methods: array_map(strtoupper(...), $methods),
            name: $n, middleware: [...$this->groupMiddleware, ...$mw],
            controller: $controller, action: $action,
        );
        $this->compiled = null;
        return $this;
    }

    public function loadFromAttributes(string $directory): self
    {
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory)) as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') continue;
            $path = $file->getPathname();
            $class = $this->classFromFile($path);
            if ($class === null) continue;
            require_once $path;
            $ref = new ReflectionClass($class);
            foreach ($ref->getMethods(ReflectionMethod::IS_PUBLIC) as $m) {
                foreach ($m->getAttributes(Route::class) as $attr) {
                    /** @var Route $r */
                    $r = $attr->newInstance();
                    $this->routes[] = new CompiledRoute(
                        path: $r->path, methods: $r->methods, name: $r->name,
                        middleware: $r->middleware, controller: $class, action: $m->getName(),
                    );
                }
            }
        }
        $this->compiled = null;
        return $this;
    }

    public function dispatch(Request $request): Response
    {
        $compiled = $this->getCompiled();
        if (empty($compiled['map'])) {
            return new Response('Not Found', StatusCode::NOT_FOUND);
        }
        $uri = strtok($request->uri, '?') ?: '/';

        $m = Regex::match($compiled['regex'], $request->method . $uri);
        if ($m === null) {
            return new Response('Not Found', StatusCode::NOT_FOUND);
        }

        $id    = (int) $m['MARK'];
        $meta  = $compiled['map'][$id];
        $named = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
        unset($named['METHOD'], $named['MARK']);

        $controller = $this->container->make($meta['controller']);
        $action     = $meta['action'];

        $ref = new ReflectionMethod($controller, $action);
        $hasRequest = array_any($ref->getParameters(), fn($p) => $p->getName() === 'request');
        $params = $hasRequest ? [...$named, 'request' => $request] : $named;

        $handler = fn() => $controller->{$action}(...$params)
            |> (fn($r) => $r instanceof Response ? $r : Response::html((string) $r));

        return Pipeline::run($meta['middleware'], $request, $handler, $this->container);
    }

    public function url(string $name, array $params = []): string
    {
        $route = array_find($this->routes, fn($r) => $r->name === $name)
            ?? throw new \InvalidArgumentException("Route '{$name}' not found");

        return Regex::replace(
            '#\{([a-zA-Z_][a-zA-Z0-9_]*)(?::[^}]+)?\}#',
            fn($m) => (string) ($params[$m[1]] ?? throw new \InvalidArgumentException("Missing param {$m[1]}")),
            $route->path,
        );
    }

    private function getCompiled(): array
    {
        if ($this->compiled !== null) return $this->compiled;
        if (is_file($this->cacheFile)) {
            $cached = require $this->cacheFile;
            if (is_array($cached) && isset($cached['regex'], $cached['map'])) {
                return $this->compiled = $cached;
            }
        }
        $this->compiled = (new RouteCompiler())->compile($this->routes, $this->patterns);
        $export = $this->compiled;
        // Strip route objects from map — not serializable, not needed for dispatch
        foreach ($export['map'] as &$entry) unset($entry['route']);
        @file_put_contents(
            $this->cacheFile,
            "<?php\nreturn " . var_export($export, true) . ";\n",
        );
        return $this->compiled;
    }

    private function classFromFile(string $file): ?string
    {
        $code = file_get_contents($file);
        $nsMatch = Regex::match('#^\s*namespace\s+([^;]+);#m', $code);
        $ns = $nsMatch !== null ? $nsMatch[1] : '';
        $clsMatch = Regex::match('#class\s+([A-Za-z_][A-Za-z0-9_]*)#', $code);
        $cls = $clsMatch !== null ? $clsMatch[1] : null;
        return $cls !== null ? ($ns !== '' ? $ns . '\\' . $cls : $cls) : null;
    }
}

