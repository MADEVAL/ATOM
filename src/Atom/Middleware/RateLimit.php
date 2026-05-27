<?php
declare(strict_types=1);
namespace Atom\Middleware;

use Atom\Http\{Request, Response, StatusCode};

final class RateLimit implements MiddlewareInterface
{
    private static array $store = [];

    public function __construct(
        private int $max = 60,
        private int $window = 60,
    ) {}

    public function handle(Request $req, \Closure $next): Response
    {
        $key = $req->ip . '_' . ($req->path ?? '/');
        $now = time();
        $windowStart = $now - $this->window;

        if (!isset(self::$store[$key])) {
            self::$store[$key] = [];
        }
        self::$store[$key] = array_filter(self::$store[$key], fn(int $t) => $t > $windowStart);
        self::$store[$key][] = $now;

        if (count(self::$store[$key]) > $this->max) {
            return new Response('Too Many Requests', StatusCode::TOO_MANY_REQUESTS);
        }
        return $next($req);
    }
}
