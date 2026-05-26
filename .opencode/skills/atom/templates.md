# Template Engine

Twig-like syntax compiled to PHP classes. Disk-cached, OPCache-friendly.

## Output & filters

```twig
{# comment #}
{{ user.name | e }}              # escape
{{ html | raw }}                 # raw (no escape)
{{ title | upper | raw }}        # chained
{{ count | default(0) }}         # with args
{{ data | json }}                # JSON encode
{{ items.0 }}                    # numeric index
```

Built-in filters: `escape`, `e`, `upper`, `lower`, `trim`, `length`, `nl2br`, `json`, `default`, `raw`.

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

{% set total = price * qty %}
{% include "partials/nav.twig" %}
{% raw %}<script>{{ not_parsed }}</script>{% endraw %}
```

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

## Custom filters & globals

```php
$app->view->addFilter('markdown', fn($v) => parseMarkdown($v));
$app->view->addGlobal('app_name', 'MyApp');
$app->view->addGlobal('year', date('Y'));
```
