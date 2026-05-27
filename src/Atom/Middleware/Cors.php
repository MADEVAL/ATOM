<?php
declare(strict_types=1);
namespace Atom\Middleware;

use Atom\Constants;
use Atom\Http\{Request, Response, StatusCode};

final readonly class Cors implements MiddlewareInterface
{
    public function __construct(
        private string $allowOrigin = '*',
        private string $allowMethods = 'GET,POST,PUT,PATCH,DELETE,OPTIONS',
        private string $allowHeaders = 'Content-Type,Authorization',
        private bool $allowCredentials = false,
        private string $exposeHeaders = '',
    ) {
        if ($this->allowOrigin === '*' && $this->allowCredentials) {
            throw new \InvalidArgumentException(
                'Cannot use allowOrigin=* with allowCredentials=true per CORS specification'
            );
        }
    }

    public function handle(Request $req, \Closure $next): Response
    {
        $origin = $this->allowOrigin;
        $requestOrigin = $req->header('Origin');
        $reflected = false;
        if ($origin === '*') {
            $origin = $requestOrigin !== '' ? $requestOrigin : '*';
            $reflected = $requestOrigin !== '';
        }

        if ($req->method === 'OPTIONS') {
            $res = (new Response('', StatusCode::NO_CONTENT))
                ->withHeader('Access-Control-Allow-Origin', $origin)
                ->withHeader('Access-Control-Allow-Methods', $this->allowMethods)
                ->withHeader('Access-Control-Allow-Headers', $this->allowHeaders)
                ->withHeader('Access-Control-Max-Age', (string) Constants::CORS_MAX_AGE);
            if ($this->allowCredentials) {
                $res = $res->withHeader('Access-Control-Allow-Credentials', 'true');
            }
            if ($this->exposeHeaders !== '') {
                $res = $res->withHeader('Access-Control-Expose-Headers', $this->exposeHeaders);
            }
            return $reflected ? $res->withHeader('Vary', 'Origin') : $res;
        }
        $res = $next($req)->withHeader('Access-Control-Allow-Origin', $origin);
        if ($this->allowCredentials) {
            $res = $res->withHeader('Access-Control-Allow-Credentials', 'true');
        }
        if ($this->exposeHeaders !== '') {
            $res = $res->withHeader('Access-Control-Expose-Headers', $this->exposeHeaders);
        }
        if ($reflected) {
            $res = $res->withHeader('Vary', 'Origin');
        }
        return $res;
    }
}
