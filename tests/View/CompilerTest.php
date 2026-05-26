<?php
declare(strict_types=1);
namespace Atom\Tests\View;

use Atom\View\Compiler;
use Atom\View\Engine;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(Compiler::class)]
final class CompilerTest extends TestCase
{
    private Engine $engine;
    private Compiler $compiler;
    private string $tmpViewsDir;
    private string $tmpCacheDir;

    protected function setUp(): void
    {
        $this->tmpViewsDir = sys_get_temp_dir() . '/atom_views_' . uniqid();
        $this->tmpCacheDir = sys_get_temp_dir() . '/atom_cache_' . uniqid();
        mkdir($this->tmpViewsDir, 0777, true);
        mkdir($this->tmpCacheDir, 0777, true);
        $this->engine = new Engine($this->tmpViewsDir, $this->tmpCacheDir);
        $this->compiler = new Compiler($this->engine);
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
    public function compile_removes_comments(): void
    {
        file_put_contents($this->tmpViewsDir . '/test.twig', 'hello {# comment #} world');
        $result = $this->engine->render('test.twig', []);
        $this->assertStringContainsString('hello', $result);
        $this->assertStringContainsString('world', $result);
        $this->assertStringNotContainsString('comment', $result);
    }

    #[Test]
    public function compile_renders_variable_output(): void
    {
        file_put_contents($this->tmpViewsDir . '/test.twig', 'Hello {{ name }}');
        $result = $this->engine->render('test.twig', ['name' => 'World']);
        $this->assertStringContainsString('Hello World', $result);
    }

    #[Test]
    public function compile_variable_with_filter(): void
    {
        file_put_contents($this->tmpViewsDir . '/test.twig', '{{ name | upper }}');
        $result = $this->engine->render('test.twig', ['name' => 'hello']);
        $this->assertStringContainsString('HELLO', $result);
    }

    #[Test]
    public function compile_variable_with_multiple_filters(): void
    {
        file_put_contents($this->tmpViewsDir . '/test.twig', '{{ name | trim | upper }}');
        $result = $this->engine->render('test.twig', ['name' => '  hello  ']);
        $this->assertStringContainsString('HELLO', trim($result));
    }

    #[Test]
    public function compile_auto_escapes_by_default(): void
    {
        file_put_contents($this->tmpViewsDir . '/test.twig', '{{ code }}');
        $result = $this->engine->render('test.twig', ['code' => '<script>']);
        $this->assertStringContainsString('&lt;script&gt;', $result);
        $this->assertStringNotContainsString('<script>', $result);
    }

    #[Test]
    public function compile_raw_filter_disables_escape(): void
    {
        file_put_contents($this->tmpViewsDir . '/test.twig', '{{ html | raw }}');
        $result = $this->engine->render('test.twig', ['html' => '<b>bold</b>']);
        $this->assertStringContainsString('<b>bold</b>', $result);
    }

    #[Test]
    public function compile_dot_notation_to_array_access(): void
    {
        file_put_contents($this->tmpViewsDir . '/test.twig', '{{ user.name }}');
        $result = $this->engine->render('test.twig', ['user' => ['name' => 'John']]);
        $this->assertStringContainsString('John', $result);
    }

    #[Test]
    public function compile_if_statement(): void
    {
        file_put_contents($this->tmpViewsDir . '/test.twig', '{% if show %}yes{% endif %}');
        $result = $this->engine->render('test.twig', ['show' => true]);
        $this->assertStringContainsString('yes', $result);
    }

    #[Test]
    public function compile_if_else_statement(): void
    {
        file_put_contents($this->tmpViewsDir . '/test.twig', '{% if x %}A{% else %}B{% endif %}');
        $this->assertStringContainsString('A', $this->engine->render('test.twig', ['x' => true]));
        $this->assertStringContainsString('B', $this->engine->render('test.twig', ['x' => false]));
    }

    #[Test]
    public function compile_elseif_statement(): void
    {
        file_put_contents($this->tmpViewsDir . '/test.twig', '{% if a %}A{% elseif b %}B{% else %}C{% endif %}');
        $this->assertStringContainsString('A', $this->engine->render('test.twig', ['a' => true, 'b' => false]));
        $this->assertStringContainsString('B', $this->engine->render('test.twig', ['a' => false, 'b' => true]));
        $this->assertStringContainsString('C', $this->engine->render('test.twig', ['a' => false, 'b' => false]));
    }

    #[Test]
    public function compile_for_loop(): void
    {
        file_put_contents($this->tmpViewsDir . '/test.twig', '{% for item in items %}{{ item }},{% endfor %}');
        $result = $this->engine->render('test.twig', ['items' => ['a', 'b', 'c']]);
        $this->assertStringContainsString('a,b,c', $result);
    }

    #[Test]
    public function compile_for_loop_with_key_value(): void
    {
        file_put_contents($this->tmpViewsDir . '/test.twig', '{% for k, v in data %}{{ k }}:{{ v }};{% endfor %}');
        $result = $this->engine->render('test.twig', ['data' => ['x' => 1, 'y' => 2]]);
        $this->assertStringContainsString('x', $result);
        $this->assertStringContainsString('1', $result);
    }

    #[Test]
    public function compile_set_statement(): void
    {
        file_put_contents($this->tmpViewsDir . '/test.twig', '{% set foo = 5 %}{{ foo }}');
        $result = $this->engine->render('test.twig', []);
        $this->assertStringContainsString('5', trim($result));
    }

    #[Test]
    public function compile_include_statement(): void
    {
        mkdir($this->tmpViewsDir . '/partials', 0777, true);
        file_put_contents($this->tmpViewsDir . '/partials/footer.twig', '<footer>end</footer>');
        file_put_contents($this->tmpViewsDir . '/test.twig', '{% include "partials/footer.twig" %}');
        $result = $this->engine->render('test.twig', []);
        $this->assertStringContainsString('<footer>end</footer>', $result);
    }

    #[Test]
    public function compile_block_statement(): void
    {
        file_put_contents($this->tmpViewsDir . '/base.twig', '<div>{% block content %}{% endblock %}</div>');
        file_put_contents($this->tmpViewsDir . '/child.twig', '{% extends "base.twig" %}{% block content %}hello{% endblock %}');
        $result = $this->engine->render('child.twig', []);
        $this->assertStringContainsString('<div>', $result);
        $this->assertStringContainsString('hello', $result);
    }

    #[Test]
    public function compile_extends_extracts_parent(): void
    {
        file_put_contents($this->tmpViewsDir . '/layout.twig', '<html>{% block body %}{% endblock %}</html>');
        file_put_contents($this->tmpViewsDir . '/page.twig', '{% extends "layout.twig" %}{% block body %}content{% endblock %}');
        $result = $this->engine->render('page.twig', []);
        $this->assertStringContainsString('<html>', $result);
        $this->assertStringContainsString('content', $result);
    }

    #[Test]
    public function compile_creates_valid_php_class(): void
    {
        $code = $this->compiler->compile('{{ text }}', 'MyCompiledTemplate', 'my.twig');
        $this->assertStringContainsString('final class MyCompiledTemplate', $code);
        $this->assertStringContainsString('extends Template', $code);
        $this->assertStringContainsString('protected function body(): string', $code);
    }

    #[Test]
    public function compile_filter_with_arguments(): void
    {
        file_put_contents($this->tmpViewsDir . '/test.twig', '{{ name | default("n/a") }}');
        $result = $this->engine->render('test.twig', ['name' => '']);
        $this->assertStringContainsString('n/a', $result);
    }

    #[Test]
    public function compile_variable_with_index_access(): void
    {
        file_put_contents($this->tmpViewsDir . '/test.twig', '{{ items.0 }}');
        $result = $this->engine->render('test.twig', ['items' => ['zero', 'one']]);
        $this->assertStringContainsString('zero', $result);
    }

    #[Test]
    public function compile_preserves_class_name(): void
    {
        $code = $this->compiler->compile('hello', 'UniqueTemplateName_123', 'unique.twig');
        $this->assertStringContainsString('final class UniqueTemplateName_123 extends Template', $code);
    }

    #[Test]
    public function compile_unknown_tag_throws(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->compiler->compile('{% foobar %}', 'BadTemplate', 'bad.twig');
    }

    #[Test]
    public function compile_literal_strings_preserved(): void
    {
        file_put_contents($this->tmpViewsDir . '/test.twig', 'hello world');
        $result = $this->engine->render('test.twig', []);
        $this->assertStringContainsString('hello world', $result);
    }

    #[Test]
    public function compile_literal_numbers_preserved(): void
    {
        file_put_contents($this->tmpViewsDir . '/test.twig', '{{ 42 }}');
        $result = $this->engine->render('test.twig', []);
        $this->assertStringContainsString('42', trim($result));
    }

    #[Test]
    public function for_loop_cleans_up_ctx_after_endfor(): void
    {
        file_put_contents($this->tmpViewsDir . '/test.twig', '{% for x in items %}{{ x }}{% endfor %}{{ x | default("gone") }}');
        $result = $this->engine->render('test.twig', ['items' => ['a']]);
        $this->assertStringContainsString('agone', $result);
    }

    #[Test]
    public function for_keyval_cleans_up_ctx(): void
    {
        file_put_contents($this->tmpViewsDir . '/test.twig', '{% for k, v in data %}{{ k }}{% endfor %}{{ k | default("x") }}{{ v | default("y") }}');
        $result = $this->engine->render('test.twig', ['data' => ['a' => 1]]);
        $this->assertStringContainsString('axy', $result);
    }

    #[Test]
    public function raw_block_preserves_twig_syntax(): void
    {
        file_put_contents($this->tmpViewsDir . '/test.twig', '{% raw %}<script>{{ x }}</script>{% endraw %}');
        $result = $this->engine->render('test.twig', []);
        $this->assertStringContainsString('<script>{{ x }}</script>', $result);
    }

    #[Test]
    public function raw_block_inside_template(): void
    {
        file_put_contents($this->tmpViewsDir . '/test.twig', 'before {% raw %}{{ verbatim }}{% endraw %} after');
        $result = $this->engine->render('test.twig', []);
        $this->assertStringContainsString('before {{ verbatim }} after', $result);
    }
}
