<?php
declare(strict_types=1);
namespace Atom\View;

use Atom\Cache\Cache;
use Atom\Constants;

final class Engine
{
    private string $cacheDir;
    /** @var array<string, callable> */
    private array $filters;
    /** @var array<string, mixed> */
    private array $globals = [];
    private ?Cache $cache = null;
    private string $cacheStrategy = 'file';

    public function __construct(
        private readonly string $viewsDir,
        string $cacheDir = __DIR__ . '/../../storage/views',
        ?Cache $cache = null,
        string $cacheStrategy = 'file',
    ) {
        $this->cacheDir = $cacheDir;
        $this->filters  = $this->defaultFilters();
        $this->cache = $cache;
        $this->cacheStrategy = $cacheStrategy;
    }

    public function addFilter(string $name, callable $fn): self { $this->filters[$name] = $fn; return $this; }
    public function addGlobal(string $name, mixed $value): self { $this->globals[$name] = $value; return $this; }

    public function render(string $template, array $data = []): string
    {
        $cls = $this->load($template);
        /** @var Template $tpl */
        $tpl = new $cls($this, [...$this->globals, ...$data]);
        return $tpl->render([]);
    }

    public function getFilter(string $name): callable
    {
        return $this->filters[$name] ?? throw new \RuntimeException("Unknown filter: {$name}");
    }

    public function load(string $template): string
    {
        $viewsReal = realpath($this->viewsDir);
        if ($viewsReal === false) {
            throw new \RuntimeException("View Engine: views directory not found: {$this->viewsDir}");
        }
        $viewsReal = strtr($viewsReal, '\\', '/');
        $src  = $this->viewsDir . '/' . ltrim($template, '/');
        $real = realpath($src);
        if ($real === false || !str_starts_with(strtr($real, '\\', '/'), $viewsReal . '/')) {
            throw new \RuntimeException("Template not found: {$template}");
        }
        $hash = sha1($real);
        $cls  = 'AtomTemplate_' . $hash;
        $file = $this->cacheDir . '/' . $hash . '.php';

        $needsCompile = true;
        if ($this->cacheStrategy === 'cache' && $this->cache !== null) {
            $cachedMtime = $this->cache->get("tpl_mtime:{$hash}");
            $needsCompile = $cachedMtime === null || $cachedMtime < filemtime($real) || !is_file($file);
        } else {
            $needsCompile = !is_file($file) || filemtime($file) < filemtime($real);
        }

        if ($needsCompile) {
            $compiler = new Compiler($this);
            $code = $compiler->compile(file_get_contents($real), $cls, $template);
            $fileDir = dirname($file);
            if (!is_dir($fileDir) && !@mkdir($fileDir, Constants::DIR_PERMISSIONS, true) && !is_dir($fileDir)) {
                throw new \RuntimeException("View Engine: cannot create cache directory '{$fileDir}'");
            }
            if (file_put_contents($file, $code) === false) {
                throw new \RuntimeException("View Engine: failed to write compiled template '{$file}'");
            }
            if ($this->cacheStrategy === 'cache' && $this->cache !== null) {
                $this->cache->set("tpl_mtime:{$hash}", filemtime($real));
            }
        }
        if (!class_exists($cls, false)) {
            require $file;
        }
        return $cls;
    }

    /** @return array<string,callable> */
    private function defaultFilters(): array
    {
        return [
            'escape' => 'htmlspecialchars',
            'e'      => 'htmlspecialchars',
            'upper'  => 'strtoupper',
            'lower'  => 'strtolower',
            'trim'   => 'trim',
            'length' => fn($v) => is_countable($v) ? count($v) : mb_strlen((string) $v),
            'nl2br'  => fn($v) => nl2br(htmlspecialchars((string) $v)),
            'json'   => fn($v) => json_encode($v, JSON_UNESCAPED_UNICODE),
            'default'=> fn($v, $d = '') => ($v !== null && $v !== false && $v !== '' && $v !== []) ? $v : $d,
            'raw'    => fn($v) => $v,
        ];
    }
}
