<?php
declare(strict_types=1);
namespace Atom\Routing;

use Atom\Support\Regex;

final readonly class RouteCompiler
{
    private const DEFAULT_PATTERNS = [
        'id'   => '[0-9]+',
        'slug' => '[a-z0-9\-]+',
        'any'  => '[^/]+',
        'all'  => '.+',
    ];

    /**
     * @param Route[] $routes  список маршрутов с уже сохранёнными controller/action
     * @param array<string,string> $extraPatterns
     * @return array{regex:string, map:array<int, array{controller:string,action:string,name:string,middleware:array,route:Route}>}
     */
    public function compile(array $routes, array $extraPatterns = []): array
    {
        $patterns = self::DEFAULT_PATTERNS + $extraPatterns;
        $parts = [];
        $map   = [];
        $id    = 0;

        foreach ($routes as $route) {
            // Превращаем "/users/{id:\d+}" в "/users/(?<id>\d+)"
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

            // Экранируем слеши и собираем альтернативу с маркером (*:N)
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

        // Используем branch reset (?|...) чтобы именованные группы работали единообразно
        $regex = '#^(?|' . implode('|', $parts) . ')$#xs';
        Regex::assert($regex);

        return ['regex' => $regex, 'map' => $map];
    }
}
