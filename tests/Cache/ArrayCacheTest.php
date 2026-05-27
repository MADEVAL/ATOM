<?php
declare(strict_types=1);
namespace Atom\Tests\Cache;

use Atom\Cache\{Cache, ArrayDriver, FileDriver};
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(ArrayDriver::class)]
#[CoversClass(Cache::class)]
final class ArrayCacheTest extends TestCase
{
    private Cache $cache;

    protected function setUp(): void
    {
        $this->cache = new Cache(new ArrayDriver());
    }

    #[Test]
    public function set_and_get(): void
    {
        $this->cache->set('key', 'value');
        $this->assertSame('value', $this->cache->get('key'));
    }

    #[Test]
    public function get_default_when_missing(): void
    {
        $this->assertNull($this->cache->get('nope'));
        $this->assertSame('fallback', $this->cache->get('nope', 'fallback'));
    }

    #[Test]
    public function has_detects_presence(): void
    {
        $this->assertFalse($this->cache->has('x'));
        $this->cache->set('x', 1);
        $this->assertTrue($this->cache->has('x'));
    }

    #[Test]
    public function delete_removes(): void
    {
        $this->cache->set('del', 'me');
        $this->assertTrue($this->cache->has('del'));
        $this->cache->delete('del');
        $this->assertFalse($this->cache->has('del'));
    }

    #[Test]
    public function flush_clears_all(): void
    {
        $this->cache->set('a', 1);
        $this->cache->set('b', 2);
        $this->cache->flush();
        $this->assertNull($this->cache->get('a'));
        $this->assertNull($this->cache->get('b'));
    }

    #[Test]
    public function increment_and_decrement(): void
    {
        $this->assertSame(1, $this->cache->increment('counter'));
        $this->assertSame(2, $this->cache->increment('counter'));
        $this->assertSame(5, $this->cache->increment('counter', 3));
        $this->assertSame(4, $this->cache->decrement('counter'));
        $this->assertSame(0, $this->cache->decrement('counter', 4));
    }

    #[Test]
    public function remember_stores_on_miss(): void
    {
        $called = false;
        $value = $this->cache->remember('computed', function () use (&$called) {
            $called = true;
            return ['result' => 42];
        });
        $this->assertTrue($called);
        $this->assertSame(42, $value['result']);
        $called = false;
        $value = $this->cache->remember('computed', function () use (&$called) {
            $called = true;
            return ['result' => 99];
        });
        $this->assertFalse($called);
        $this->assertSame(42, $value['result']);
    }

    #[Test]
    public function remember_forever_cache(): void
    {
        $called = false;
        $this->cache->rememberForever('forever', function () use (&$called) {
            $called = true;
            return 'saved';
        });
        $this->assertTrue($called);
        $called = false;
        $this->cache->rememberForever('forever', function () use (&$called) {
            $called = true;
            return 'no';
        });
        $this->assertFalse($called);
    }

    #[Test]
    public function ttl_expires(): void
    {
        $this->cache->set('ephemeral', 'data', 0);
        $this->assertSame('data', $this->cache->get('ephemeral'));
        $this->assertTrue($this->cache->has('ephemeral'));
    }

    #[Test]
    public function stores_arrays_and_objects(): void
    {
        $obj = new \stdClass();
        $obj->name = 'test';
        $this->cache->set('obj', $obj);
        $this->assertEquals($obj, $this->cache->get('obj'));

        $this->cache->set('arr', [1, 'two', null]);
        $this->assertSame([1, 'two', null], $this->cache->get('arr'));
    }

    #[Test]
    public function stores_null_value(): void
    {
        $this->cache->set('nullish', null);
        $this->assertTrue($this->cache->has('nullish'));
        $this->assertNull($this->cache->get('nullish'));
    }
}
