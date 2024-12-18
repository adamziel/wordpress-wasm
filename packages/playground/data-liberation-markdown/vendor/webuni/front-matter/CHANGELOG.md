# CHANGELOG

## 2.0.0 (2024-02-22)

-   Enable Symfony 7
-   Remove support for CommonMark 1
-   Integrate `exists` method into `FrontMatterInterface`
-   Add factory method `FrontMatterChain::create`

    1.5.0 (2022-12-12)

---

-   Remove indentation from front matter
-   Add front matter chain
-   Add support for Pug comment as front matter
-   Change line number preservation in Twig templates compatible with other template engines
-   Remove support for PHP 7.2 and 7.3

    1.4.0 (2022-09-30)

---

-   Enable CommonMark 2
-   Enable Symfony 6
-   Improve YamlProcessor with yaml configuration

    1.3.0 (2021-06-14)

---

-   Add `{% line %}` tag to preserve line numbers in Twig templates
-   Inject front matter data to Twig templates as template variables
-   Add support for Twig comment as front matter

## 1.2.0 (2020-11-04)

-   Add CommonMark extension
-   Add FrontMatterExistsInterface
-   Add fluent interface for Document
-   Minimal PHP 7.2
-   Minimal Twig 3
-   Enable PHP 8
-   Enable Symfony 5

## 1.1.0 (2018-03-20)

-   Update dependencies
-   Enable Symfony 4
-   Fix Twig loader

## 1.0.0 (2016-12-30)

-   Add predefined formats (https://gohugo.io/content/front-matter/)
-   Add support for NEON
-   Add support for Toml
-   Enable Symfony 3.0

## 0.1.0 (2015-08-11)

-   Add support for Json
-   Add support for Yaml
-   Initial implementation
