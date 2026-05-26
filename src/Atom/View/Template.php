<?php
declare(strict_types=1);
namespace Atom\View;

abstract class Template
{
    protected ?string $parent = null;
    protected string $selfName = '';
    protected array $blocks = [];

    public function __construct(
        protected readonly Engine $engine,
        protected array $ctx = [],
    ) {}

    abstract protected function body(): string;

    public function render(array $blocks): string
    {
        $blocks = $blocks + $this->blocks;

        if ($this->parent !== null) {
            $parentCls = $this->engine->load($this->parent);
            /** @var Template $parent */
            $parent = new $parentCls($this->engine, $this->ctx);
            return $parent->render($blocks);
        }

        $this->blocks = $blocks;
        ob_start();
        eval('?>' . $this->body());
        return ob_get_clean();
    }

    protected function renderBlock(string $name): string
    {
        if (!isset($this->blocks[$name])) return '';
        ob_start();
        eval('?>' . $this->blocks[$name]);
        return ob_get_clean();
    }

    protected function renderInclude(string $name): string
    {
        return $this->engine->render($name, $this->ctx);
    }
}
