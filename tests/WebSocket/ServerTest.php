<?php
declare(strict_types=1);
namespace Atom\Tests\WebSocket;

use Atom\Application;
use Atom\Config;
use Atom\WebSocket\Connection;
use Atom\WebSocket\Server;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(Server::class)]
final class ServerTest extends TestCase
{
    private Application $app;
    private string $tmpCache;

    protected function setUp(): void
    {
        $this->tmpCache = sys_get_temp_dir() . '/atom_ws_' . uniqid();
        @mkdir($this->tmpCache, 0777, true);
        $this->app = new Application(new Config(cacheDir: $this->tmpCache));
    }

    protected function tearDown(): void
    {
        foreach ((array) glob($this->tmpCache . '/*') as $f) @unlink($f);
        if (is_dir($this->tmpCache)) rmdir($this->tmpCache);
    }

    #[Test]
    public function ws_registration_via_app(): void
    {
        $handler = fn() => null;
        $this->app->ws('/chat', $handler);
        $server = $this->app->wsServer();
        $this->assertNotNull($server);
        $routes = $server->routes();
        $this->assertArrayHasKey('/chat', $routes);
        $this->assertSame($handler, $routes['/chat']);
    }

    #[Test]
    public function ws_returns_app_for_fluent_chaining(): void
    {
        $result = $this->app->ws('/chat', fn() => null);
        $this->assertSame($this->app, $result);
    }

    #[Test]
    public function ws_server_is_singleton(): void
    {
        $this->app->ws('/a', fn() => null);
        $first = $this->app->wsServer();
        $this->app->ws('/b', fn() => null);
        $second = $this->app->wsServer();
        $this->assertSame($first, $second);
        $this->assertCount(2, $first->routes());
        $this->assertArrayHasKey('/a', $first->routes());
        $this->assertArrayHasKey('/b', $first->routes());
    }

    #[Test]
    public function ws_server_null_when_no_routes(): void
    {
        $this->assertNull($this->app->wsServer());
    }

    #[Test]
    public function routes_exposes_all_registered_handlers(): void
    {
        $fn1 = fn() => 'one';
        $fn2 = fn() => 'two';
        $this->app->ws('/first', $fn1);
        $this->app->ws('/second/{id}', $fn2);
        $routes = $this->app->wsServer()->routes();
        $this->assertCount(2, $routes);
        $this->assertSame($fn1, $routes['/first']);
        $this->assertSame($fn2, $routes['/second/{id}']);
    }

    // ──────────── Room management (requires resolving private methods) ────────────

    private function makeTestConn(): Connection
    {
        $server = @stream_socket_server('tcp://127.0.0.1:19998');
        if ($server === false) {
            $this->markTestSkipped('Could not create test socket');
        }
        $client = @stream_socket_client('tcp://127.0.0.1:19998');
        $accepted = @stream_socket_accept($server);
        fclose($server);
        return new Connection($accepted);
    }

    #[Test]
    public function join_and_leave_room(): void
    {
        $conn = $this->makeTestConn();
        $this->app->ws('/chat/{room}', fn() => null);
        $server = $this->app->wsServer();

        $this->assertCount(0, $server->room('test-room'));
        $server->join('test-room', $conn);
        $this->assertCount(1, $server->room('test-room'));
        $server->leave('test-room', $conn);
        $this->assertCount(0, $server->room('test-room'));
    }

    #[Test]
    public function broadcast_json_sends_to_all(): void
    {
        $conn = $this->makeTestConn();
        $this->app->ws('/broadcast', fn() => null);
        $server = $this->app->wsServer();
        $server->join('lobby', $conn);

        ob_start();
        $server->broadcastJson(['type' => 'announce'], $server->room('lobby'));
        ob_end_clean();
        $this->assertTrue($conn->isOpen());
    }

    #[Test]
    public function send_json_to_room(): void
    {
        $conn = $this->makeTestConn();
        $this->app->ws('/updates', fn() => null);
        $server = $this->app->wsServer();
        $server->join('room-a', $conn);

        ob_start();
        $server->sendJsonToRoom('room-a', ['event' => 'update']);
        ob_end_clean();
        $this->assertTrue($conn->isOpen());
    }

    #[Test]
    public function leave_nonexistent_room_is_safe(): void
    {
        $conn = $this->makeTestConn();
        $this->app->ws('/safe', fn() => null);
        $server = $this->app->wsServer();
        $server->leave('nonexistent', $conn);
        $this->assertCount(0, $server->room('nonexistent'));
    }

    #[Test]
    public function room_returns_empty_for_unknown(): void
    {
        $this->app->ws('/x', fn() => null);
        $this->assertCount(0, $this->app->wsServer()->room('unknown'));
    }

    #[Test]
    public function multiple_connections_in_same_room(): void
    {
        $c1 = $this->makeTestConn();
        $c2 = $this->makeTestConn();
        $this->app->ws('/room', fn() => null);
        $server = $this->app->wsServer();
        $server->join('shared', $c1);
        $server->join('shared', $c2);
        $this->assertCount(2, $server->room('shared'));
    }
}
