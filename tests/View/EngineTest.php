<?php
declare(strict_types=1);
namespace Atom\Tests\View;

use Atom\View\Engine;
use Atom\View\Template;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(Engine::class)]
#[CoversClass(Template::class)]
final class EngineTest extends TestCase
{
    private Engine $engine;
    private string $tmpViewsDir;
    private string $tmpCacheDir;

    protected function setUp(): void
    {
        $this->tmpViewsDir = sys_get_temp_dir() . '/atom_v_' . uniqid();
        $this->tmpCacheDir = sys_get_temp_dir() . '/atom_c_' . uniqid();
        mkdir($this->tmpViewsDir, 0777, true);
        mkdir($this->tmpCacheDir, 0777, true);
        $this->engine = new Engine($this->tmpViewsDir, $this->tmpCacheDir);
    }

    protected function tearDown(): void
    {
        $this->rmDir($this->tmpViewsDir);
        $this->rmDir($this->tmpCacheDir);
    }

    private function rmDir(string $dir): void
    {
        if (!is_dir($dir)) return;
        foreach (scandir($dir) as $f) {
            if ($f === '.' || $f === '..') continue;
            $p = "$dir/$f";
            is_dir($p) ? $this->rmDir($p) : unlink($p);
        }
        rmdir($dir);
    }

    #[Test]
    public function render_simple_template(): void
    {
        file_put_contents($this->tmpViewsDir . '/hello.twig', 'Hello {{ name }}!');
        $result = $this->engine->render('hello.twig', ['name' => 'World']);
        $this->assertSame('Hello World!', trim($result));
    }

    #[Test]
    public function render_with_auto_escape(): void
    {
        file_put_contents($this->tmpViewsDir . '/escape.twig', '{{ code }}');
        $result = $this->engine->render('escape.twig', ['code' => '<script>alert(1)</script>']);
        $this->assertStringContainsString('&lt;script&gt;', $result);
        $this->assertStringNotContainsString('<script>', $result);
    }

    #[Test]
    public function render_with_raw_filter_no_escape(): void
    {
        file_put_contents($this->tmpViewsDir . '/raw.twig', '{{ html | raw }}');
        $result = $this->engine->render('raw.twig', ['html' => '<b>bold</b>']);
        $this->assertStringContainsString('<b>bold</b>', $result);
    }

    #[Test]
    public function render_with_filter_chain(): void
    {
        file_put_contents($this->tmpViewsDir . '/chain.twig', '{{ name | trim | upper }}');
        $result = $this->engine->render('chain.twig', ['name' => '  hello  ']);
        $this->assertStringContainsString('HELLO', trim($result));
    }

    #[Test]
    public function render_if_true(): void
    {
        file_put_contents($this->tmpViewsDir . '/ift.twig', '{% if show %}visible{% endif %}');
        $result = $this->engine->render('ift.twig', ['show' => true]);
        $this->assertStringContainsString('visible', $result);
    }

    #[Test]
    public function render_if_false(): void
    {
        file_put_contents($this->tmpViewsDir . '/iff.twig', '{% if show %}visible{% endif %}');
        $result = $this->engine->render('iff.twig', ['show' => false]);
        $this->assertStringNotContainsString('visible', $result);
    }

    #[Test]
    public function render_if_else(): void
    {
        file_put_contents($this->tmpViewsDir . '/ifelse.twig', '{% if flag %}yes{% else %}no{% endif %}');
        $this->assertStringContainsString('yes', $this->engine->render('ifelse.twig', ['flag' => true]));
        $this->assertStringContainsString('no', $this->engine->render('ifelse.twig', ['flag' => false]));
    }

    #[Test]
    public function render_for_loop(): void
    {
        file_put_contents($this->tmpViewsDir . '/loop.twig', '{% for item in items %}{{ item }},{% endfor %}');
        $result = $this->engine->render('loop.twig', ['items' => ['a', 'b', 'c']]);
        $this->assertStringContainsString('a,b,c', $result);
    }

    #[Test]
    public function render_for_loop_with_null_does_not_error(): void
    {
        file_put_contents($this->tmpViewsDir . '/nullloop.twig', '{% for item in items %}{{ item }}{% endfor %}');
        $result = $this->engine->render('nullloop.twig', ['items' => null]);
        $this->assertEmpty(trim($result));
    }

    #[Test]
    public function render_set_variable(): void
    {
        file_put_contents($this->tmpViewsDir . '/set.twig', '{% set x = 5 %}{{ x }}');
        $result = $this->engine->render('set.twig', []);
        $this->assertStringContainsString('5', trim($result));
    }

    #[Test]
    public function render_with_globals(): void
    {
        $this->engine->addGlobal('app_name', 'MyApp');
        file_put_contents($this->tmpViewsDir . '/global.twig', '{{ app_name }}');
        $result = $this->engine->render('global.twig', []);
        $this->assertStringContainsString('MyApp', $result);
    }

    #[Test]
    public function render_data_overrides_globals(): void
    {
        $this->engine->addGlobal('title', 'Default');
        file_put_contents($this->tmpViewsDir . '/override.twig', '{{ title }}');
        $result = $this->engine->render('override.twig', ['title' => 'Override']);
        $this->assertStringContainsString('Override', $result);
    }

    #[Test]
    public function render_dot_notation_access(): void
    {
        file_put_contents($this->tmpViewsDir . '/dot.twig', '{{ user.name }}');
        $result = $this->engine->render('dot.twig', ['user' => ['name' => 'John']]);
        $this->assertStringContainsString('John', $result);
    }

    #[Test]
    public function render_filter_default(): void
    {
        file_put_contents($this->tmpViewsDir . '/def.twig', '{{ name | default("nobody") }}');
        $result = $this->engine->render('def.twig', ['name' => '']);
        $this->assertStringContainsString('nobody', $result);
    }

    #[Test]
    public function render_filter_default_with_value(): void
    {
        file_put_contents($this->tmpViewsDir . '/def2.twig', '{{ name | default("nobody") }}');
        $result = $this->engine->render('def2.twig', ['name' => 'Alice']);
        $this->assertStringContainsString('Alice', $result);
    }

    #[Test]
    public function render_filter_e_is_alias_for_escape(): void
    {
        file_put_contents($this->tmpViewsDir . '/e.twig', '{{ text | e }}');
        $result = $this->engine->render('e.twig', ['text' => '<x>']);
        $this->assertStringContainsString('&lt;x&gt;', $result);
    }

    #[Test]
    public function render_filter_json(): void
    {
        file_put_contents($this->tmpViewsDir . '/json.twig', '{{ data | json }}');
        $result = $this->engine->render('json.twig', ['data' => ['a' => 1]]);
        $this->assertStringContainsString('{"a":1}', $result);
    }

    #[Test]
    public function render_filter_length(): void
    {
        file_put_contents($this->tmpViewsDir . '/len.twig', '{{ items | length }}');
        $result = $this->engine->render('len.twig', ['items' => [1, 2, 3]]);
        $this->assertStringContainsString('3', trim($result));
    }

    #[Test]
    public function render_filter_upper(): void
    {
        file_put_contents($this->tmpViewsDir . '/upper.twig', '{{ text | upper }}');
        $result = $this->engine->render('upper.twig', ['text' => 'hello']);
        $this->assertStringContainsString('HELLO', $result);
    }

    #[Test]
    public function render_filter_lower(): void
    {
        file_put_contents($this->tmpViewsDir . '/lower.twig', '{{ text | lower }}');
        $result = $this->engine->render('lower.twig', ['text' => 'HELLO']);
        $this->assertStringContainsString('hello', $result);
    }

    #[Test]
    public function render_filter_trim(): void
    {
        file_put_contents($this->tmpViewsDir . '/trim.twig', '{{ text | trim }}');
        $result = $this->engine->render('trim.twig', ['text' => '  hi  ']);
        $this->assertStringContainsString('hi', trim($result));
    }

    #[Test]
    public function render_filter_nl2br(): void
    {
        file_put_contents($this->tmpViewsDir . '/nl.twig', '{{ text | nl2br | raw }}');
        $result = $this->engine->render('nl.twig', ['text' => "line1\nline2"]);
        $this->assertStringContainsString('<br />', $result);
    }

    #[Test]
    public function add_filter_custom(): void
    {
        $this->engine->addFilter('reverse', fn(string $s): string => strrev($s));
        $result = $this->engine->getFilter('reverse')('abc');
        $this->assertSame('cba', $result);
    }

    #[Test]
    public function add_filter_available_in_template(): void
    {
        $this->engine->addFilter('shout', fn(string $s): string => strtoupper($s) . '!');
        file_put_contents($this->tmpViewsDir . '/shout.twig', '{{ text | shout }}');
        $result = $this->engine->render('shout.twig', ['text' => 'hey']);
        $this->assertStringContainsString('HEY!', $result);
    }

    #[Test]
    public function get_filter_throws_for_unknown(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->engine->getFilter('nonexistent_filter');
    }

    #[Test]
    public function render_caches_compiled_template(): void
    {
        file_put_contents($this->tmpViewsDir . '/cache.twig', 'cached-content');

        // First render - compiles and caches
        $r1 = $this->engine->render('cache.twig', []);
        $this->assertStringContainsString('cached-content', $r1);

        // Check cache file exists
        $cacheFiles = glob($this->tmpCacheDir . '/*.php');
        $this->assertNotEmpty($cacheFiles, 'Cache file should exist after compilation');

        // Second render - from cache
        $r2 = $this->engine->render('cache.twig', []);
        $this->assertSame($r1, $r2);
    }

    #[Test]
    public function render_include_partial(): void
    {
        mkdir($this->tmpViewsDir . '/partials', 0777, true);
        file_put_contents($this->tmpViewsDir . '/partials/footer.twig', '<footer>end</footer>');
        file_put_contents($this->tmpViewsDir . '/page.twig', '{% include "partials/footer.twig" %}');

        $result = $this->engine->render('page.twig', []);
        $this->assertStringContainsString('<footer>end</footer>', $result);
    }

    #[Test]
    public function render_template_inheritance(): void
    {
        // Layout
        file_put_contents($this->tmpViewsDir . '/layout.twig', '<html>{% block body %}{% endblock %}</html>');
        // Child extends layout and defines block
        file_put_contents($this->tmpViewsDir . '/child.twig', '{% extends "layout.twig" %}{% block body %}<p>{{ msg }}</p>{% endblock %}');

        $result = $this->engine->render('child.twig', ['msg' => 'hello']);
        $this->assertStringContainsString('<html>', $result);
        $this->assertStringContainsString('<p>hello</p>', $result);
        $this->assertStringContainsString('</html>', $result);
    }

    #[Test]
    public function render_template_inheritance_preserves_parent_blocks(): void
    {
        // Layout with default block
        file_put_contents($this->tmpViewsDir . '/parent.twig', '<head>{% block title %}Default{% endblock %}</head><body>{% block content %}{% endblock %}</body>');
        // Child only overrides content
        file_put_contents($this->tmpViewsDir . '/child2.twig', '{% extends "parent.twig" %}{% block content %}<p>Hi</p>{% endblock %}');

        $result = $this->engine->render('child2.twig', []);
        $this->assertStringContainsString('Default', $result);
        $this->assertStringContainsString('<p>Hi</p>', $result);
    }

    #[Test]
    public function render_variable_from_context(): void
    {
        file_put_contents($this->tmpViewsDir . '/ctx.twig', '{{ count }}');
        $result = $this->engine->render('ctx.twig', ['count' => 10]);
        $this->assertStringContainsString('10', trim($result));
    }

    #[Test]
    public function render_plain_text_returned_as_is(): void
    {
        file_put_contents($this->tmpViewsDir . '/plain.twig', 'Just some text without any tags.');
        $result = $this->engine->render('plain.twig', []);
        $this->assertStringContainsString('Just some text', $result);
    }

    #[Test]
    public function render_multiple_expressions(): void
    {
        file_put_contents($this->tmpViewsDir . '/multi.twig', '{{ a }} + {{ b }} = {{ sum }}');
        $result = $this->engine->render('multi.twig', ['a' => 2, 'b' => 3, 'sum' => 5]);
        $this->assertStringContainsString('2 + 3 = 5', $result);
    }

    #[Test]
    public function render_nonexistent_template_throws(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Template not found');
        $this->engine->render('does_not_exist.twig', []);
    }

    #[Test]
    public function render_path_traversal_blocked(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->engine->render('../outside.twig', []);
    }

    #[Test]
    public function render_for_loop_with_key_value(): void
    {
        file_put_contents($this->tmpViewsDir . '/kv.twig', '{% for key, val in data %}{{ key }}:{{ val }};{% endfor %}');
        $result = $this->engine->render('kv.twig', ['data' => ['x' => 1, 'y' => 2]]);
        $this->assertStringContainsString('x:1', $result);
        $this->assertStringContainsString('y:2', $result);
    }

    #[Test]
    public function load_throws_for_nonexistent_views_directory(): void
    {
        $engine = new Engine('/nonexistent/path/12345', $this->tmpCacheDir);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('views directory not found');
        $engine->load('test.twig');
    }

    #[Test]
    public function load_throws_when_cache_dir_is_file(): void
    {
        $cacheDir = $this->tmpCacheDir . '/blocked';
        file_put_contents($cacheDir, 'block');
        $engine = new Engine($this->tmpViewsDir, $cacheDir);
        file_put_contents($this->tmpViewsDir . '/test.twig', 'hello');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('cannot create cache directory');
        $engine->load('test.twig');
    }

    #[Test]
    public function load_compiles_and_caches_template(): void
    {
        file_put_contents($this->tmpViewsDir . '/fresh.twig', 'fresh content');
        $this->engine->load('fresh.twig');
        $files = glob($this->tmpCacheDir . '/*.php');
        $this->assertNotEmpty($files);
    }
}
