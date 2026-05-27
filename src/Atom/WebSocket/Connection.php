<?php
declare(strict_types=1);
namespace Atom\WebSocket;

final class Connection
{
    private const MAX_PAYLOAD_BYTES = 1_048_576;
    /** @var list<int> */
    private const DATA_OPCODES = [0x1, 0x2];
    /** @var list<int> */
    private const CONTROL_OPCODES = [0x8, 0x9, 0xA];

    private string $id;
    /** @var resource */
    private $socket;
    private string $buffer = '';
    private bool $open = true;
    private ?int $fragmentedOpcode = null;
    private string $fragmentedPayload = '';
    /** @var array<string,mixed> */
    public array $data = [];

    /**
     * @param resource $socket
     */
    public function __construct($socket, private bool $requireMaskedFrames = false)
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

    /** @param list<string> $protocols @return list<string> */
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
        if (!preg_match('#^GET\s+\S+\s+HTTP/1\.[01]\r?$#im', $requestHeaders)) {
            return null;
        }
        if (!preg_match('#^Upgrade:\s*websocket\r?$#im', $requestHeaders)) {
            return null;
        }
        if (!preg_match('#^Connection:\s*.*\bUpgrade\b.*\r?$#im', $requestHeaders)) {
            return null;
        }
        if (!preg_match('#^Sec-WebSocket-Version:\s*13\r?$#im', $requestHeaders)) {
            return null;
        }
        if (!preg_match('#^Sec-WebSocket-Key:\s*(\S+)\r?$#im', $requestHeaders, $m)) {
            return null;
        }
        $decoded = base64_decode($m[1], true);
        if ($decoded === false || strlen($decoded) !== 16) {
            return null;
        }
        $acceptKey = self::generateAcceptKey($m[1]);
        $headers = self::createHandshakeHeaders($acceptKey);
        fwrite($client, implode("\r\n", $headers) . "\r\n\r\n");
        return new self($client, true);
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
        $this->assertSendableFrame($payload, $opcode);
        $frame = $this->encodeFrame($payload, $opcode);
        return $this->writeAll($frame);
    }

    public function sendJson(mixed $data): bool
    {
        return $this->send(json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
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
        if (!self::isValidCloseCode($code)) {
            throw new \InvalidArgumentException("Invalid WebSocket close code: {$code}");
        }
        $this->writeAll($this->encodeFrame(pack('n', $code), 0x8));
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
        $fin = ($first & 0x80) !== 0;
        $rsv = $first & 0x70;
        $opcode = $first & 0x0F;
        $masked = ($second & 0x80) !== 0;
        $len = $second & 0x7F;

        if ($rsv !== 0 || !in_array($opcode, [0x0, ...self::DATA_OPCODES, ...self::CONTROL_OPCODES], true)) {
            return $this->protocolError();
        }
        if ($this->requireMaskedFrames && !$masked) {
            return $this->protocolError();
        }

        $offset = 2;
        if ($len === 126) {
            if (strlen($this->buffer) < 4) return null;
            $len = unpack('n', substr($this->buffer, 2, 2))[1];
            if ($len < 126) {
                return $this->protocolError();
            }
            $offset = 4;
        } elseif ($len === 127) {
            if (strlen($this->buffer) < 10) return null;
            $lengthBytes = substr($this->buffer, 2, 8);
            if ((ord($lengthBytes[0]) & 0x80) !== 0) {
                return $this->protocolError();
            }
            $len = unpack('J', $lengthBytes)[1];
            if ($len < 65536) {
                return $this->protocolError();
            }
            $offset = 10;
        }

        $isControl = in_array($opcode, self::CONTROL_OPCODES, true);
        if ($isControl && (!$fin || $len > 125)) {
            return $this->protocolError();
        }
        if ($len > self::MAX_PAYLOAD_BYTES) {
            $this->close(1009);
            return null;
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

        if ($opcode === 0x0) {
            return $this->continuationReceived($payload, $fin);
        }
        if ($opcode === 0x1 || $opcode === 0x2) {
            return $this->dataReceived($opcode, $payload, $fin);
        }
        if ($opcode === 0x8) {
            return $this->closeReceived($payload);
        }
        if ($opcode === 0x9) {
            return $this->handlePing($payload);
        }
        return null;
    }

    /** @return array{opcode:int,payload:string}|null */
    private function dataReceived(int $opcode, string $payload, bool $fin): ?array
    {
        if ($this->fragmentedOpcode !== null) {
            return $this->protocolError();
        }
        if ($fin) {
            return ['opcode' => $opcode, 'payload' => $payload];
        }
        $this->fragmentedOpcode = $opcode;
        $this->fragmentedPayload = $payload;
        return null;
    }

    /** @return array{opcode:int,payload:string}|null */
    private function continuationReceived(string $payload, bool $fin): ?array
    {
        if ($this->fragmentedOpcode === null) {
            return $this->protocolError();
        }
        if (strlen($this->fragmentedPayload) + strlen($payload) > self::MAX_PAYLOAD_BYTES) {
            $this->fragmentedOpcode = null;
            $this->fragmentedPayload = '';
            $this->close(1009);
            return null;
        }
        $this->fragmentedPayload .= $payload;
        if (!$fin) {
            return null;
        }
        $opcode = $this->fragmentedOpcode;
        $complete = $this->fragmentedPayload;
        $this->fragmentedOpcode = null;
        $this->fragmentedPayload = '';
        return ['opcode' => $opcode, 'payload' => $complete];
    }

    private function closeReceived(string $payload): null
    {
        $length = strlen($payload);
        if ($length === 1) {
            return $this->protocolError();
        }
        $code = $length >= 2 ? unpack('n', substr($payload, 0, 2))[1] : 1000;
        if ($length >= 2 && !self::isValidCloseCode($code)) {
            return $this->protocolError();
        }
        if ($this->open) {
            $this->writeAll($this->encodeFrame(pack('n', $code), 0x8));
        }
        $this->open = false;
        return null;
    }

    private function handlePing(string $payload): null
    {
        $this->writeAll($this->encodeFrame($payload, 0xA));
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

    private function writeAll(string $frame): bool
    {
        $offset = 0;
        $length = strlen($frame);
        $emptyWrites = 0;
        while ($offset < $length) {
            $written = @fwrite($this->socket, substr($frame, $offset));
            if ($written === false) {
                $this->open = false;
                return false;
            }
            if ($written === 0) {
                if (++$emptyWrites > 5) {
                    return false;
                }
                usleep(1000);
                continue;
            }
            $offset += $written;
            $emptyWrites = 0;
        }
        return true;
    }

    private static function unmask(string $payload, string $mask): string
    {
        $result = '';
        for ($i = 0; $i < strlen($payload); $i++) {
            $result .= chr(ord($payload[$i]) ^ ord($mask[$i % 4]));
        }
        return $result;
    }

    private function protocolError(): null
    {
        $this->close(1002);
        return null;
    }

    private function assertSendableFrame(string $payload, int $opcode): void
    {
        if (!in_array($opcode, [...self::DATA_OPCODES, ...self::CONTROL_OPCODES], true)) {
            throw new \InvalidArgumentException("Invalid WebSocket opcode: {$opcode}");
        }
        if (in_array($opcode, self::CONTROL_OPCODES, true) && strlen($payload) > 125) {
            throw new \InvalidArgumentException('WebSocket control frames must be 125 bytes or less');
        }
    }

    private static function isValidCloseCode(int $code): bool
    {
        return in_array($code, [1000, 1001, 1002, 1003, 1007, 1008, 1009, 1010, 1011, 1012, 1013, 1014], true)
            || ($code >= 3000 && $code <= 4999);
    }
}
