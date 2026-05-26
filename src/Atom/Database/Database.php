<?php
declare(strict_types=1);
namespace Atom\Database;

use PDO, PDOStatement;

final class Database
{
    private PDO $pdo;

    public function __construct(
        string $dsn,
        ?string $user = null,
        ?string $pass = null,
        array $options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC],
    ) {
        $this->pdo = new PDO($dsn, $user, $pass, $options);
    }

    /** @return list<array<string,mixed>> */
    public function all(string $sql, array $params = []): array
    {
        return $this->execute($sql, $params)->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public function one(string $sql, array $params = []): ?array
    {
        return $this->execute($sql, $params)->fetch() ?: null;
    }

    public function single(string $sql, array $params = []): mixed
    {
        return $this->execute($sql, $params)->fetchColumn();
    }

    public function run(string $sql, array $params = []): int
    {
        $stmt = $this->execute($sql, $params);
        return $stmt->rowCount();
    }

    public function lastId(): string
    {
        return $this->pdo->lastInsertId();
    }

    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    public function rollback(): bool
    {
        return $this->pdo->rollBack();
    }

    public function raw(): PDO
    {
        return $this->pdo;
    }

    private function execute(string $sql, array $params): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
}
