<?php
declare(strict_types=1);
namespace Atom\Middleware;

use Atom\Http\{Request, Response};

interface MiddlewareInterface
{
    public function handle(Request $request, \Closure $next): Response;
}
