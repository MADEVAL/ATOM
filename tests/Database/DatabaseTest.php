<?php
declare(strict_types=1);
namespace Atom\Tests\Database;

use Atom\Database\Database;
use PDO;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(Database::class)]
final class DatabaseTest extends TestCase
{
    private ?Database $db = null;
    private string $tmpFile;

    protected function setUp(): void
    {
        $this->tmpFile = sys_get_temp_dir() . '/atom_test_' . uniqid() . '.sqlite';
        $this->db = new Database("sqlite:{$this->tmpFile}");
        $this->db->run('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)');
        $this->db->run('INSERT INTO users (name) VALUES (?)', ['Alice']);
        $this->db->run('INSERT INTO users (name) VALUES (?)', ['Bob']);
    }

    protected function tearDown(): void
    {
        $this->db = null;
        if (is_file($this->tmpFile)) unlink($this->tmpFile);
    }

    #[Test]
    public function all_returns_rows(): void
    {
        $rows = $this->db->all('SELECT * FROM users ORDER BY id');
        $this->assertCount(2, $rows);
        $this->assertSame('Alice', $rows[0]['name']);
        $this->assertSame('Bob', $rows[1]['name']);
    }

    #[Test]
    public function one_returns_single_row(): void
    {
        $row = $this->db->one('SELECT * FROM users WHERE name = ?', ['Alice']);
        $this->assertIsArray($row);
        $this->assertSame('Alice', $row['name']);
    }

    #[Test]
    public function one_returns_null_for_no_match(): void
    {
        $row = $this->db->one('SELECT * FROM users WHERE name = ?', ['Nobody']);
        $this->assertNull($row);
    }

    #[Test]
    public function single_returns_scalar(): void
    {
        $count = $this->db->single('SELECT COUNT(*) FROM users');
        $this->assertSame(2, (int) $count);
    }

    #[Test]
    public function run_returns_affected_rows(): void
    {
        $affected = $this->db->run('UPDATE users SET name = ? WHERE name = ?', ['Alicia', 'Alice']);
        $this->assertSame(1, $affected);
    }

    #[Test]
    public function last_id_returns_inserted_id(): void
    {
        $this->db->run('INSERT INTO users (name) VALUES (?)', ['Charlie']);
        $id = (int) $this->db->lastId();
        $this->assertGreaterThan(2, $id);
    }

    #[Test]
    public function raw_returns_pdo_instance(): void
    {
        $this->assertInstanceOf(PDO::class, $this->db->raw());
    }

    #[Test]
    public function named_params_work(): void
    {
        $row = $this->db->one('SELECT * FROM users WHERE name = :n', ['n' => 'Bob']);
        $this->assertSame('Bob', $row['name']);
    }

    #[Test]
    public function empty_result_set(): void
    {
        $rows = $this->db->all('SELECT * FROM users WHERE name = ?', ['Nobody']);
        $this->assertEmpty($rows);
    }

    #[Test]
    public function transaction_commit_persists(): void
    {
        $this->db->beginTransaction();
        $this->db->run('INSERT INTO users (name) VALUES (?)', ['InTx']);
        $this->db->commit();

        $row = $this->db->one('SELECT * FROM users WHERE name = ?', ['InTx']);
        $this->assertNotNull($row);
        $this->assertSame('InTx', $row['name']);
    }

    #[Test]
    public function transaction_rollback_discards(): void
    {
        $this->db->beginTransaction();
        $this->db->run('INSERT INTO users (name) VALUES (?)', ['RolledBack']);
        $this->db->rollback();

        $row = $this->db->one('SELECT * FROM users WHERE name = ?', ['RolledBack']);
        $this->assertNull($row);
    }

    #[Test]
    public function single_returns_false_when_empty(): void
    {
        $result = $this->db->single('SELECT name FROM users WHERE name = ?', ['Nobody']);
        $this->assertFalse($result);
    }

    #[Test]
    public function run_returns_zero_when_no_rows_affected(): void
    {
        $affected = $this->db->run('UPDATE users SET name = ? WHERE name = ?', ['X', 'Nobody']);
        $this->assertSame(0, $affected);
    }
}
