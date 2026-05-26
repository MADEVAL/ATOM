<?php
declare(strict_types=1);
namespace Atom\Tests\Support;

use Atom\Support\Regex;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(Regex::class)]
final class RegexTest extends TestCase
{
    #[Test]
    public function match_returns_array_on_success(): void
    {
        $result = Regex::match('#(\d+)#', 'abc123def');
        $this->assertIsArray($result);
        $this->assertSame('123', $result[1]);
    }

    #[Test]
    public function match_returns_null_on_failure(): void
    {
        $result = Regex::match('#(\d+)#', 'abcdef');
        $this->assertNull($result);
    }

    #[Test]
    public function match_with_flags(): void
    {
        $result = Regex::match('#(\d+(\w))#i', 'abc123Xdef', PREG_UNMATCHED_AS_NULL);
        $this->assertIsArray($result);
        $this->assertSame('123X', $result[1]);
    }

    #[Test]
    public function match_all_returns_all_matches(): void
    {
        $result = Regex::matchAll('#(\d+)#', '12 abc 34 def 56');
        $this->assertCount(3, $result[1] ?? []);
        $this->assertSame('12', $result[1][0]);
        $this->assertSame('34', $result[1][1]);
        $this->assertSame('56', $result[1][2]);
    }

    #[Test]
    public function match_all_no_matches_returns_empty_structure(): void
    {
        $result = Regex::matchAll('#(\d+)#', 'no numbers');
        $this->assertEmpty($result[1] ?? []);
    }

    #[Test]
    public function replace_with_string(): void
    {
        $result = Regex::replace('#world#', 'PHP', 'hello world');
        $this->assertSame('hello PHP', $result);
    }

    #[Test]
    public function replace_with_array(): void
    {
        $result = Regex::replace(['#foo#', '#bar#'], ['FOO', 'BAR'], 'foo bar baz');
        $this->assertSame('FOO BAR baz', $result);
    }

    #[Test]
    public function replace_with_callback(): void
    {
        $result = Regex::replace(
            '#(\d+)#',
            fn(array $m): string => (string) ((int) $m[1] * 2),
            'num: 5 and 10',
        );
        $this->assertSame('num: 10 and 20', $result);
    }

    #[Test]
    public function replace_callback_receives_correct_match(): void
    {
        $captured = [];
        Regex::replace(
            '#([a-z]+)#',
            function (array $m) use (&$captured): string {
                $captured[] = $m[1];
                return strtoupper($m[1]);
            },
            'hello world',
        );
        $this->assertSame(['hello', 'world'], $captured);
    }

    #[Test]
    public function split_returns_array(): void
    {
        $result = Regex::split('#\s*,\s*#', 'a, b, c, d');
        $this->assertSame(['a', 'b', 'c', 'd'], $result);
    }

    #[Test]
    public function split_no_matches_returns_wrapped_string(): void
    {
        $result = Regex::split('#,#', 'hello');
        $this->assertSame(['hello'], $result);
    }

    #[Test]
    public function split_empty_returns_empty_array(): void
    {
        $result = Regex::split('#,#', '');
        $this->assertSame([], $result);
    }

    #[Test]
    public function quote_escapes_special_chars(): void
    {
        $result = Regex::quote('.^$*+?()[]{}|\\');
        $this->assertStringContainsString('\\.', $result);
        $this->assertStringContainsString('\\^', $result);
    }

    #[Test]
    public function quote_uses_hash_delimiter(): void
    {
        $result = Regex::quote('/path/to#file');
        $this->assertStringContainsString('\\#', $result);
        $this->assertStringNotContainsString('\\/', $result);
    }

    #[Test]
    public function assert_valid_regex_does_not_throw(): void
    {
        Regex::assert('#valid#');
        $this->assertTrue(true); // no exception
    }

    #[Test]
    public function assert_invalid_regex_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Regex::assert('#(unclosed');
    }

    #[Test]
    public function match_with_complex_pattern(): void
    {
        $result = Regex::match('#^(?<METHOD>GET)/(?<id>\d+)/(?<slug>[a-z\-]+)$#', 'GET/42/hello-world');
        $this->assertNotNull($result);
        $this->assertSame('GET', $result['METHOD']);
        $this->assertSame('42', $result['id']);
        $this->assertSame('hello-world', $result['slug']);
    }

    #[Test]
    public function replace_with_array_patterns_and_callbacks_handles_complex(): void
    {
        $result = Regex::replace(
            '#\{(\w+)\}#',
            fn($m) => strtoupper($m[1]),
            '{hello} {world}',
        );
        $this->assertSame('HELLO WORLD', $result);
    }
}
