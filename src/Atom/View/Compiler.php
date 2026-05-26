<?php
declare(strict_types=1);
namespace Atom\View;

use Atom\Support\Regex;

final readonly class Compiler
{
    public function __construct(private Engine $engine) {}

    public function compile(string $source, string $className, string $selfName): string
    {
        // 1. Удаляем комментарии {# ... #}
        $src = Regex::replace('~\{#.*?#\}~s', '', $source);

        // 2. Вытаскиваем extends и блоки
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

        // 3. Компилируем оставшееся тело (для шаблонов без extends)
        $body = $this->compileBody($src);

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
        preg_match_all('#(\{\{.*?\}\}|\{%.*?%\})|([^{}]+|\{|\})#s', $src, $m, PREG_SET_ORDER);

        $out = '';
        foreach ($m as $tok) {
            $t = $tok[0];
            if (str_starts_with($t, '{{')) {
                $expr = trim($t, "{} \t\n\r");
                $out  .= '<?=' . $this->compileExpression($expr, true) . '?>';
            } elseif (str_starts_with($t, '{%')) {
                $out .= $this->compileTag(trim($t, "{%} \t\n\r"));
            } else {
                $out .= $t;
            }
        }
        return $out;
    }

    private function compileExpression(string $expr, bool $autoEscape = false): string
    {
        // Фильтры через "|" — транслируем в цепочку вызовов (pipe-like)
        $parts = Regex::split('#\s*\|\s*#', $expr);
        $head  = array_shift($parts);
        $code  = $this->compileVariable($head);

        foreach ($parts as $filter) {
            // filter или filter(arg1,arg2)
            if ($fm = Regex::match('#^([a-zA-Z_][a-zA-Z0-9_]*)(?:\((.*)\))?$#s', trim($filter))) {
                $name = $fm[1];
                $args = isset($fm[2]) && $fm[2] !== '' ? ',' . $fm[2] : '';
                $code = "\$this->engine->getFilter('{$name}')({$code}{$args})";
            }
        }
        // Авто-экранирование только для вывода и только если нет фильтров
        if ($autoEscape && empty($parts)) {
            $code = "htmlspecialchars((string)({$code}), ENT_QUOTES, 'UTF-8')";
        }
        return $code;
    }

    private function compileVariable(string $v): string
    {
        $v = trim($v);
        if (preg_match('#^([\'"]).*\1$#s', $v) || preg_match('#^-?\d+(\.\d+)?$#', $v) || in_array($v, ['true','false','null'], true)) {
            return $v;
        }
        // a.b.c -> $this->ctx['a']['b']['c']
        // a.0 -> $this->ctx['a'][0]
        $parts = explode('.', $v, 2);
        if (count($parts) === 1) {
            return "\$this->ctx['{$parts[0]}']";
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
            (bool) preg_match('#^if\s+(.+)$#s', $tag, $m)    => '<?php if (' . $this->compileExpression($m[1]) . '): ?>',
            (bool) preg_match('#^elseif\s+(.+)$#s', $tag, $m) => '<?php elseif (' . $this->compileExpression($m[1]) . '): ?>',
            $tag === 'else'                                    => '<?php else: ?>',
            $tag === 'endif'                                   => '<?php endif; ?>',

            (bool) preg_match('#^for\s+(\w+)\s+in\s+(.+)$#s', $tag, $m) =>
                '<?php foreach ((' . $this->compileExpression($m[2]) . ') ?? [] as $' . $m[1] . '): $this->ctx[\'' . $m[1] . '\'] = $' . $m[1] . '; ?>',
            (bool) preg_match('#^for\s+(\w+),\s*(\w+)\s+in\s+(.+)$#s', $tag, $m) =>
                '<?php foreach ((' . $this->compileExpression($m[3]) . ') ?? [] as $' . $m[1] . ' => $' . $m[2] . '): $this->ctx[\'' . $m[1] . '\'] = $' . $m[1] . '; $this->ctx[\'' . $m[2] . '\'] = $' . $m[2] . '; ?>',
            $tag === 'endfor' => '<?php endforeach; ?>',

            (bool) preg_match('#^set\s+(\w+)\s*=\s*(.+)$#s', $tag, $m) =>
                '<?php $this->ctx[\'' . $m[1] . '\'] = ' . $this->compileExpression($m[2]) . '; ?>',

            (bool) preg_match('#^include\s+[\'"]([^\'"]+)[\'"]#', $tag, $m) =>
                '<?= $this->renderInclude(' . var_export($m[1], true) . ') ?>',

            (bool) preg_match('#^block\s+(\w+)$#', $tag, $m) => '<?= $this->renderBlock(' . var_export($m[1], true) . ') ?>',
            $tag === 'endblock' => '',

            default => throw new \RuntimeException("Unknown tag: {$tag}"),
        };
    }

    private function q(string $s): string { return var_export($s, true); }
}
