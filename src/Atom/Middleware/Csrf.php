<?php
declare(strict_types=1);
namespace Atom\Middleware;

use Atom\Http\{Request, Response, Session, StatusCode};

final readonly class Csrf implements MiddlewareInterface
{
    public function __construct(private Session $session) {}

    public function handle(Request $req, \Closure $next): Response
    {
        if (in_array($req->method, ['POST','PUT','PATCH','DELETE'], true)) {
            $token = $req->body['_csrf'] ?? $req->header('X-CSRF-Token');
            if (!is_string($token) || !$this->session->validateCsrf($token)) {
                return new Response('Invalid CSRF token', StatusCode::FORBIDDEN);
            }
        }
        return $next($req);
    }
}
