# WordPress Playground and PHP WASM (WebAssembly)

[Project Page](https://developer.wordpress.org/playground/) | [Live demo](https://playground.wordpress.net/) | [Documentation and API Reference](https://wordpress.github.io/wordpress-playground/)

[WordPress Playground](https://github.com/WordPress/wordpress-playground) is an experimental in-browser WordPress that runs without a PHP server thanks to the magic of WebAssembly.

![](https://raw.githubusercontent.com/wordpress/wordpress-playground/trunk/demo.png)

## Why is WordPress Playground useful?

WordPress Playground exists to make WordPress instantly accessible for users, learners, extenders, and contributors by building foundational software tools developers can use to create interactive, zero-setup, JavaScript applications with WordPress.

Playground aims to facilitate:

-   Learning WordPress Through Exploration
-   Learning WordPress Development Through Writing Code
-   Instant access to WordPress ecosystem

Learn more about the [vision](https://github.com/WordPress/wordpress-playground/issues/472) and the [roadmap](https://github.com/WordPress/wordpress-playground/issues/525).

## Getting started

WordPress Playground has a [live demo](https://developer.wordpress.org/playground/demo/) available.

You can embed WordPress Playground in your project via an `<iframe>` – find out how in the [documentation](https://wordpress.github.io/wordpress-playground/). **Note the embed is experimental and may break or change without a warning.**

You can connect to the Playground using the JavaScript client. Here's an example of how to do it in the browser using an `iframe` HTML element and the `startPlaygroundWeb` function from the `@wp-playground/client` package.

**index.html**:

```html
<!DOCTYPE html>
<iframe id="wp-playground" style="width: 1200px; height: 800px"></iframe>
<script type="module">
	import { startPlaygroundWeb } from 'https://playground.wordpress.net/client/index.js';

	const client = await startPlaygroundWeb({
		iframe: document.getElementById('wp-playground'),
		remoteUrl: `https://playground.wordpress.net/remote.html`,
		blueprint: {
			landingPage: '/wp-admin/',
			preferredVersions: {
				php: '8.0',
				wp: 'latest',
			},
			steps: [
				{
					step: 'login',
					username: 'admin',
					password: 'password',
				},
				{
					step: 'installPlugin',
					pluginData: {
						resource: 'wordpress.org/plugins',
						slug: 'friends',
					},
				},
			],
		},
	});

	const response = await client.run({
		// wp-load.php is only required if you want to interact with WordPress.
		code: '<?php require_once "/wordpress/wp-load.php"; $posts = get_posts(); echo "Post Title: " . $posts[0]->post_title;',
	});
	console.log(response.text);
</script>
```

## WordPress Playground Tools

WordPress [Playground Tools](https://github.com/WordPress/playground-tools) are independent applications built on top of WordPress Playground. They are located in a different repository: [WordPress/playground-tools](https://github.com/WordPress/playground-tools).

These tools include:

-   [Interactive Code Block for Gutenberg](https://github.com/WordPress/playground-tools/tree/trunk/packages/interactive-code-block/#readme)
-   [WordPress Playground for Visual Studio Code](https://github.com/WordPress/playground-tools/tree/trunk/packages/vscode-extension/#readme)
-   [wp-now](https://github.com/WordPress/playground-tools/tree/trunk/packages/wp-now/#readme) CLI local development environment.

## Cloning WordPress Playground repo

The vanilla `git clone` command will take ages. Here's a faster alternative that will
only pull the latest revision of the trunk branch:

```
git clone -b trunk --single-branch --depth 1 --recurse-submodules https://github.com/WordPress/wordpress-playground.git
```

## Running WordPress Playground locally

You also can run WordPress Playground locally as follows:

```bash
git clone -b trunk --single-branch --depth 1 --recurse-submodules https://github.com/WordPress/wordpress-playground.git
cd wordpress-playground
npm install
npm run dev
```

A browser should open and take you to your very own client-side WordPress at [http://127.0.0.1:5400/](http://127.0.0.1:5400/)!

Any changes you make to `.ts` files will be live-reloaded. Changes to `Dockerfile` require a full rebuild.

From here, the [documentation](https://wordpress.github.io/wordpress-playground/) will help you learn how WordPress Playground works and how to use it to build amazing things!

And here's a few more interesting CLI commands you can run in this repo:

```bash
# Build and run PHP.wasm CLI
npx nx start php-wasm-cli

# Build latest WordPress releases
npx nx bundle-wordpress:all playground-wordpress-builds

# Recompile PHP 7.0 - 8.3 releases to .wasm for web
npx nx recompile-php:all php-wasm-web

# Recompile PHP 7.0 - 8.3 releases to .wasm for node
npx nx recompile-php:all php-wasm-node

# Builds the documentation site
npx nx build docs-site

# Builds the Playground Client npm package
npx nx build playground-client

# Bonus: Run PHP.wasm in your local CLI:
npx @php-wasm/cli -v
PHP=7.4 npx @php-wasm/cli -v
npx @php-wasm/cli phpcbf
```

### Test offline support

To test the offline support you need to build the website and run a local server:

```bash
PLAYGROUND_URL=http://localhost:9999 npx nx run playground-website:build:wasm-wordpress-net
```

Then you can run a local server:

```bash
php -S localhost:9999 -t dist/packages/playground/wasm-wordpress-net
```

or using Docker:

```bash
docker run --rm -p 9999:80 -v $(pwd)/dist/packages/playground/wasm-wordpress-net:/usr/share/nginx/html:ro nginx:alpine
```

#### Using the browser to test the offline support

1. Open the browser and go to `http://localhost:9999`.
2. Open the browser's developer tools.
3. Go to the "Network" tab.
4. Select "Offline" in the throttling dropdown menu.
5. Refresh the page.

## How can I contribute?

WordPress Playground is an open-source project and welcomes all contributors from code to design, and from documentation to triage. If the feature you need is missing, you are more than welcome to start a discussion, open an issue, and even propose a Pull Request to implement it.

Here's a few quickstart guides to get you started:

-   Code contributions – see the [developer section](https://wordpress.github.io/wordpress-playground/docs/contributing/code).
-   Documentation – see the [documentation section](https://wordpress.github.io/wordpress-playground/docs/contributing/documentation).
-   Triage – see the [triage section](https://wordpress.github.io/wordpress-playground/contributing/#triaging-issues).
-   Contributions to translations – see the [translations section](https://wordpress.github.io/wordpress-playground/contributing/translations).
-   Reporting bugs – open an [issue](https://github.com/WordPress/wordpress-playground/issues/new) in the repository.
-   Ideas, designs or anything else – open a [GitHub discussion](https://github.com/WordPress/wordpress-playground/discussions) and let's talk!

## WordCamp Contributor Day

If you're participating in a WordCamp Contributor Day, you can use the following instructions to get started: [WordCamp Contributor Day](https://wordpress.github.io/wordpress-playground/contributing/contributor-day/).

## Backwards compatibility

This experimental software may break or change without a warning. Releasing a stable API is an important future milestone that will be reached once the codebase is mature enough.

## Prior art

WordPress Playground forked the original PHP to WebAssembly build published in https://github.com/oraoto/pib and modified later in https://github.com/seanmorris/php-wasm.

Another strong inspiration was the [Drupal in the browser demo](https://seanmorris.github.io/php-wasm/?autorun=0&persist=0&single-expression=0&code=%253C%253Fphp%250Aini_set%28%27session.save_path%27%252C%2520%27%252Fhome%252Fweb_user%27%29%253B%250A%250A%2524stdErr%2520%253D%2520fopen%28%27php%253A%252F%252Fstderr%27%252C%2520%27w%27%29%253B%250A%2524errors%2520%253D%2520%255B%255D%253B%250A%250Aregister_shutdown_function%28function%28%29%2520use%28%2524stdErr%252C%2520%2526%2524errors%29%257B%250A%2520%2520%2520%2520fwrite%28%2524stdErr%252C%2520json_encode%28%255B%27session_id%27%2520%253D%253E%2520session_id%28%29%255D%29%2520.%2520%2522%255Cn%2522%29%253B%250A%2520%2520%2520%2520fwrite%28%2524stdErr%252C%2520json_encode%28%255B%27headers%27%253D%253Eheaders_list%28%29%255D%29%2520.%2520%2522%255Cn%2522%29%253B%250A%2520%2520%2520%2520fwrite%28%2524stdErr%252C%2520json_encode%28%255B%27errors%27%2520%253D%253E%2520error_get_last%28%29%255D%29%2520.%2520%2522%255Cn%2522%29%253B%250A%257D%29%253B%250A%250Aset_error_handler%28function%28...%2524args%29%2520use%28%2524stdErr%252C%2520%2526%2524errors%29%257B%250A%2509fwrite%28%2524stdErr%252C%2520print_r%28%2524args%252C1%29%29%253B%250A%257D%29%253B%250A%250A%2524docroot%2520%253D%2520%27%252Fpreload%252Fdrupal-7.59%27%253B%250A%2524path%2520%2520%2520%2520%253D%2520%27%252F%27%253B%250A%2524script%2520%2520%253D%2520%27index.php%27%253B%250A%250A%2524_SERVER%255B%27REQUEST_URI%27%255D%2520%2520%2520%2520%2520%253D%2520%2524docroot%2520.%2520%2524path%253B%250A%2524_SERVER%255B%27REMOTE_ADDR%27%255D%2520%2520%2520%2520%2520%253D%2520%27127.0.0.1%27%253B%250A%2524_SERVER%255B%27SERVER_NAME%27%255D%2520%2520%2520%2520%2520%253D%2520%27localhost%27%253B%250A%2524_SERVER%255B%27SERVER_PORT%27%255D%2520%2520%2520%2520%2520%253D%25203333%253B%250A%2524_SERVER%255B%27REQUEST_METHOD%27%255D%2520%2520%253D%2520%27GET%27%253B%250A%2524_SERVER%255B%27SCRIPT_FILENAME%27%255D%2520%253D%2520%2524docroot%2520.%2520%27%252F%27%2520.%2520%2524script%253B%250A%2524_SERVER%255B%27SCRIPT_NAME%27%255D%2520%2520%2520%2520%2520%253D%2520%2524docroot%2520.%2520%27%252F%27%2520.%2520%2524script%253B%250A%2524_SERVER%255B%27PHP_SELF%27%255D%2520%2520%2520%2520%2520%2520%2520%2520%253D%2520%2524docroot%2520.%2520%27%252F%27%2520.%2520%2524script%253B%250A%250Achdir%28%2524docroot%29%253B%250A%250Aob_start%28%29%253B%250A%250Adefine%28%27DRUPAL_ROOT%27%252C%2520getcwd%28%29%29%253B%250A%250Arequire_once%2520DRUPAL_ROOT%2520.%2520%27%252Fincludes%252Fbootstrap.inc%27%253B%250Adrupal_bootstrap%28DRUPAL_BOOTSTRAP_FULL%29%253B%250A%250A%2524uid%2520%2520%2520%2520%2520%253D%25201%253B%250A%2524user%2520%2520%2520%2520%253D%2520user_load%28%2524uid%29%253B%250A%2524account%2520%253D%2520array%28%27uid%27%2520%253D%253E%2520%2524user-%253Euid%29%253B%250Auser_login_submit%28array%28%29%252C%2520%2524account%29%253B%250A%250A%2524itemPath%2520%253D%2520%2524path%253B%250A%2524itemPath%2520%253D%2520preg_replace%28%27%252F%255E%255C%255C%252Fpreload%252F%27%252C%2520%27%27%252C%2520%2524itemPath%29%253B%250A%2524itemPath%2520%253D%2520preg_replace%28%27%252F%255E%255C%255C%252Fdrupal-7.59%252F%27%252C%2520%27%27%252C%2520%2524itemPath%29%253B%250A%2524itemPath%2520%253D%2520preg_replace%28%27%252F%255E%255C%252F%252F%27%252C%2520%27%27%252C%2520%2524itemPath%29%253B%250A%250Aif%28%2524itemPath%29%250A%257B%250A%2520%2520%2520%2520%250A%2520%2520%2520%2520%2524router_item%2520%253D%2520menu_get_item%28%2524itemPath%29%253B%250A%2520%2520%2520%2520%2524router_item%255B%27access_callback%27%255D%2520%253D%2520true%253B%250A%2520%2520%2520%2520%2524router_item%255B%27access%27%255D%2520%253D%2520true%253B%250A%2520%2520%2520%2520%250A%2520%2520%2520%2520if%2520%28%2524router_item%255B%27include_file%27%255D%29%2520%257B%250A%2520%2520%2520%2520%2520%2520require_once%2520DRUPAL_ROOT%2520.%2520%27%252F%27%2520.%2520%2524router_item%255B%27include_file%27%255D%253B%250A%2520%2520%2520%2520%257D%250A%2520%2520%2520%2520%250A%2520%2520%2520%2520%2524page_callback_result%2520%253D%2520call_user_func_array%28%2524router_item%255B%27page_callback%27%255D%252C%2520unserialize%28%2524router_item%255B%27page_arguments%27%255D%29%29%253B%250A%2520%2520%2520%2520%250A%2520%2520%2520%2520drupal_deliver_page%28%2524page_callback_result%29%253B%250A%257D%250Aelse%250A%257B%250A%2520%2520%2520%2520menu_execute_active_handler%28%29%253B%250A%257D&render-as=html) which proved you can run non-trivial PHP software in the browser.

A worthy mention is Wasm Labs’s closed-source [WordPress in the browser](https://wordpress.wasmlabs.dev/) shared before this project was first published. There is no public repository available, but their [technical overview](https://wasmlabs.dev/articles/wordpress-in-the-browser/) gives a breakdown of the technical decisions that project took. WordPress Playground draws inspiration from the same PHP in the browser projects and makes similar technical choices.

## Governance

[WordPress Playground](https://github.com/WordPress/wordpress-playground) is a WordPress.org project started and led by [Adam Zielinski](https://github.com/adamziel).

[Playground tools](https://github.com/WordPress/playground-tools) like `wp-now` or the interactive code block are maintained by their authors in the [playground-tools monorepo](https://github.com/WordPress/playground-tools).
