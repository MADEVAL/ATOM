<?php
declare(strict_types=1);
namespace Atom\WebSocket;

final class Connection
{
    private string $id;
    /** @var resource */
    private $socket;
    private string $buffer = '';
    private bool $open = true;
    private bool $masked = true;
    /** @var array<string,mixed> */
    public array $data = [];

    /**
     * @param resource $socket
     */
    public function __construct($socket)
    {
        $this->id = bin2hex(random_bytes(16));
        $this->socket = $socket;
        stream_set_blocking($this->socket, false);
    }

    public function id(): string { return $this->id; }
    public function isOpen(): bool { return $this->open; }
    /** @return resource */
    public function socket() { return $this->socket; }

    public static function generateAcceptKey(string $key): string
    {
        return base64_encode(sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));
    }

    /** @return array<string,string> */
    public static function createHandshakeHeaders(string $acceptKey, array $protocols = []): array
    {
        return [
            'HTTP/1.1 101 Switching Protocols',
            'Upgrade: websocket',
            'Connection: Upgrade',
            'Sec-WebSocket-Accept: ' . $acceptKey,
        ];
    }

    /**
     * @param resource $client
     */
    public static function accept($client, string $requestHeaders): ?self
    {
        if (!preg_match('#Sec-WebSocket-Key:\s*(\S+)#i', $requestHeaders, $m)) {
            return null;
        }
        $acceptKey = self::generateAcceptKey($m[1]);
        $headers = self::createHandshakeHeaders($acceptKey);
        fwrite($client, implode("\r\n", $headers) . "\r\n\r\n");
        return new self($client);
    }

    /** @return array{opcode:int,payload:string}|null */
    public function read(): ?array
    {
        $data = $this->readSocket();
        if ($data === null || $data === '') {
            return null;
        }
        $this->buffer .= $data;
        return $this->parseFrame();
    }

    public function send(string $payload, int $opcode = 0x1): bool
    {
        if (!$this->open) {
            return false;
        }
        $frame = $this->encodeFrame($payload, $opcode);
        $written = @fwrite($this->socket, $frame);
        return $written !== false && $written === strlen($frame);
    }

    public function sendJson(mixed $data): bool
    {
        return $this->send(json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    public function ping(): bool
    {
        return $this->send('', 0x9);
    }

    public function close(int $code = 1000): void
    {
        if (!$this->open) {
            return;
        }
        $payload = pack('n', $code);
        @fwrite($this->socket, $this->encodeFrame($payload, 0x8));
        $this->open = false;
    }

    private function readSocket(): ?string
    {
        $data = @fread($this->socket, 65536);
        if ($data === false || $data === '') {
            if (!is_resource($this->socket) || feof($this->socket)) {
                $this->open = false;
            }
            return null;
        }
        return $data;
    }

    /** @return array{opcode:int,payload:string}|null */
    private function parseFrame(): ?array
    {
        if (strlen($this->buffer) < 2) {
            return null;
        }
        $first = ord($this->buffer[0]);
        $second = ord($this->buffer[1]);
        $opcode = $first & 0x0F;
        $masked = ($second & 0x80) !== 0;
        $len = $second & 0x7F;

        $offset = 2;
        if ($len === 126) {
            if (strlen($this->buffer) < 4) return null;
            $len = unpack('n', substr($this->buffer, 2, 2))[1];
            $offset = 4;
        } elseif ($len === 127) {
            if (strlen($this->buffer) < 10) return null;
            $len = unpack('J', substr($this->buffer, 2, 8))[1];
            $offset = 10;
        }

        $maskLen = $masked ? 4 : 0;
        if (strlen($this->buffer) < $offset + $maskLen + $len) {
            return null;
        }

        $mask = $masked ? substr($this->buffer, $offset, 4) : '';
        $offset += $maskLen;
        $payload = substr($this->buffer, $offset, (int) $len);

        if ($mask !== '') {
            $payload = self::unmask($payload, $mask);
        }

        $this->buffer = substr($this->buffer, $offset + (int) $len);

        return match ($opcode) {
            0x0, 0x1, 0x2 => ['opcode' => $opcode, 'payload' => $payload],
            0x8 => $this->closeReceived($payload),
            0x9 => $this->handlePing($payload),
            0xA => null, // pong — ignore
            default => null,
        };
    }

    /** @return null */
    private function closeReceived(string $payload): ?array
    {
        $code = strlen($payload) >= 2 ? unpack('n', substr($payload, 0, 2))[1] : 1000;
        if ($this->open) {
            @fwrite($this->socket, $this->encodeFrame(pack('n', $code), 0x8));
        }
        $this->open = false;
        return null;
    }

    /** @return null */
    private function handlePing(string $payload): ?array
    {
        @fwrite($this->socket, $this->encodeFrame($payload, 0xA));
        return null;
    }

    private function encodeFrame(string $payload, int $opcode): string
    {
        $len = strlen($payload);
        $frame = chr(0x80 | $opcode);

        if ($len <= 125) {
            $frame .= chr($len);
        } elseif ($len <= 65535) {
            $frame .= chr(126) . pack('n', $len);
        } else {
            $frame .= chr(127) . pack('J', $len);
        }

        return $frame . $payload;
    }

    private static function unmask(string $payload, string $mask): string
    {
        $result = '';
        for ($i = 0; $i < strlen($payload); $i++) {
            $result .= chr(ord($payload[$i]) ^ ord($mask[$i % 4]));
        }
        return $result;
    }
}
