# Front Matter

[![Packagist](https://img.shields.io/packagist/v/webuni/front-matter.svg?style=flat-square)](https://packagist.org/packages/webuni/front-matter)
[![Quality Gate Status](https://sonarcloud.io/api/project_badges/measure?project=webuni_front-matter&metric=alert_status)](https://sonarcloud.io/summary/new_code?id=webuni_front-matter)
[![Coverage](https://sonarcloud.io/api/project_badges/measure?project=webuni_front-matter&metric=coverage)](https://sonarcloud.io/summary/new_code?id=webuni_front-matter)

The most universal Front matter (yaml, json, neon, toml) parser and dumper for PHP.
Front matter allows page-specific variables to be included at the top of a page.

## Installation

This library can be installed via Composer:

    composer require webuni/front-matter

## Usage

This library can parse all form of front matter:

<table>
<thead><tr><th>YAML (Neon)</th><th>TOML</th><th>Json</th></tr></thead>
<tbody><tr>
<td>

```markdown
---
foo: bar
---

# h1

paragraph
```

</td>
<td>

```markdown
+++
foo = bar
+++

# h1

paragraph
```

</td>
<td>

```markdown
{
"foo": "bar"
}

# h1

paragraph
```

</td>
</tr></tbody>
</table>

### Parse an arbitrary string

```php
<?php

$frontMatter = new \Webuni\FrontMatter\FrontMatter();

$document = $frontMatter->parse($string);

$data = $document->getData();
$content = $document->getContent();
```

### Check if a string has front matter

```php
<?php

$frontMatter = new \Webuni\FrontMatter\FrontMatter();

$hasFrontMatter = $frontMatter->exists($string);
```

### Twig loader

If you want to store metadata about twig template, e.g.:

```twig
{#---
title: Hello world
menu: main
weight: 20
---#}
{% extend layout.html.twig %}
{% block content %}
Hello world!
{% endblock %}
```

you can use `FrontMatterLoader`, that decorates another Twig loader:

```php
$frontMatter = \Webuni\FrontMatter\Twig\TwigCommentFrontMatter::create();
$loader = new \Twig\Loader\FilesystemLoader(['path/to/templates']);
$loader = new \Webuni\FrontMatter\Twig\FrontMatterLoader($frontMatter, $loader);

$twig = new \Twig\Environment($loader);
$content = $twig->render('template', []);
// rendered the valid twig template without front matter
```

It is possible to inject front matter to Twig template as variables:

```php
// …
$converter = \Webuni\FrontMatter\Twig\DataToTwigConvertor::vars();
$loader = new \Webuni\FrontMatter\Twig\FrontMatterLoader($frontMatter, $loader, $converter);
// …
```

Loaded Twig template has this code:

```twig
{% set title = "Hello world" %}
{% set menu = "main" %}
{% set weight = 20 %}
{% line 5 %}
{% extend layout.html.twig %}
{% block content %}
Hello world!
{% endblock %}
```

Available converters:

| Converter                                 | Twig                                                       |
| ----------------------------------------- | ---------------------------------------------------------- |
| `DataToTwigConvertor::nothing()`          |                                                            |
| `DataToTwigConvertor::vars()`             | `{% set key1 = value1 %}`                                  |
| `DataToTwigConvertor::vars(false)`        | `{% set key1 = key1 is defined ? key1 : value1 %}`         |
| `DataToTwigConvertor::var('name')`        | `{% set name = {key1: value1, key2: value2} %}`            |
| `DataToTwigConvertor::var('name', false)` | `{% set name = name is defined ? name : {key1: value1} %}` |

### Markdown

The most commonly used front matter is for markdown files:

```markdown
---
layout: post
title: I Love Markdown
tags:
    - test
    - example
---

# Hello World!
```

This library can be used with [league/commonmark](https://commonmark.thephpleague.com/):

```php
$frontMatter = new \Webuni\FrontMatter\FrontMatter();
$extension = new \Webuni\FrontMatter\Markdown\FrontMatterLeagueCommonMarkExtension($frontMatter);

$converter = new \League\CommonMark\CommonMarkConverter([]);
$converter->getEnvironment()->addExtension($extension);
$html = $converter->convertToHtml('markdown'); // html without front matter
```

## Alternatives

-   https://github.com/spatie/yaml-front-matter
-   https://github.com/ergebnis/front-matter
-   https://github.com/mnapoli/FrontYAML
-   https://github.com/Modularr/YAML-FrontMatter
