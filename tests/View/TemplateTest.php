<?php
declare(strict_types=1);
namespace Atom\Tests\View;

use Atom\View\Engine;
use Atom\View\Template;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(Template::class)]
final class TemplateTest extends TestCase
{
    private Engine $engine;
    private string $tmpViewsDir;
    private string $tmpCacheDir;

    protected function setUp(): void
    {
        $this->tmpViewsDir = sys_get_temp_dir() . '/atom_tpl_' . uniqid();
        $this->tmpCacheDir = sys_get_temp_dir() . '/atom_tplc_' . uniqid();
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
    public function render_with_empty_blocks_outputs_body(): void
    {
        $tpl = new class($this->engine) extends Template {
            protected function body(): string { return 'hello'; }
        };
        $result = $tpl->render([]);
        $this->assertSame('hello', $result);
    }

    #[Test]
    public function render_block_returns_block_content(): void
    {
        $tpl = new class($this->engine) extends Template {
            protected function body(): string { return '<?= $this->renderBlock(\'sidebar\') ?>'; }
        };
        $result = $tpl->render(['sidebar' => '<nav>menu</nav>']);
        $this->assertStringContainsString('<nav>menu</nav>', $result);
    }

    #[Test]
    public function render_block_returns_empty_for_unknown_name(): void
    {
        $tpl = new class($this->engine) extends Template {
            protected function body(): string { return 'before<?= $this->renderBlock(\'missing\') ?>after'; }
        };
        $result = $tpl->render([]);
        $this->assertSame('beforeafter', $result);
    }

    #[Test]
    public function render_blocks_passed_override_default_blocks(): void
    {
        $tpl = new class($this->engine) extends Template {
            protected array $blocks = ['header' => 'default-header'];
            protected function body(): string { return '<?= $this->renderBlock(\'header\') ?><?= $this->renderBlock(\'footer\') ?>'; }
        };
        $result = $tpl->render(['header' => 'passed-header', 'footer' => 'passed-footer']);
        $this->assertStringContainsString('passed-header', $result);
        $this->assertStringContainsString('passed-footer', $result);
    }

    #[Test]
    public function render_include_delegates_to_engine(): void
    {
        file_put_contents($this->tmpViewsDir . '/partial.twig', 'partial-content');
        $tpl = new class($this->engine) extends Template {
            protected function body(): string { return '<?= $this->renderInclude(\'partial.twig\') ?>'; }
        };
        $result = $tpl->render([]);
        $this->assertStringContainsString('partial-content', $result);
    }

    #[Test]
    public function render_include_passes_context(): void
    {
        file_put_contents($this->tmpViewsDir . '/ctx.twig', '{{ name }}');
        $tpl = new class($this->engine, ['name' => 'John']) extends Template {
            protected function body(): string { return '<?= $this->renderInclude(\'ctx.twig\') ?>'; }
        };
        $result = $tpl->render([]);
        $this->assertStringContainsString('John', $result);
    }

    #[Test]
    public function render_with_custom_context_accesses_data(): void
    {
        $tpl = new class($this->engine, ['title' => 'Test']) extends Template {
            protected function body(): string { return "<?= \$this->ctx['title'] ?>"; }
        };
        $result = $tpl->render([]);
        $this->assertSame('Test', $result);
    }
}
