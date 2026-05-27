<?php
declare(strict_types=1);
namespace Atom\Tests\Middleware;

use Atom\Http\{Request, Response};
use Atom\Middleware\RateLimit;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(RateLimit::class)]
final class RateLimitTest extends TestCase
{
    #[Test]
    public function allows_requests_within_limit(): void
    {
        $rl = new RateLimit(max: 5, window: 60);
        $req = new Request(server: ['REMOTE_ADDR' => '1.1.1.1', 'REQUEST_URI' => '/api']);
        $next = fn() => new Response('ok');
        $res = $rl->handle($req, $next);
        $this->assertSame(200, $res->getStatusCode());
    }

    #[Test]
    public function blocks_when_limit_exceeded(): void
    {
        $rl = new RateLimit(max: 2, window: 60);
        $next = fn() => new Response('ok');
        for ($i = 0; $i < 3; $i++) {
            $req = new Request(server: ['REMOTE_ADDR' => '2.2.2.2', 'REQUEST_URI' => '/api']);
            $res = $rl->handle($req, $next);
        }
        $req = new Request(server: ['REMOTE_ADDR' => '2.2.2.2', 'REQUEST_URI' => '/api']);
        $res = $rl->handle($req, $next);
        $this->assertSame(429, $res->getStatusCode());
    }

    #[Test]
    public function separate_ips_have_separate_limits(): void
    {
        $rl = new RateLimit(max: 1, window: 60);
        $next = fn() => new Response('ok');
        $r1 = new Request(server: ['REMOTE_ADDR' => '10.0.0.1', 'REQUEST_URI' => '/']);
        $r2 = new Request(server: ['REMOTE_ADDR' => '10.0.0.2', 'REQUEST_URI' => '/']);
        $rl->handle($r1, $next);
        $rl->handle($r1, $next);
        $res = $rl->handle($r2, $next);
        $this->assertSame(200, $res->getStatusCode());
    }
}
