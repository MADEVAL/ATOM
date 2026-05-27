<?php
declare(strict_types=1);
namespace Atom\Middleware;

use Atom\Http\{Request, Response};
use Atom\Container\Container;

final readonly class Pipeline
{
    public static function run(array $layers, Request $request, \Closure $core, Container $c): Response
    {
        $resolve = static fn(string|\Closure|MiddlewareInterface $m): string|\Closure|MiddlewareInterface => match (true) {
            $m instanceof \Closure     => $m,
            $m instanceof MiddlewareInterface => $m,
            is_string($m)              => $c->make($m),
            default => throw new \InvalidArgumentException(
                'Middleware must be a Closure, MiddlewareInterface, or class name'
            ),
        };
        $pipeline = array_reduce(
            array_reverse($layers),
            fn(\Closure $next, $m) => function (Request $req) use ($next, $m, $resolve): Response {
                $mw = $resolve($m);
                return $mw instanceof \Closure
                    ? $mw($req, $next)
                    : $mw->handle($req, $next);
            },
            fn(Request $req) => $core($req),
        );
        return $pipeline($request);
    }
}
