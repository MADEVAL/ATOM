<?php
declare(strict_types=1);
namespace Atom\Middleware;

use Atom\Http\{Request, Response};
use Atom\Container\Container;

final readonly class Pipeline
{
    /** @param list<string|\Closure|MiddlewareInterface> $layers */
    public static function run(array $layers, Request $request, \Closure $core, Container $c): Response
    {
        $resolve = static function (string|\Closure|MiddlewareInterface $m) use ($c): \Closure|MiddlewareInterface {
            if ($m instanceof \Closure || $m instanceof MiddlewareInterface) {
                return $m;
            }
            $resolved = $c->make($m);
            if (!$resolved instanceof MiddlewareInterface) {
                throw new \InvalidArgumentException('Middleware class must implement MiddlewareInterface');
            }
            return $resolved;
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
