<?php
declare(strict_types=1);
namespace Atom\Routing;

use Atom\Http\{Request, Response, StatusCode};
use Atom\Cache\Cache;
use Atom\Constants;
use Atom\Container\Container;
use Atom\Middleware\Pipeline;
use Atom\Support\Regex;
use ReflectionClass, ReflectionMethod;

final class Router
{
    private const CACHE_VERSION = 2;

    /** @var CompiledRoute[] */
    private array $routes = [];
    private array $namedRoutes = [];
    private ?array $compiled = null;
    private string $cacheFile;
    private array $patterns = [];
    private array $groupMiddleware = [];
    private string $groupPrefix = '';
    private string $groupNamePrefix = '';
    private ?Cache $cache = null;
    private string $cacheStrategy = 'file';

    public function __construct(
        private readonly Container $container,
        string $cacheDir = __DIR__ . '/../../storage/cache',
        ?Cache $cache = null,
        string $cacheStrategy = 'file',
    ) {
        $this->cacheFile = $cacheDir . '/routes.php';
        $this->cache = $cache;
        $this->cacheStrategy = $cacheStrategy;
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
        $key = '__health_' . sha1($path);
        $this->container->singleton($key, fn() => $checks);
        $this->container->bind($key . '_ctrl', fn() => new class($this->container, $key) {
            public function __construct(private Container $c, private string $k) {}
            public function check(): Response {
                $fn = $this->c->make($this->k);
                $result = ($fn)();
                $allOk = !array_any($result, fn(mixed $v) => $v !== true);
                return Response::json($result, $allOk ? StatusCode::OK : StatusCode::SERVICE_UNAVAILABLE);
            }
        });
        return $this->get($path, $key . '_ctrl@check');
    }

    public function loadFromAttributes(string $directory): self
    {
        try {
            $iter = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));
        } catch (\UnexpectedValueException) {
            return $this;
        }
        foreach ($iter as $file) {
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

        $handler = function () use ($controller, $action, $params): Response {
            $result = $controller->{$action}(...$params);
            return $result instanceof Response ? $result : Response::html((string) $result);
        };

        return Pipeline::run($meta['middleware'], $request, $handler, $this->container);
    }

    /** @return list<string> */
    private function getAllowedMethods(string $uri): array
    {
        $methods = [];
        foreach ($this->routes as $route) {
            $regex = '#' . $this->patternToRegex($route->path) . '#';
            if (Regex::match($regex, $uri) !== null) {
                foreach ($route->methods as $method) {
                    $methods[$method] = true;
                }
            }
        }
        return array_keys($methods);
    }

    private function patternToRegex(string $path): string
    {
        $result = '';
        $offset = 0;
        while (preg_match('#\{([a-zA-Z_][a-zA-Z0-9_]*)(?::([^}]+))?\}#', $path, $m, PREG_OFFSET_CAPTURE, $offset)) {
            $result .= Regex::quote(substr($path, $offset, $m[0][1] - $offset));
            $custom = $m[2][0] ?? '';
            $pattern = $custom !== '' ? $custom : ($this->patterns[$m[1][0]] ?? '[^/]+');
            $result .= '(' . $pattern . ')';
            $offset = $m[0][1] + strlen($m[0][0]);
        }
        $result .= Regex::quote(substr($path, $offset));
        return $result;
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

    /** @return array{regex:string, map:array<int,array{controller:string,action:string,name:string,middleware:array,route:CompiledRoute}>, altRegex:string} */
    private function getCompiled(): array
    {
        if ($this->compiled !== null) return $this->compiled;

        $signature = $this->signature();
        if ($this->cacheStrategy === 'cache' && $this->cache !== null) {
            return $this->compiled = $this->cache->remember(
                'routes_compiled:' . $signature,
                fn() => $this->compileRoutes(),
                0,
            );
        }

        if (is_file($this->cacheFile)) {
            try {
                $cached = require $this->cacheFile;
            } catch (\ParseError) {
                unlink($this->cacheFile);
                $cached = null;
            }
            if (
                is_array($cached)
                && ($cached['version'] ?? null) === self::CACHE_VERSION
                && ($cached['signature'] ?? null) === $signature
                && isset($cached['regex'], $cached['map'], $cached['altRegex'])
            ) {
                unset($cached['version'], $cached['signature']);
                return $this->compiled = $cached;
            }
        }
        $this->compiled = $this->compileRoutes();
        $export = ['version' => self::CACHE_VERSION, 'signature' => $signature, ...$this->compiled];
        foreach ($export['map'] as &$entry) unset($entry['route']);
        unset($entry);
        $dir = dirname($this->cacheFile);
        if (!is_dir($dir) && !@mkdir($dir, Constants::DIR_PERMISSIONS, true) && !is_dir($dir)) {
            return $this->compiled;
        }
        $tmp = $this->cacheFile . '.' . bin2hex(random_bytes(8)) . '.tmp';
        if (file_put_contents($tmp, "<?php\nreturn " . var_export($export, true) . ";\n", LOCK_EX) !== false) {
            @rename($tmp, $this->cacheFile);
        } elseif (is_file($tmp)) {
            @unlink($tmp);
        }
        return $this->compiled;
    }

    private function signature(): string
    {
        $routes = array_map(
            fn(CompiledRoute $r) => [
                'path' => $r->path,
                'methods' => $r->methods,
                'name' => $r->name,
                'middleware' => array_map(
                    fn(mixed $m) => is_string($m) ? $m : (is_object($m) ? $m::class : get_debug_type($m)),
                    $r->middleware,
                ),
                'controller' => $r->controller,
                'action' => $r->action,
            ],
            $this->routes,
        );

        return sha1(serialize([self::CACHE_VERSION, $routes, $this->patterns]));
    }

    private function compileRoutes(): array
    {
        $compiled = (new RouteCompiler())->compile($this->routes, $this->patterns);
        $compiled['altRegex'] = Regex::replace(
            '~\(\?<METHOD>[^)]+\)~',
            '(?<METHOD>\w+)',
            $compiled['regex'],
        );
        foreach ($compiled['map'] as &$entry) {
            $entry['methods'] = $entry['route']->methods;
        }
        unset($entry);
        return $compiled;
    }

    /** @return ?string Extracts fully-qualified class name from a PHP file */
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
