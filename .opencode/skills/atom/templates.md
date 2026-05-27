# Template Engine

Twig-like syntax compiled to PHP classes. Disk-cached, OPCache-friendly.

## Output & filters

```twig
{# comment #}
{{ user.name | e }}
{{ html | raw }}
{{ title | upper | raw }}
{{ count | default(0) }}
{{ data | json }}
{{ items.0 }}
```

Built-in filters: `escape`, `e`, `upper`, `lower`, `trim`, `length`, `nl2br`, `json`, `default`, `raw`.

The `default` filter handles `null`, `false`, `''`, and `[]` as empty values — showing the fallback. Nested `{{ }}` braces (e.g., `{{ func({a: 1}) }}`) are handled correctly.

## Control flow

```twig
{% if user.admin %}
  <p>Admin</p>
{% elseif user.active %}
  <p>Active</p>
{% else %}
  <p>Inactive</p>
{% endif %}

{% for item in items %}
  <li>{{ item | e }}</li>
{% endfor %}

{% for key, val in data %}
  {{ key }}: {{ val }}
{% endfor %}
```

For-loop variables are cleaned up after `endfor`. Shadowed variables (same name as outer context) are saved and restored.

```twig
{{ title }}                      {# "My Page" #}
{% for title in items %}
  [{{ title }}]                  {# iterated values #}
{% endfor %}
{{ title }}                      {# "My Page" — restored #}
```

## Set, include, raw

```twig
{% set total = price * qty %}
{% include "partials/nav.twig" %}
{% raw %}<script>{{ not_parsed }}</script>{% endraw %}
```

Unclosed tags (missing `%}`) throw `RuntimeException`.

## Inheritance

```twig
{# layout.twig #}
<html>
<head><title>{% block title %}Default{% endblock %}</title></head>
<body>{% block body %}{% endblock %}</body>
</html>

{# page.twig #}
{% extends "layout.twig" %}
{% block title %}{{ page.title | e }}{% endblock %}
{% block body %}<p>{{ page.content | raw }}</p>{% endblock %}
```

Template recompilation: source change detection via `filemtime`. In-process recompilation uses `class_exists` guard + `require` (no `require_once` staleness).

## Custom filters & globals

```php
$app->view->addFilter('markdown', fn($v) => parseMarkdown($v));
$app->view->addGlobal('app_name', 'MyApp');
$app->view->addGlobal('year', date('Y'));
```
