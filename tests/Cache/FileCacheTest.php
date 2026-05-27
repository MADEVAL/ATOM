<?php
declare(strict_types=1);
namespace Atom\Tests\Cache;

use Atom\Cache\{Cache, FileDriver};
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(FileDriver::class)]
#[CoversClass(Cache::class)]
final class FileCacheTest extends TestCase
{
    private Cache $cache;
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/atom_fcache_' . uniqid();
        $this->cache = new Cache(new FileDriver($this->dir, cleanupChance: 1));
    }

    protected function tearDown(): void
    {
        foreach ((array) glob($this->dir . '/*') as $f) @unlink($f);
        if (is_dir($this->dir)) rmdir($this->dir);
    }

    #[Test]
    public function set_and_get(): void
    {
        $this->cache->set('greeting', 'hello file');
        $this->assertSame('hello file', $this->cache->get('greeting'));
    }

    #[Test]
    public function get_default_for_missing(): void
    {
        $this->assertNull($this->cache->get('missing'));
        $this->assertSame(404, $this->cache->get('missing', 404));
    }

    #[Test]
    public function has_detects(): void
    {
        $this->assertFalse($this->cache->has('foo'));
        $this->cache->set('foo', 'bar');
        $this->assertTrue($this->cache->has('foo'));
    }

    #[Test]
    public function delete_removes_file(): void
    {
        $this->cache->set('todelete', 'bye');
        $this->assertTrue($this->cache->has('todelete'));
        $this->cache->delete('todelete');
        $this->assertFalse($this->cache->has('todelete'));
    }

    #[Test]
    public function flush_clears_all_files(): void
    {
        $this->cache->set('x', 1);
        $this->cache->set('y', 2);
        $this->cache->flush();
        $this->assertNull($this->cache->get('x'));
        $this->assertNull($this->cache->get('y'));
    }

    #[Test]
    public function ttl_expires(): void
    {
        $this->cache->set('short', 'lived', 1);
        $this->assertSame('lived', $this->cache->get('short'));
        sleep(2);
        $this->assertNull($this->cache->get('short'));
    }

    #[Test]
    public function remember_file(): void
    {
        $calls = 0;
        $val = $this->cache->remember('expensive', function () use (&$calls) {
            $calls++;
            return 'computed';
        }, 60);
        $this->assertSame(1, $calls);
        $this->assertSame('computed', $val);

        $this->cache->remember('expensive', function () use (&$calls) {
            $calls++;
            return 'ignored';
        }, 60);
        $this->assertSame(1, $calls);
    }

    #[Test]
    public function stores_complex_data(): void
    {
        $data = ['users' => [['id' => 1, 'name' => 'Alice'], ['id' => 2, 'name' => 'Bob']]];
        $this->cache->set('users', $data);
        $this->assertSame($data, $this->cache->get('users'));
    }

    #[Test]
    public function stores_null_value(): void
    {
        $this->cache->set('empty', null);
        $this->assertTrue($this->cache->has('empty'));
        $this->assertNull($this->cache->get('empty'));
    }

    #[Test]
    public function increment_decrement_file(): void
    {
        $this->assertSame(10, $this->cache->increment('hits', 10));
        $this->assertSame(9, $this->cache->decrement('hits'));
        $this->assertSame(0, $this->cache->decrement('hits', 9));
    }

    #[Test]
    public function delete_idempotent(): void
    {
        $this->cache->delete('never_existed');
        $this->assertFalse($this->cache->has('never_existed'));
    }

    #[Test]
    public function no_ttl_never_expires(): void
    {
        $this->cache->set('forever', 'data', 0);
        $this->assertSame('data', $this->cache->get('forever'));
        $this->assertTrue($this->cache->has('forever'));
    }
}
