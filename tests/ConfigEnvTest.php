<?php
declare(strict_types=1);
namespace Atom\Tests;

use Atom\Config;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(Config::class)]
final class ConfigEnvTest extends TestCase
{
    private string $tmpEnv;

    protected function setUp(): void
    {
        $this->tmpEnv = sys_get_temp_dir() . '/atom_env_' . uniqid() . '.env';
    }

    protected function tearDown(): void
    {
        if (is_file($this->tmpEnv)) unlink($this->tmpEnv);
    }

    #[Test]
    public function parses_key_value_pairs(): void
    {
        file_put_contents($this->tmpEnv, "APP_DEBUG=true\nDB_DSN=sqlite:test.db\n");
        $config = Config::fromEnv($this->tmpEnv, false);
        $this->assertTrue($config->debug);
        $this->assertSame('sqlite:test.db', $config->get('DB_DSN'));
    }

    #[Test]
    public function ignores_comments(): void
    {
        file_put_contents($this->tmpEnv, "# comment\nKEY=value\n");
        $config = Config::fromEnv($this->tmpEnv, false);
        $this->assertSame('value', $config->get('KEY'));
    }

    #[Test]
    public function strips_quotes(): void
    {
        file_put_contents($this->tmpEnv, "QUOTED=\"hello world\"\nSINGLE='single'\n");
        $config = Config::fromEnv($this->tmpEnv, false);
        $this->assertSame('hello world', $config->get('QUOTED'));
        $this->assertSame('single', $config->get('SINGLE'));
    }

    #[Test]
    public function set_global_populates_env(): void
    {
        $file = sys_get_temp_dir() . '/atom_env_' . uniqid();
        file_put_contents($file, "KEY=val");
        Config::fromEnv($file, true);
        unlink($file);
        $this->assertSame('val', $_ENV['KEY']);
        unset($_ENV['KEY']);
    }

    #[Test]
    public function reads_cache_and_views_dir_from_env(): void
    {
        $file = sys_get_temp_dir() . '/atom_env_' . uniqid();
        file_put_contents($file, "APP_CACHE_DIR=/tmp/cache\nAPP_VIEWS_DIR=/tmp/views");
        $config = Config::fromEnv($file, false);
        unlink($file);
        $this->assertSame('/tmp/cache', $config->cacheDir);
        $this->assertSame('/tmp/views', $config->viewsDir);
    }

    #[Test]
    public function debug_false_by_default(): void
    {
        $config = Config::fromEnv(sys_get_temp_dir() . '/atom_missing_' . uniqid());
        $this->assertFalse($config->debug);
    }

    #[Test]
    public function debug_true_when_set(): void
    {
        file_put_contents($this->tmpEnv, "APP_DEBUG=1\n");
        $config = Config::fromEnv($this->tmpEnv, false);
        $this->assertTrue($config->debug);
    }

    #[Test]
    public function get_falls_back_to_default(): void
    {
        file_put_contents($this->tmpEnv, "EXISTS=yes\n");
        $config = Config::fromEnv($this->tmpEnv, false);
        $this->assertSame('yes', $config->get('EXISTS'));
        $this->assertSame('fallback', $config->get('MISSING', 'fallback'));
    }

    #[Test]
    public function empty_file_returns_defaults(): void
    {
        file_put_contents($this->tmpEnv, '');
        $config = Config::fromEnv($this->tmpEnv, false);
        $this->assertFalse($config->debug);
        $this->assertSame('', $config->get('ANY'));
    }

    #[Test]
    public function missing_file_returns_defaults(): void
    {
        $config = Config::fromEnv('/nonexistent.env', false);
        $this->assertFalse($config->debug);
        $this->assertSame([], $config->env);
    }

    #[Test]
    public function get_falls_back_to_getenv(): void
    {
        file_put_contents($this->tmpEnv, "X=from_file\n");
        putenv('Y=from_env');
        $config = Config::fromEnv($this->tmpEnv, false);
        $this->assertSame('from_file', $config->get('X'));
        $this->assertSame('from_env', $config->get('Y'));
        putenv('Y');
    }
}
