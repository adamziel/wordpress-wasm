## Site Transfer Protocol prototype

This is an exploration of what could become the WordPress
Site Transfer Protocol:

https://core.trac.wordpress.org/ticket/60375

The current version is focused on finding and rewriting
URLs as well as downloading any related assets.

Inspect tricky-input.html and tricky-output.html to
see what this repo can do today.

The next steps here would be to flesh out this README more,
start some issues and discussions, and define the minimal
v1 to ship and stress-test in Playground.

### Current status

-   URL rewriting works to perhaps the greatest extent it ever did.
    There are still corner-cases to discuss and performance optimizations
    to reap before making any of this a public API.
-   The URL parser requires PHP 8.1. This is fine for some Playground applications,
    but we'll more compatibility to get any of this into WordPress core. Also, that
    parser uses dozens of classes when we could likely rework it into something as
    self-contained like the WP_HTML_Tag_Processor.
-   Downloading the assets isn't implemented yet. It feels like there's
    no way to even start doing it without a state tracking table as there
    might be way more images to download than the PHP time limit allows.
    Perhaps that doesn't need to be solved to start using this with Markdown.

### Next steps

-   !!! Before making any further changes!!!
    Enforce WordPress formatting and coding standards so that we don't need to spend
    a week on adjusting everything later on.
    – Find a way to run this code in Playground
    – Find a way of loading the PHP files in Playground web – but without actually bundling
    the entire PHP library as a default and mandatory.
    – Make sure everytime a PHP file is updated on the disk, that change is reflected
    in Playground.
    – Run the unit tests in Playground CLI. - Add a CI job to run the unit tests in Playground CLI.
-   Add an extensive test suite.
    – Make the test suite ridiculously large, see URLParserWHATWGComplianceTests.
    – Cover thousands of cases. Not just simple combinations, but actually thousands of
    different, tricky ways in which the block markup may be expressed.
    – Cover migrations of every part of the URL (domain, path, protocol, ...)
    – Cover things that must not be migrated, e.g. URL-lookalike, CSS classes,
    subsyntaxes we can't correctly parse and escape such as the content of `<script>` tags, etc. - Think through different protocols, e.g. `mailto:CEO@mysite.com`. Should they be
    migrated? Or merely logged? Logged where? How will the user update them later on?
    Should they have a button to update them automatically? - Cover URL lookalikes, domains with invalid public suffix
    – Cover non-UTF8 encodings. Thoroughly think through what should actually happen
    when working with such data. What if the input has mixed encodings? Can we even
    touch it without breaking it?
    – Test the speed of WP_Migration_URL_In_Text_Processor. Consider switching to the
    detector/rewriter explored by @dmsnell. Later on this might also require using
    his UTF-8 decoder.
    – Make this PHP code compatible with PHP 7.2 – 8.3
    – Add extensibility so that specific plugins may, e.g., base64_decode() their block
    attributes before migrating the URLs.
    – Consider dropping the code from `src/wordpress-core`. Downside: Having a subset of
    WordPres core libraries baked in makes this project reusable as a library in other
    projects without a full-on WordPress core dependency. Potential solution: Proceed
    with a WordPress core dependency, then add a build step, e.g. using the Box library
    for producing .phar files, that would pull just the parts we need from WordPress core.
    Danger: We'll encode the WordPress dependency in the DNA of this project and will have
    a hard time migrating away from it later on.
-   ... think through what we're still missing ...
    – Bootstrap a new WordPress plugin in this repo, use the "Sandbox site" plugin as a starting point.
    _ Give it only a single feature: "Clone my site into a local directory"
    _ Copy all the files to another OPFS directory
    _ Migrate the URLs in all the migrated SQL data (best effort for now, guess what
    is the content type. Eventually the plugin authors will need to declare a schema
    and custom handlers – otherwise we won't know what is block markup and what is JSON)
    _ Keep track of progress
    _ If timed out, run another HTTP request to `migrate.php` (or so) that will continue
    copying the site exactly where the first request stopped.
    _ Memory quota: No more than 5MB of extra memory used after loading all the PHP files.
    This will enforce streaming everything, avoiding buffering, generally good design
    decisions.
-   Use that plugin to implement a "Duplicate site" button in Playground web.
    -   Mount another OPFS directory in, say, `/cloned-site`
    -   Run the clone process until it's complete. Display progress. Restart on timeouts and
        crashes.
    -   Don't download media files from URLs. Just copy them from the local site.
    -   Treat that new OPFS directory as a root for the new, cloned site.
-   Discuss applying this entire logic for importing WXR files or bundling them as WP.zip exports
    with all the media files and other types of static assets baked in.

#### Processing tricky inputs

When this code is fed into the migrator:

```html
<!-- wp:paragraph -->
<p>
	<!-- Inline URLs are migrated -->
	🚀-science.com/science has the best scientific articles on the internet! We're also available via the punycode URL:

	<!-- No problem handling HTML-encoded punycode URLs with urlencoded characters in the path -->
	&#104;ttps://xn---&#115;&#99;ience-7f85g.com/%73%63ience/.

	<!-- Correctly ignores similar–but–different URLs -->
	This isn't migrated: https://🚀-science.comcast/science <br />
	Or this: super-🚀-science.com/science
</p>
<!-- /wp:paragraph -->

<!-- Block attributes are migrated without any issue -->
<!-- wp:image {"src": "https:\/\/\ud83d\ude80-\u0073\u0063ience.com/%73%63ience/wp-content/image.png"} -->
<!-- As are URI HTML attributes -->
<img src="&#104;ttps://xn---&#115;&#99;ience-7f85g.com/science/wp-content/image.png" />
<!-- /wp:image -->

<!-- Classes are not migrated. -->
<span class="https://🚀-science.com/science"></span>
```

This actual output is produced:

```html
<!-- wp:paragraph -->
<p>
	<!-- Inline URLs are migrated -->
	science.wordpress.com has the best scientific articles on the internet! We're also available via the punycode URL:

	<!-- No problem handling HTML-encoded punycode URLs with urlencoded characters in the path -->
	https://science.wordpress.com/.

	<!-- Correctly ignores similar–but–different URLs -->
	This isn't migrated: https://🚀-science.comcast/science <br />
	Or this: super-🚀-science.com/science
</p>
<!-- /wp:paragraph -->

<!-- Block attributes are migrated without any issue -->
<!-- wp:image {"src":"https:\/\/science.wordpress.com\/wp-content\/image.png"} -->
<!-- As are URI HTML attributes -->
<img src="https://science.wordpress.com/wp-content/image.png" />
<!-- /wp:image -->

<!-- Classes are not migrated. -->
<span class="https://🚀-science.com/science"></span>
```

### Related projects

-   https://github.com/WordPress/wordpress-playground/discussions/1524
-   https://github.com/adamziel/wxr-normalize
-   https://github.com/WordPress/blueprints/pull/52
-   https://github.com/adamziel/playground-docs-workflow
-   https://github.com/WordPress/blueprints-library/blob/trunk/src/WordPress/AsyncHttp/Client.php
-   https://github.com/WordPress/wordpress-develop/pull/6713

### What code do I run?

To migrate domain names from tricky-input.html, run:

```shell
php bin/rewrite-urls.php migrate_urls --file ./tricky-input.html --current-site-url https://🚀-science.com/science --new-site-url https://science.wordpress.com
```

To list all the URLs found in that file, run:

```shell
php bin/rewrite-urls.php list_urls --file ./tricky-input.html
```
