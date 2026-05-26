<?php
declare(strict_types=1);
namespace Atom\Middleware;

use Atom\Http\{Request, Response, StatusCode};

final readonly class Cors implements MiddlewareInterface
{
    public function __construct(
        private string $allowOrigin = '',
        private string $allowMethods = 'GET,POST,PUT,PATCH,DELETE,OPTIONS',
        private string $allowHeaders = 'Content-Type,Authorization',
    ) {}

    public function handle(Request $req, \Closure $next): Response
    {
        if ($req->method === 'OPTIONS') {
            return (new Response('', StatusCode::NO_CONTENT))
                ->withHeader('Access-Control-Allow-Origin', $this->allowOrigin)
                ->withHeader('Access-Control-Allow-Methods', $this->allowMethods)
                ->withHeader('Access-Control-Allow-Headers', $this->allowHeaders)
                ->withHeader('Access-Control-Max-Age', '86400');
        }
        return $next($req)->withHeader('Access-Control-Allow-Origin', $this->allowOrigin);
    }
}
