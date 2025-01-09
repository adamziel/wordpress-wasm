---
sidebar_position: 5
slug: /developers/apis/query-api
---

# Query API

WordPress Playground exposes a simple API that you can use to configure the Playground in the browser.

It works by passing configuration options as query parameters to the Playground URL. For example, to install the pendant theme, you would use the following URL:

```text
https://playground.wordpress.net/?theme=pendant
```

You can go ahead and try it out. The Playground will automatically install the theme and log you in as an admin. You may even embed this URL in your website using an `<iframe>` tag:

```html
<iframe src="https://playground.wordpress.net/?theme=pendant"></iframe>
```

## Available options

| Option                   | Default Value         | Description                                                                                                                                                                                                                                                                                                                                                                      |
| ------------------------ | --------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `php`                    | `8.0`                 | Loads the specified PHP version. Accepts `7.0`, `7.1`, `7.2`, `7.3`, `7.4`, `8.0`, `8.1`, `8.2`, `8.3`, `8.4` or `latest`.                                                                                                                                                                                                                                                       |
| `wp`                     | `latest`              | Loads the specified WordPress version. Accepts the last three major WordPress versions. As of June 1, 2024, that's `6.3`, `6.4`, or `6.5`. You can also use the generic values `latest`, `nightly`, or `beta`.                                                                                                                                                                   |
| `blueprint-url`          |                       | The URL of the Blueprint that will be used to configure this Playground instance.                                                                                                                                                                                                                                                                                                |
| `networking`             | `no`                  | Enables or disables the networking support for Playground. Accepts `yes` or `no`.                                                                                                                                                                                                                                                                                                |
| `plugin`                 |                       | Installs the specified plugin. Use the plugin name from the WordPress Plugins Directory URL. For example, if the URL is `https://wordpress.org/plugins/wp-lazy-loading/`, the plugin name would be `wp-lazy-loading`. You can pre-install multiple plugins by saying `plugin=coblocks&plugin=wp-lazy-loading&…`. Installing a plugin automatically logs the user in as an admin. |
| `theme`                  |                       | Installs the specified theme. Use the theme name from the WordPress Themes Directory URL. For example, if the URL is `https://wordpress.org/themes/disco/`, the theme name would be `disco`. Installing a theme automatically logs the user in as an admin.                                                                                                                      |
| `url`                    | `/wp-admin/`          | Load the specified initial WordPress page in this Playground instance.                                                                                                                                                                                                                                                                                                           |
| `mode`                   | `browser-full-screen` | Determines how the WordPress instance is displayed. Either wrapped in a browser UI or full width as a seamless experience. Accepts `browser-full-screen`, or `seamless`.                                                                                                                                                                                                         |
| `lazy`                   |                       | Defer loading the Playground assets until someone clicks on the "Run" button. Does not accept any values. If `lazy` is added as a URL parameter, loading will be deferred.                                                                                                                                                                                                       |
| `login`                  | `yes`                 | Log the user in as an admin. Accepts `yes` or `no`.                                                                                                                                                                                                                                                                                                                              |
| `multisite`              | `no`                  | Enables the WordPress multisite mode. Accepts `yes` or `no`.                                                                                                                                                                                                                                                                                                                     |
| `import-site`            |                       | Imports site files and database from a ZIP file specified by a URL.                                                                                                                                                                                                                                                                                                              |
| `import-wxr`             |                       | Imports site content from a WXR file specified by a URL. It uses the WordPress Importer plugin, so the default admin user must be logged in.                                                                                                                                                                                                                                     |
| `site-slug`              |                       | Selects which site to load from browser storage.                                                                                                                                                                                                                                                                                                                                 |
| `language`               | `en_US`               | Sets the locale for the WordPress instance. This must be used in combination with `networking=yes` otherwise WordPress won't be able to download translations.                                                                                                                                                                                                                   |
| `core-pr`                |                       | Installs a specific https://github.com/WordPress/wordpress-develop core PR. Accepts the PR number. For example, `core-pr=6883`.                                                                                                                                                                                                                                                  |
| `gutenberg-pr`           |                       | Installs a specific https://github.com/WordPress/gutenberg PR. Accepts the PR number. For example, `gutenberg-pr=65337`.                                                                                                                                                                                                                                                         |
| `if-stored-site-missing` |                       | Indicates how to handle the scenario where the `site-slug` parameter identifies a site that does not exist. Use `if-stored-site-missing=prompt` to indicate that the user should be asked whether they would like to save a new site with the specified `site-slug`.                                                                                                             |

For example, the following code embeds a Playground with a preinstalled Gutenberg plugin and opens the post editor:

```html
<iframe src="https://playground.wordpress.net/?plugin=gutenberg&url=/wp-admin/post-new.php&mode=seamless"> </iframe>
```

:::info CORS policy

To import files from a URL, such as a site zip package, they must be served with `Access-Control-Allow-Origin` header set. For reference, see: [Cross-Origin Resource Sharing (CORS)](https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS#the_http_response_headers).

:::

## GitHub Export Options

The following additional query parameters may be used to pre-configure the GitHub export form:

-   `gh-ensure-auth`: If set to `yes`, Playground will display a modal to ensure the
    user is authenticated with GitHub before proceeding.
-   `ghexport-repo-url`: The URL of the GitHub repository to export to.
-   `ghexport-pr-action`: The action to take when exporting (create or update).
-   `ghexport-playground-root`: The root directory in the Playground to export from.
-   `ghexport-repo-root`: The root directory in the repository to export to.
-   `ghexport-content-type`: The content type of the export (plugin, theme, wp-content, custom-paths).
-   `ghexport-plugin`: Plugin path. When the content type is `plugin`, pre-select the plugin to export.
-   `ghexport-theme`: Theme directory name. When the content type is `theme`, pre-select the theme to export.
-   `ghexport-path`: A path relative to `ghexport-playground-root`. Can be provided multiple times. When the
    content type is `custom-paths`, it pre-populates the list of paths to export.
-   `ghexport-commit-message`: The commit message to use when exporting.
-   `ghexport-allow-include-zip`: Whether to offer an option to include a zip file in the GitHub
    export (yes, no). Optional. Defaults to `yes`.
