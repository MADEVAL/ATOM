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
    private array $namedRoutes = [];
    private ?array $compiled = null;
    private string $cacheFile;
    private array $patterns = [];
    private array $groupMiddleware = [];
    private string $groupPrefix = '';
    private string $groupNamePrefix = '';

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
        $prevPrefix     = $this->groupPrefix;
        $prevMw         = $this->groupMiddleware;
        $prevNamePrefix = $this->groupNamePrefix;
        $this->groupPrefix     = $prevPrefix . $prefix;
        $this->groupMiddleware = [...$prevMw, ...$middleware];
        $this->groupNamePrefix = $prevNamePrefix;
        $fn($this);
        $this->groupPrefix     = $prevPrefix;
        $this->groupMiddleware = $prevMw;
        $this->groupNamePrefix = $prevNamePrefix;
        return $this;
    }

    public function namePrefix(string $prefix, callable $fn): self
    {
        $prev = $this->groupNamePrefix;
        $this->groupNamePrefix = $prev . $prefix;
        $fn($this);
        $this->groupNamePrefix = $prev;
        return $this;
    }

    public function add(string $method, string $path, string $controllerAction, string $name = '', array $mw = []): self
    {
        $fullName = $this->groupNamePrefix . $name;
        if ($name !== '' && isset($this->namedRoutes[$fullName])) {
            throw new \InvalidArgumentException("Duplicate route name: {$fullName}");
        }
        [$controller, $action] = explode('@', $controllerAction, 2) + [1 => '__invoke'];
        $this->routes[] = new CompiledRoute(
            path:       $this->groupPrefix . $path,
            methods:    [strtoupper($method)],
            name:       $fullName,
            middleware: [...$this->groupMiddleware, ...$mw],
            controller: $controller,
            action:     $action,
        );
        if ($name !== '') {
            $this->namedRoutes[$fullName] = end($this->routes);
        }
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
        if ($methods === []) {
            throw new \InvalidArgumentException('match() requires at least one HTTP method');
        }
        $fullName = $this->groupNamePrefix . $n;
        if ($n !== '' && isset($this->namedRoutes[$fullName])) {
            throw new \InvalidArgumentException("Duplicate route name: {$fullName}");
        }
        [$controller, $action] = explode('@', $h, 2) + [1 => '__invoke'];
        $this->routes[] = new CompiledRoute(
            path: $this->groupPrefix . $p, methods: array_map(strtoupper(...), $methods),
            name: $fullName, middleware: [...$this->groupMiddleware, ...$mw],
            controller: $controller, action: $action,
        );
        if ($n !== '') {
            $this->namedRoutes[$fullName] = end($this->routes);
        }
        $this->compiled = null;
        return $this;
    }

    public function clearCache(): void
    {
        if (is_file($this->cacheFile)) unlink($this->cacheFile);
        $this->compiled = null;
    }

    /** @return CompiledRoute[] */
    public function routes(): array
    {
        return $this->routes;
    }

    public function health(string $path, callable $checks): self
    {
        $this->container->singleton('__health_checks', fn() => $checks);
        $this->container->bind('__health_controller', fn() => new class($this->container) {
            public function __construct(private \Atom\Container\Container $c) {}
            public function check(): \Atom\Http\Response {
                $fn = $this->c->make('__health_checks');
                $result = ($fn)();
                $allOk = !in_array(false, $result, true);
                return \Atom\Http\Response::json($result, $allOk ? \Atom\Http\StatusCode::OK : \Atom\Http\StatusCode::SERVICE_UNAVAILABLE);
            }
        });
        return $this->get($path, '__health_controller@check');
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
                    if ($r->name !== '') {
                        if (isset($this->namedRoutes[$r->name])) {
                            throw new \InvalidArgumentException("Duplicate route name from attribute: {$r->name}");
                        }
                        $this->namedRoutes[$r->name] = end($this->routes);
                    }
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
        $uri = parse_url($request->uri, PHP_URL_PATH) ?: '/';

        $m = Regex::match($compiled['regex'], $request->method . $uri);
        if ($m === null) {
            $allowed = $this->getAllowedMethods($uri);
            if ($allowed !== []) {
                return (new Response('Method Not Allowed', StatusCode::METHOD_NOT_ALLOWED))
                    ->withHeader('Allow', implode(', ', $allowed));
            }
            return new Response('Not Found', StatusCode::NOT_FOUND);
        }

        $id    = (int) $m['MARK'];
        $meta  = $compiled['map'][$id];
        $named = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
        unset($named['METHOD'], $named['MARK']);

        $controller = $this->container->make($meta['controller']);
        $action     = $meta['action'];

        $ref = new ReflectionMethod($controller, $action);
        if (!$ref->isPublic()) {
            return new Response('Internal Server Error', StatusCode::SERVER_ERROR);
        }
        $hasRequest = array_any($ref->getParameters(), fn($p) => $p->getName() === 'request');
        $params = $hasRequest ? ['request' => $request, ...$named] : $named;

        $handler = fn() => $controller->{$action}(...$params)
            |> (fn($r) => $r instanceof Response ? $r : Response::html((string) $r));

        return Pipeline::run($meta['middleware'], $request, $handler, $this->container);
    }

    private function getPatternForParam(string $name, string $customPattern = ''): string
    {
        if ($customPattern !== '') return $customPattern;
        return $this->patterns[$name] ?? RouteCompiler::DEFAULT_PATTERNS[$name] ?? '[^/]+';
    }

    private function getAllowedMethods(string $uri): array
    {
        $compiled = $this->getCompiled();
        if (empty($compiled['altRegex'])) {
            return [];
        }
        $m = Regex::match($compiled['altRegex'], 'GET' . $uri);
        if ($m !== null) {
            $id = (int) $m['MARK'];
            return $compiled['map'][$id]['methods'] ?? [];
        }
        return [];
    }

    public function url(string $name, array $params = []): string
    {
        $route = $this->namedRoutes[$name]
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
            try {
                $cached = require $this->cacheFile;
            } catch (\ParseError) {
                unlink($this->cacheFile);
                $cached = null;
            }
            if (is_array($cached) && isset($cached['regex'], $cached['map'], $cached['altRegex'])) {
                return $this->compiled = $cached;
            }
        }
        $this->compiled = (new RouteCompiler())->compile($this->routes, $this->patterns);
        $this->compiled['altRegex'] = Regex::replace(
            '~\(\?<METHOD>[^)]+\)~',
            '(?<METHOD>\w+)',
            $this->compiled['regex'],
        );
        foreach ($this->compiled['map'] as &$entry) {
            $entry['methods'] = $entry['route']->methods;
        }
        unset($entry);
        $export = $this->compiled;
        foreach ($export['map'] as &$entry) unset($entry['route']);
        unset($entry);
        $dir = dirname($this->cacheFile);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            return $this->compiled;
        }
        file_put_contents(
            $this->cacheFile,
            "<?php\nreturn " . var_export($export, true) . ";\n",
        );
        return $this->compiled;
    }

    private function classFromFile(string $file): ?string
    {
        $code = file_get_contents($file);
        if ($code === false) return null;
        $nsMatch = Regex::match('#^\s*namespace\s+([^;]+);#m', $code);
        $ns = $nsMatch !== null ? $nsMatch[1] : '';
        $clsMatch = Regex::match('#class\s+([A-Za-z_][A-Za-z0-9_]*)#', $code);
        $cls = $clsMatch !== null ? $clsMatch[1] : null;
        return $cls !== null ? ($ns !== '' ? $ns . '\\' . $cls : $cls) : null;
    }
}

