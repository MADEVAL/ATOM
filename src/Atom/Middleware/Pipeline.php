<?php
declare(strict_types=1);
namespace Atom\Middleware;

use Atom\Http\{Request, Response};
use Atom\Container\Container;

interface MiddlewareInterface
{
    public function handle(Request $request, \Closure $next): Response;
}

final readonly class Pipeline
{
    public static function run(array $layers, Request $request, \Closure $core, Container $c): Response
    {
        $resolve = static fn(string|\Closure|MiddlewareInterface $m) => match (true) {
            $m instanceof \Closure     => $m,
            $m instanceof MiddlewareInterface => $m,
            is_string($m)              => $c->make($m),
        };
        $pipeline = array_reduce(
            array_reverse($layers),
            fn(\Closure $next, $m) =>
                fn(Request $req) => $resolve($m) instanceof \Closure
                    ? $resolve($m)($req, $next)
                    : $resolve($m)->handle($req, $next),
            fn(Request $req) => $core($req),
        );
        return $pipeline($request);
    }
}
