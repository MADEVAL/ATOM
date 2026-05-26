<?php
declare(strict_types=1);
namespace Atom\Routing;

use Atom\Support\Regex;

final readonly class RouteCompiler
{
    public const DEFAULT_PATTERNS = [
        'id'   => '[0-9]+',
        'slug' => '[a-z0-9\-]+',
        'any'  => '[^/]+',
        'all'  => '.+',
    ];

    /**
     * @param CompiledRoute[] $routes
     * @param array<string,string> $extraPatterns
     * @return array{regex:string, map:array}
     */
    public function compile(array $routes, array $extraPatterns = []): array
    {
        $patterns = array_merge(self::DEFAULT_PATTERNS, $extraPatterns);
        $parts = [];
        $map   = [];
        $id    = 0;

        foreach ($routes as $route) {
            // Split path into literal segments and parameter placeholders, quote literals
            $compiled = Regex::replace(
                '#\{([a-zA-Z_][a-zA-Z0-9_]*)(?::([^}]+))?\}#',
                function (array $m) use ($patterns): string {
                    $name = $m[1];
                    $custom = $m[2] ?? '';
                    $pattern = $custom !== '' ? $custom : ($patterns[$name] ?? '[^/]+');
                    return "(?<{$name}>{$pattern})";
                },
                $route->path,
            );
            // Escape PCRE meta-chars in literal parts (outside (?<name>...) groups)
            $segments = Regex::split('#(\(\?<[^>]+>[^)]*\)|\(\?:)#', $compiled, PREG_SPLIT_DELIM_CAPTURE);
            $compiled = implode(array_map(
                fn(string $s, int $i): string => $i % 2 === 0 ? Regex::quote($s) : $s,
                $segments,
                array_keys($segments),
            ));
            // Escape slashes
            $regex = Regex::replace('#(?<!\\\\)/#', '\\/', $compiled);
            foreach ($route->methods as $method) {
                $parts[] = "(?<METHOD>{$method}){$regex}(*:{$id})";
                $map[$id] = [
                    'controller' => $route->controller ?? '',
                    'action'     => $route->action ?? '',
                    'name'       => $route->name,
                    'middleware' => $route->middleware,
                    'route'      => $route,
                ];
                $id++;
            }
        }

        $regex = '#^(?|' . implode('|', $parts) . ')$#xs';
        try {
            Regex::assert($regex);
        } catch (\InvalidArgumentException $e) {
            throw new \InvalidArgumentException("Bad compiled route regex (check your custom parameter patterns or paths): {$e->getMessage()}", 0, $e);
        }

        return ['regex' => $regex, 'map' => $map];
    }
}
