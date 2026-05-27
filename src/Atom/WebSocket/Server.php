<?php
declare(strict_types=1);
namespace Atom\WebSocket;

final class Server
{
    /** @var resource|null */
    private $mainSocket;

    private bool $running = false;
    /** @var array<int,Connection> */
    private array $connections = [];
    /** @var array<string,list<Connection>> */
    private array $rooms = [];
    /** @var array<string,array{handler:callable,path:string}> */
    private array $routes = [];

    public function __construct(
        private string $host = '0.0.0.0',
        private int $port = 8080,
    ) {}

    public function add(string $path, callable $handler): self
    {
        $this->routes[$path] = ['handler' => $handler, 'path' => $path];
        return $this;
    }

    /**
     * @return array<string,callable>
     */
    public function routes(): array
    {
        $result = [];
        foreach ($this->routes as $route) {
            $result[$route['path']] = $route['handler'];
        }
        return $result;
    }

    public function run(): void
    {
        $this->mainSocket = stream_socket_server(
            "tcp://{$this->host}:{$this->port}",
            $errno,
            $errstr,
        );
        if ($this->mainSocket === false) {
            throw new \RuntimeException("WebSocket server: {$errstr} ({$errno})");
        }
        stream_set_blocking($this->mainSocket, false);

        $this->running = true;

        while ($this->running) {
            $reads = [$this->mainSocket];
            foreach ($this->connections as $c) {
                if ($c->isOpen()) {
                    $reads[] = $c->socket();
                }
            }

            $writes = null;
            $except = null;
            $changed = @stream_select($reads, $writes, $except, 1);

            if ($changed === false) {
                usleep(50000);
                continue;
            }

            if ($changed === 0) {
                continue;
            }

            foreach ($reads as $read) {
                if ($read === $this->mainSocket) {
                    $this->acceptNewConnection();
                } else {
                    $this->handleSocketData($read);
                }
            }

            $this->cleanupDeadConnections();
        }
    }

    public function stop(): void
    {
        $this->running = false;
    }

    /** @param list<Connection>|null $connections */
    public function broadcast(string $message, ?array $connections = null): void
    {
        $targets = $connections ?? array_values($this->connections);
        foreach ($targets as $conn) {
            if ($conn->isOpen()) {
                $conn->send($message);
            }
        }
    }

    public function broadcastJson(mixed $data, ?array $connections = null): void
    {
        $this->broadcast(json_encode($data, JSON_UNESCAPED_UNICODE), $connections);
    }

    public function join(string $room, Connection $conn): void
    {
        $this->rooms[$room][] = $conn;
    }

    public function leave(string $room, Connection $conn): void
    {
        if (!isset($this->rooms[$room])) {
            return;
        }
        $this->rooms[$room] = array_filter(
            $this->rooms[$room],
            fn(Connection $c) => $c->id() !== $conn->id(),
        );
    }

    /** @return list<Connection> */
    public function room(string $room): array
    {
        return $this->rooms[$room] ?? [];
    }

    public function sendToRoom(string $room, string $message): void
    {
        $this->broadcast($message, $this->room($room));
    }

    public function sendJsonToRoom(string $room, mixed $data): void
    {
        $this->sendToRoom($room, json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    /** @return array{handler:callable|null,params:array<string,string>} */
    private function resolveRoute(string $uri): array
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';

        // exact match first
        if (isset($this->routes[$path])) {
            return ['handler' => $this->routes[$path]['handler'], 'params' => []];
        }

        // parameterized match
        foreach ($this->routes as $route) {
            $pattern = $this->pathToPattern($route['path']);
            if (preg_match($pattern, $path, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                return ['handler' => $route['handler'], 'params' => $params];
            }
        }

        return ['handler' => null, 'params' => []];
    }

    private function pathToPattern(string $path): string
    {
        $pattern = preg_quote($path, '#');
        $pattern = preg_replace('#\\\{([a-zA-Z_][a-zA-Z0-9_]*)\\\}#', '(?<$1>[^/]+)', $pattern);
        return '#^' . $pattern . '$#';
    }

    private function acceptNewConnection(): void
    {
        $client = @stream_socket_accept($this->mainSocket, 0);
        if ($client === false) {
            return;
        }

        stream_set_blocking($client, false);

        $buffer = '';
        $start = microtime(true);
        while (microtime(true) - $start < 3) {
            $data = @fread($client, 65536);
            if ($data !== false && $data !== '') {
                $buffer .= $data;
            }
            if (str_contains($buffer, "\r\n\r\n")) {
                break;
            }
            usleep(10000);
        }

        if (!str_contains($buffer, 'Upgrade: websocket')) {
            fwrite($client, "HTTP/1.1 400 Bad Request\r\n\r\n");
            fclose($client);
            return;
        }

        $conn = Connection::accept($client, $buffer);
        if ($conn === null) {
            fwrite($client, "HTTP/1.1 400 Bad Request\r\n\r\n");
            fclose($client);
            return;
        }

        $uri = '';
        if (preg_match('#GET\s+(\S+)#i', $buffer, $m)) {
            $uri = $m[1];
        }
        $route = $this->resolveRoute($uri);
        $conn->data['_route'] = $route;

        $this->connections[(int) $client] = $conn;

        if ($route['handler'] !== null) {
            try {
                ($route['handler'])($conn, null, 'open', $route['params']);
            } catch (\Throwable) {
                $conn->close(1011);
            }
        }
    }

    private function handleSocketData($socket): void
    {
        $id = (int) $socket;
        $conn = $this->connections[$id] ?? null;
        if ($conn === null) {
            return;
        }

        $frame = $conn->read();
        if ($frame === null) {
            if (!$conn->isOpen()) {
                $this->disconnect($conn);
            }
            return;
        }

        $payload = $frame['payload'];
        $route = $conn->data['_route'] ?? null;

        if ($route && $route['handler'] !== null) {
            try {
                $data = json_decode($payload, true);
                ($route['handler'])($conn, $data ?? $payload, 'message', $route['params']);
            } catch (\Throwable) {
                $conn->close(1011);
            }
        }
    }

    private function disconnect(Connection $conn): void
    {
        $route = $conn->data['_route'] ?? null;
        $closed = !$conn->isOpen();

        foreach ($this->rooms as $room => &$members) {
            $members = array_filter($members, fn(Connection $c) => $c->id() !== $conn->id());
        }
        unset($members);

        if ($route && $route['handler'] !== null && !$closed) {
            try {
                ($route['handler'])($conn, null, 'close', $route['params']);
            } catch (\Throwable) {
            }
        }

        if (is_resource($conn->socket())) {
            fclose($conn->socket());
        }

        $socketId = (int) $conn->socket();
        unset($this->connections[$socketId]);
    }

    private function cleanupDeadConnections(): void
    {
        foreach ($this->connections as $id => $conn) {
            if (!$conn->isOpen()) {
                if (is_resource($conn->socket())) {
                    fclose($conn->socket());
                }
                unset($this->connections[$id]);
            }
        }
    }
}
