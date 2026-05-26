<?php
declare(strict_types=1);
namespace Atom\Tests\Middleware;

use Atom\Container\Container;
use Atom\Http\{Request, Response, Session};
use Atom\Middleware\Csrf;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(Csrf::class)]
final class CsrfTest extends TestCase
{
    private Session $session;

    protected function setUp(): void
    {
        $_SESSION = [];
        $this->session = new Session();
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    #[Test]
    public function blocks_post_without_token(): void
    {
        $csrf = new Csrf($this->session);
        $req = new Request(server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/']);
        $response = $csrf->handle($req, fn() => new Response('ok'));
        $this->assertSame(403, $response->getStatusCode());
    }

    #[Test]
    public function allows_get_without_token(): void
    {
        $csrf = new Csrf($this->session);
        $req = new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/']);
        $response = $csrf->handle($req, fn() => new Response('ok'));
        $this->assertSame('ok', $response->getContent());
    }

    #[Test]
    public function allows_post_with_valid_token_in_body(): void
    {
        $token = $this->session->csrfToken();
        $csrf = new Csrf($this->session);
        $req = new Request(body: ['_csrf' => $token], server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/']);
        $response = $csrf->handle($req, fn() => new Response('ok'));
        $this->assertSame('ok', $response->getContent());
    }

    #[Test]
    public function blocks_post_with_invalid_token(): void
    {
        $this->session->csrfToken(); // generate
        $csrf = new Csrf($this->session);
        $req = new Request(body: ['_csrf' => 'wrong'], server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/']);
        $response = $csrf->handle($req, fn() => new Response('ok'));
        $this->assertSame(403, $response->getStatusCode());
    }

    #[Test]
    public function allows_valid_token_in_header(): void
    {
        $token = $this->session->csrfToken();
        $csrf = new Csrf($this->session);
        $req = new Request(server: [
            'REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/',
            'HTTP_X_CSRF_TOKEN' => $token,
        ]);
        $response = $csrf->handle($req, fn() => new Response('ok'));
        $this->assertSame('ok', $response->getContent());
    }
}
