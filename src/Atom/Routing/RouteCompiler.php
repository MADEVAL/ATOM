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
            $regex = self::compilePath($route->path, $patterns);
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

    /**
     * @param array<string,string> $patterns
     */
    private static function compilePath(string $path, array $patterns): string
    {
        $result = '';
        $offset = 0;

        while (preg_match(
            '#\{([a-zA-Z_][a-zA-Z0-9_]*)(?::([^}]+))?\}#',
            $path,
            $matches,
            PREG_OFFSET_CAPTURE,
            $offset,
        )) {
            $matchPos = $matches[0][1];
            $matchLen = strlen($matches[0][0]);

            $result .= Regex::quote(substr($path, $offset, $matchPos - $offset));

            $name = $matches[1][0];
            $custom = $matches[2][0] ?? '';
            $pattern = $custom !== '' ? $custom : ($patterns[$name] ?? '[^/]+');
            $result .= "(?<{$name}>{$pattern})";

            $offset = $matchPos + $matchLen;
        }

        $result .= Regex::quote(substr($path, $offset));
        return Regex::replace('#(?<!\\\\)/#', '\\/', $result);
    }
}
