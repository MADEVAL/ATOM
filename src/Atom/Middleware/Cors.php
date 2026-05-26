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
        private bool $allowCredentials = false,
        private string $exposeHeaders = '',
    ) {}

    public function handle(Request $req, \Closure $next): Response
    {
        if ($req->method === 'OPTIONS') {
            $res = (new Response('', StatusCode::NO_CONTENT))
                ->withHeader('Access-Control-Allow-Origin', $this->allowOrigin)
                ->withHeader('Access-Control-Allow-Methods', $this->allowMethods)
                ->withHeader('Access-Control-Allow-Headers', $this->allowHeaders)
                ->withHeader('Access-Control-Max-Age', '86400');
            if ($this->allowCredentials) {
                $res = $res->withHeader('Access-Control-Allow-Credentials', 'true');
            }
            if ($this->exposeHeaders !== '') {
                $res = $res->withHeader('Access-Control-Expose-Headers', $this->exposeHeaders);
            }
            return $res;
        }
        $res = $next($req)->withHeader('Access-Control-Allow-Origin', $this->allowOrigin);
        if ($this->allowCredentials) {
            $res = $res->withHeader('Access-Control-Allow-Credentials', 'true');
        }
        if ($this->exposeHeaders !== '') {
            $res = $res->withHeader('Access-Control-Expose-Headers', $this->exposeHeaders);
        }
        return $res;
    }
}
