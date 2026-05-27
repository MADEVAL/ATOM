<?php
declare(strict_types=1);
namespace Atom\Tests\WebSocket;

use Atom\WebSocket\Connection;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(Connection::class)]
final class ConnectionTest extends TestCase
{
    private int $pipePort = 0;

    /** @return array{resource,resource} */
    private function makePipe(): array
    {
        $port = $this->pipePort > 0 ? $this->pipePort : 19999;
        for ($attempt = 0; $attempt < 50; $attempt++) {
            $server = @stream_socket_server("tcp://127.0.0.1:{$port}");
            if ($server !== false) {
                $this->pipePort = $port;
                $client = @stream_socket_client("tcp://127.0.0.1:{$port}");
                if ($client === false) {
                    fclose($server);
                    $port++;
                    continue;
                }
                $accepted = @stream_socket_accept($server);
                fclose($server);
                if ($accepted === false) {
                    fclose($client);
                    $port++;
                    continue;
                }
                return [$accepted, $client];
            }
            $port++;
        }
        $this->markTestSkipped('Could not create TCP pipe');
    }

    private function newPipeConn(): Connection
    {
        [$local] = $this->makePipe();
        return new Connection($local);
    }

    // ──────────── Handshake ────────────

    #[Test]
    public function generate_accept_key_follows_rfc6455(): void
    {
        $key = 'dGhlIHNhbXBsZSBub25jZQ==';
        $accept = Connection::generateAcceptKey($key);
        $this->assertSame('s3pPLMBiTxaQ9kYGzzhZRbK+xOo=', $accept);
    }

    #[Test]
    public function accept_key_is_base64_sha1_20_bytes(): void
    {
        $key = base64_encode(random_bytes(16));
        $accept = Connection::generateAcceptKey($key);
        $decoded = base64_decode($accept, true);
        $this->assertNotFalse($decoded);
        $this->assertSame(20, strlen($decoded));
    }

    #[Test]
    public function handshake_headers_contain_101_upgrade_and_accept(): void
    {
        $headers = Connection::createHandshakeHeaders('abc123');
        $joined = implode("\r\n", $headers);
        $this->assertStringContainsString('101', $joined);
        $this->assertStringContainsString('Upgrade: websocket', $joined);
        $this->assertStringContainsString('Connection: Upgrade', $joined);
        $this->assertStringContainsString('Sec-WebSocket-Accept: abc123', $joined);
    }

    #[Test]
    public function accept_parses_key_and_creates_connection(): void
    {
        $key = base64_encode(random_bytes(16));
        $headers = "GET /chat HTTP/1.1\r\nHost: localhost\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Version: 13\r\nSec-WebSocket-Key: {$key}\r\n\r\n";
        [$client, $server] = $this->makePipe();
        $conn = Connection::accept($server, $headers);
        $this->assertNotNull($conn);
        $this->assertInstanceOf(Connection::class, $conn);
        $this->assertTrue($conn->isOpen());
        $buf = fread($client, 256);
        $this->assertStringContainsString('101 Switching Protocols', $buf);
        fclose($client);
    }

    #[Test]
    public function accept_returns_null_without_key_header(): void
    {
        $headers = "GET / HTTP/1.1\r\nHost: localhost\r\n\r\n";
        [$client, $server] = $this->makePipe();
        $conn = Connection::accept($server, $headers);
        $this->assertNull($conn);
        fclose($client);
        fclose($server);
    }

    #[Test]
    public function accept_returns_null_for_bad_key_length(): void
    {
        $headers = "GET /chat HTTP/1.1\r\nHost: localhost\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Version: 13\r\nSec-WebSocket-Key: " . base64_encode('short') . "\r\n\r\n";
        [$client, $server] = $this->makePipe();
        $conn = Connection::accept($server, $headers);
        $this->assertNull($conn);
        fclose($client);
        fclose($server);
    }

    #[Test]
    public function accept_returns_null_for_missing_version(): void
    {
        $key = base64_encode(random_bytes(16));
        $headers = "GET /chat HTTP/1.1\r\nHost: localhost\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Key: {$key}\r\n\r\n";
        [$client, $server] = $this->makePipe();
        $conn = Connection::accept($server, $headers);
        $this->assertNull($conn);
        fclose($client);
        fclose($server);
    }

    // ──────────── Frame decoding (via reflection on buffer) ────────────

    /** @return array{opcode:int,payload:string}|null */
    private function decodeFrame(Connection $conn, string $frameData): ?array
    {
        $ref = new \ReflectionClass($conn);
        $bufProp = $ref->getProperty('buffer');
        $bufProp->setValue($conn, $frameData);
        $parse = $ref->getMethod('parseFrame');
        return $parse->invoke($conn);
    }

    #[Test]
    public function decode_small_text_frame(): void
    {
        $conn = $this->newPipeConn();
        $payload = '{"msg":"hello"}';
        $frame = $this->encodeViaReflection('{"msg":"hello"}', 0x1);
        $result = $this->decodeFrame($conn, $frame);
        $this->assertNotNull($result);
        $this->assertSame(0x1, $result['opcode']);
        $this->assertSame($payload, $result['payload']);
    }

    #[Test]
    public function decode_binary_frame(): void
    {
        $conn = $this->newPipeConn();
        $payload = "\x00\x01\x02\x03";
        $frame = $this->encodeViaReflection($payload, 0x2);
        $result = $this->decodeFrame($conn, $frame);
        $this->assertNotNull($result);
        $this->assertSame(0x2, $result['opcode']);
        $this->assertSame($payload, $result['payload']);
    }

    #[Test]
    public function decode_close_frame_closes_connection(): void
    {
        $conn = $this->newPipeConn();
        $payload = pack('n', 1000);
        $frame = $this->encodeViaReflection($payload, 0x8);
        $result = $this->decodeFrame($conn, $frame);
        $this->assertNull($result);
        $this->assertFalse($conn->isOpen());
    }

    #[Test]
    public function decode_ping_does_not_close(): void
    {
        $conn = $this->newPipeConn();
        $frame = $this->encodeViaReflection('ping-data', 0x9);
        $result = $this->decodeFrame($conn, $frame);
        $this->assertNull($result);
        $this->assertTrue($conn->isOpen());
    }

    #[Test]
    public function decode_pong_ignored(): void
    {
        $conn = $this->newPipeConn();
        $frame = $this->encodeViaReflection('pong', 0xA);
        $result = $this->decodeFrame($conn, $frame);
        $this->assertNull($result);
        $this->assertTrue($conn->isOpen());
    }

    #[Test]
    public function decode_incomplete_header_returns_null(): void
    {
        $conn = $this->newPipeConn();
        $result = $this->decodeFrame($conn, "\x81");
        $this->assertNull($result);
    }

    #[Test]
    public function decode_incomplete_extended_length_returns_null(): void
    {
        $conn = $this->newPipeConn();
        $result = $this->decodeFrame($conn, "\x81\x7E\x03");
        $this->assertNull($result);
    }

    #[Test]
    public function decode_masked_payload(): void
    {
        $conn = $this->newPipeConn();
        $payload = 'masked-data';
        $mask = "\x3E\x5A\xF1\xA2";
        $masked = '';
        for ($i = 0; $i < strlen($payload); $i++) {
            $masked .= chr(ord($payload[$i]) ^ ord($mask[$i % 4]));
        }
        $len = strlen($payload);
        $frame = chr(0x81) . chr(0x80 | $len) . $mask . $masked;
        $result = $this->decodeFrame($conn, $frame);
        $this->assertNotNull($result);
        $this->assertSame($payload, $result['payload']);
    }

    #[Test]
    public function server_side_connection_rejects_unmasked_client_frame(): void
    {
        [$local, $remote] = $this->makePipe();
        $conn = new Connection($local, true);
        $frame = $this->encodeViaReflection('unmasked', 0x1);

        $result = $this->decodeFrame($conn, $frame);

        $this->assertNull($result);
        $this->assertFalse($conn->isOpen());
        fclose($remote);
    }

    #[Test]
    public function oversized_frame_closes_connection(): void
    {
        [$local, $remote] = $this->makePipe();
        $conn = new Connection($local);
        $frame = chr(0x81) . chr(127) . pack('J', 1_048_577);

        $result = $this->decodeFrame($conn, $frame);

        $this->assertNull($result);
        $this->assertFalse($conn->isOpen());
        fclose($remote);
    }

    #[Test]
    public function decode_medium_sized_payload(): void
    {
        $conn = $this->newPipeConn();
        $payload = str_repeat('x', 200);
        $frame = $this->encodeViaReflection($payload, 0x1);
        $result = $this->decodeFrame($conn, $frame);
        $this->assertNotNull($result);
        $this->assertSame(200, strlen($result['payload']));
    }

    // ──────────── Send / Ping / Close over real pipe ────────────

    #[Test]
    public function send_writes_text_frame(): void
    {
        [$local, $remote] = $this->makePipe();
        $conn = new Connection($local);
        $conn->send('hello');
        $data = fread($remote, 100);
        $this->assertSame(0x81, ord($data[0]));
        $this->assertSame(5, ord($data[1]));
        $this->assertSame('hello', substr($data, 2));
        fclose($remote);
    }

    #[Test]
    public function send_json_writes_encoded_json_frame(): void
    {
        [$local, $remote] = $this->makePipe();
        $conn = new Connection($local);
        $conn->sendJson(['type' => 'chat', 'msg' => 'hey']);
        $data = fread($remote, 200);
        $payload = substr($data, 2);
        $decoded = json_decode($payload, true);
        $this->assertSame('chat', $decoded['type']);
        $this->assertSame('hey', $decoded['msg']);
        fclose($remote);
    }

    #[Test]
    public function ping_sends_ping_frame(): void
    {
        [$local, $remote] = $this->makePipe();
        $conn = new Connection($local);
        $conn->ping();
        $data = fread($remote, 10);
        $this->assertSame(0x89, ord($data[0]));
        fclose($remote);
    }

    #[Test]
    public function close_sends_close_frame_and_marks_closed(): void
    {
        [$local, $remote] = $this->makePipe();
        $conn = new Connection($local);
        $this->assertTrue($conn->isOpen());
        $conn->close(1000);
        $this->assertFalse($conn->isOpen());
        $data = fread($remote, 10);
        $this->assertSame(0x88, ord($data[0]));
        fclose($remote);
    }

    #[Test]
    public function send_returns_false_when_closed(): void
    {
        [$local, $remote] = $this->makePipe();
        $conn = new Connection($local);
        $conn->close(1000);
        $this->assertFalse($conn->send('test'));
        fclose($remote);
    }

    // ──────────── Connection metadata ────────────

    #[Test]
    public function data_is_mutable_associative_array(): void
    {
        $conn = $this->newPipeConn();
        $this->assertSame([], $conn->data);
        $conn->data['user'] = 'alice';
        $conn->data['room'] = 'general';
        $this->assertSame('alice', $conn->data['user']);
        $this->assertSame('general', $conn->data['room']);
    }

    #[Test]
    public function id_is_32_char_hex_unique(): void
    {
        $conn = $this->newPipeConn();
        $this->assertSame(32, strlen($conn->id()));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $conn->id());
    }

    // ──────────── Helpers ────────────

    private function encodeViaReflection(string $payload, int $opcode): string
    {
        $ref = new \ReflectionClass(Connection::class);
        $method = $ref->getMethod('encodeFrame');
        $conn = new Connection(fopen('php://memory', 'r+'));
        return $method->invoke($conn, $payload, $opcode);
    }
}
