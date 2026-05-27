<?php
declare(strict_types=1);
namespace Atom\View;

use Atom\Support\Regex;

final class Compiler
{
    /** @var list<array{0:string,1:string|null}> */
    private array $forStack = [];

    public function __construct(private Engine $engine) {}

    public function compile(string $source, string $className, string $selfName): string
    {
        // Extract {% raw %}...{% endraw %} blocks before any processing
        $rawMap = [];
        $src = Regex::replace(
            '#\{%\s*raw\s*%\}(.*?)\{%\s*endraw\s*%\}#s',
            function (array $m) use (&$rawMap): string {
                $k = "\x00RAW" . count($rawMap) . "\x00";
                $rawMap[$k] = $m[1];
                return $k;
            },
            $source,
        );

        $src = Regex::replace('~\{#.*?#\}~s', '', $src);

        $parent = null;
        if ($m = Regex::match('#\{%\s*extends\s+[\'"]([^\'"]+)[\'"]\s*%\}#', $src)) {
            $parent = $m[1];
            $src = Regex::replace('#\{%\s*extends\s+[\'"][^\'"]+[\'"]\s*%\}#', '', $src);
        }

        $blocks = [];
        $src = Regex::replace(
            '#\{%\s*block\s+(\w+)\s*%\}(.*?)\{%\s*endblock\s*%\}#s',
            function ($m) use (&$blocks) {
                $blocks[$m[1]] = $this->compileBody($m[2]);
                return "{% block {$m[1]} %}";
            },
            $src,
        );

        $body = $this->compileBody($src);
        $body = $rawMap !== [] ? strtr($body, $rawMap) : $body;

        $parentExpr = $parent === null ? 'null' : var_export($parent, true);
        $blocksPhp  = var_export($blocks, true);

        return <<<PHP
        <?php
        use Atom\\View\\Template;
        final class {$className} extends Template {
            protected ?string \$parent = {$parentExpr};
            protected string \$selfName = {$this->q($selfName)};
            protected array \$blocks = {$blocksPhp};
            protected function body(): string { return {$this->q($body)}; }
        }
        PHP;
    }

    private function compileBody(string $src): string
    {
        $out = '';
        $len = strlen($src);
        $pos = 0;

        while ($pos < $len) {
            $c = $src[$pos];

            if ($c === '{' && $pos + 1 < $len) {
                $next = $src[$pos + 1];
                if ($next === '{') {
                    $depth = 1;
                    $start = $pos;
                    $pos += 2;
                    while ($pos < $len && $depth > 0) {
                        if ($src[$pos] === '{' && isset($src[$pos + 1]) && $src[$pos + 1] === '{') { $depth++; $pos++; }
                        elseif ($src[$pos] === '}' && isset($src[$pos + 1]) && $src[$pos + 1] === '}') { $depth--; $pos++; }
                        $pos++;
                    }
                    $expr = substr($src, $start + 2, $pos - $start - 4);
                    $out .= '<?=' . $this->compileExpression(trim($expr), true) . '?>';
                    continue;
                }
                if ($next === '%') {
                    $end = strpos($src, '%}', $pos + 2);
                    if ($end !== false) {
                        $tag = substr($src, $pos + 2, $end - $pos - 2);
                        $out .= $this->compileTag(trim($tag));
                        $pos = $end + 2;
                        continue;
                    }
                }
            }

            $out .= $c;
            $pos++;
        }

        return $out;
    }

    private function compileExpression(string $expr, bool $autoEscape = false): string
    {
        $parts = Regex::split('#\s*\|\s*#', $expr);
        $head  = array_shift($parts);
        if ($head === null) {
            return "''";
        }
        $code  = $this->compileVariable($head);

        $filterNames = [];
        foreach ($parts as $filter) {
            if ($fm = Regex::match('#^([a-zA-Z_][a-zA-Z0-9_]*)(?:\((.*)\))?$#s', trim($filter))) {
                $name = $fm[1];
                $filterNames[] = $name;
                $args = isset($fm[2]) && $fm[2] !== '' ? ',' . $fm[2] : '';
                $code = "\$this->engine->getFilter('{$name}')({$code}{$args})";
            }
        }
        if ($autoEscape && !array_intersect($filterNames, ['raw', 'escape', 'e', 'json', 'nl2br'])) {
            $code = "htmlspecialchars((string)({$code}), ENT_QUOTES, 'UTF-8')";
        }
        return $code;
    }

    private function compileVariable(string $v): string
    {
        $v = trim($v);
        if (Regex::match('#^([\'"]).*\1$#s', $v) !== null || Regex::match('#^-?\d+(\.\d+)?$#', $v) !== null || in_array($v, ['true','false','null'], true)) {
            return $v;
        }
        $parts = explode('.', $v, 2);
        if (count($parts) === 1) {
            return "(\$this->ctx['{$parts[0]}'] ?? null)";
        }
        $result = "\$this->ctx['{$parts[0]}']";
        $result .= Regex::replace(
            '#\.(\w+)#',
            fn($m) => ctype_digit($m[1]) ? "[{$m[1]}]" : "['{$m[1]}']",
            '.' . $parts[1],
        );
        return $result;
    }

    private function compileTag(string $tag): string
    {
        return match (true) {
            ($m = Regex::match('#^if\s+(.+)$#s', $tag)) !== null    => '<?php if (' . $this->compileExpression($m[1]) . '): ?>',
            ($m = Regex::match('#^elseif\s+(.+)$#s', $tag)) !== null => '<?php elseif (' . $this->compileExpression($m[1]) . '): ?>',
            $tag === 'else'                                           => '<?php else: ?>',
            $tag === 'endif'                                          => '<?php endif; ?>',

            ($m = Regex::match('#^for\s+(\w+)\s+in\s+(.+)$#s', $tag)) !== null => $this->compileFor($m[1], $m[2]),
            ($m = Regex::match('#^for\s+(\w+),\s*(\w+)\s+in\s+(.+)$#s', $tag)) !== null => $this->compileForKeyVal($m[1], $m[2], $m[3]),
            $tag === 'endfor' => $this->compileEndfor(),

            ($m = Regex::match('#^set\s+(\w+)\s*=\s*(.+)$#s', $tag)) !== null =>
                '<?php $this->ctx[\'' . $m[1] . '\'] = ' . $this->compileExpression($m[2]) . '; ?>',

            ($m = Regex::match('#^include\s+[\'"]([^\'"]+)[\'"]#', $tag)) !== null =>
                '<?= $this->renderInclude(' . var_export($m[1], true) . ') ?>',

            ($m = Regex::match('#^block\s+(\w+)$#', $tag)) !== null => '<?= $this->renderBlock(' . var_export($m[1], true) . ') ?>',
            $tag === 'endblock' => '',
            $tag === 'raw' => '',
            $tag === 'endraw' => '',

            default => throw new \RuntimeException("Unknown tag: {$tag}"),
        };
    }

    private function compileFor(string $var, string $expr): string
    {
        $this->forStack[] = [$var, null];
        return '<?php foreach ((' . $this->compileExpression($expr) . ') ?? [] as $' . $var . '): $this->ctx[\'' . $var . '\'] = $' . $var . '; ?>';
    }

    private function compileForKeyVal(string $key, string $val, string $expr): string
    {
        $this->forStack[] = [$key, $val];
        return '<?php foreach ((' . $this->compileExpression($expr) . ') ?? [] as $' . $key . ' => $' . $val . '): $this->ctx[\'' . $key . '\'] = $' . $key . '; $this->ctx[\'' . $val . '\'] = $' . $val . '; ?>';
    }

    private function compileEndfor(): string
    {
        if ($this->forStack === []) {
            throw new \RuntimeException('Unexpected {% endfor %} without matching {% for %}');
        }
        $vars = array_pop($this->forStack);
        $cleanup = '';
        foreach ($vars as $v) {
            if ($v !== null) $cleanup .= "unset(\$this->ctx['{$v}']);";
        }
        return '<?php endforeach; ' . $cleanup . ' ?>';
    }

    private function q(string $s): string { return var_export($s, true); }
}
