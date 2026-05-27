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

        $count = count(self::$store[$key]);
        if ($count > $this->max) {
            $retryAfter = $windowStart + $this->window - $now + 1;
            return (new Response('Too Many Requests', StatusCode::TOO_MANY_REQUESTS))
                ->withHeader('Retry-After', (string) max(1, $retryAfter));
        }

        if ($count === 1 && count(self::$store) > \Atom\Constants::RATELIMIT_CLEANUP_THRESHOLD) {
            self::$store = array_filter(self::$store, fn(array $t) => $t !== []);
        }

        $remaining = $this->max - $count;
        $res = $next($req);
        return $res
            ->withHeader('X-RateLimit-Limit', (string) $this->max)
            ->withHeader('X-RateLimit-Remaining', (string) $remaining);
    }
}
