---
slug: /developers/architecture/host-your-own-playground
---

# Host your own Playground

You can host the Playground on your own domain instead of `playground.wordpress.net`.

This is useful for having full control over its content and behavior, as well as removing dependency on a third-party server. It can provide a more customized user experience, for example: a playground with preinstalled plugins and themes, default site settings, or demo content.

#### Usage

A self-hosted Playground can be embedded as an iframe.

```html
<iframe src="https://my-playground.com"></iframe>
```

Or dynamically loaded by passing the remote URL to the Playground Client.

```ts
import { startPlaygroundWeb } from '@wp-playground/client';

const client = await startPlaygroundWeb({
	iframe: document.getElementById('wp'),
	remoteUrl: `https://my-playground.com/remote.html`,
});
```

## Static assets

There are several ways to get the static assets necessary to host the Playground.

In order of convenience and ease:

-   Download pre-built package
-   Fork the repository and build with GitHub Action
-   Build locally

### Download pre-built package

To host the Playground as is, without making changes, you can download the built artifact from [the latest successful GitHub Action](https://github.com/WordPress/wordpress-playground/actions/workflows/build-website.yml?query=is%3Asuccess).

-   Click on **Deploy to playground.wordpress.net**.
-   In the section **Artifacts** at the bottom of the page, click `playground-website`.
-   It's a zip package with the same files deployed to the public site.

### Fork the repository and build with GitHub Action

To customize the Playground, you can [fork the Git repository](https://github.com/WordPress/wordpress-playground/fork).

Build it from the fork's GitHub page by going to: **Actions -> Deploy to playground.wordpress.net -> Run workflow**.

### Build locally

The most flexible and customizable method is to build the site locally.

Create a shallow clone of the Playground repository, or your own fork.

```sh
git clone -b trunk --single-branch --depth 1 --recurse-submodules git@github.com:WordPress/wordpress-playground.git
```

Enter the `wordpress-playground` directory.

```sh
cd wordpress-playground
```

Install dependencies, and build the website.

```sh
npm install
npm run build:website
```

This command internally runs the `nx` task `build:wasm-wordpress-net`. It copies the built assets from packages `remote` and `website` into a new folder at the following path:

```
dist/packages/playground/wasm-wordpress-net
```

The entire service of the Playground consists of the content of this folder.

## Summary of included files

The static assets include:

-   Data and WASM files for all available PHP and WordPress versions
-   `remote.html` - the core of Playground
-   `index.html` - the shell, or browser chrome
-   Web Worker script

You can deploy the content of the folder to your server using SSH, such as `scp` or `rsync`.

It is a static site, except for these dynamic aspects.

-   Apache server directive `.htaccess` file from the package `remote`

For these to work, you need a server environment with Apache and PHP installed.

## NGINX configuration

As an alternative to Apache, here is an example of using NGINX to serve the Playground.

:::info Refer to the source file

The example may be outdated. Please check [the source file](https://github.com/WordPress/wordpress-playground/blob/trunk/packages/playground/remote/.htaccess) for the latest version.

:::

The combined Apache `.htaccess` file looks like this.

```htaccess
AddType application/wasm .wasm
```

An equivalent in NGINX.

```nginx
location ~* .wasm$ {
  types {
    application/wasm wasm;
  }
}
```

You may need to adjust the above according to server specifics, particularly how to invoke PHP for the path `/plugin-proxy`.

[Caddy web server](https://caddyserver.com) doesn't require any special config to work.

## Customize bundled data

The file `wp.zip` is a bundle of all the files for the virtual file system in Playground. There's a data file for each available WordPress version.

The package at `packages/playground/wordpress` is responsible for building these data files.

Edit the build script in `Dockerfile` to create a custom bundle that includes preinstalled plugins or content.

### Install plugins

Here's an example of installing plugins for the data bundle.

Before the section titled `Strip whitespaces from PHP files`.

```docker
# === Preinstall plugins ===

RUN cd wordpress/wp-content/mu-plugins && \
    # Install plugins
    for plugin_name in example-plugin-1 example-plugin-2; do \
      curl -L https://downloads.wordpress.org/plugin/{$plugin_name}.latest-stable.zip -o {$plugin_name}.zip && \
      unzip $plugin_file && \
      rm $plugin_file && \
      # Create entry file in mu-plugins root
      echo "<?php require_once __DIR__.'/$plugin_name/$plugin_name.php';" > $plugin_name.php; \
    done;
```

You can download plugins from URLs other than the WordPress plugin directory, or use Git to pull them from elsewhere.

It's also possible to copy from a local folder. For example, before `RUN`:

```
COPY ./build-assets/*.zip /root/
```

Then put the plugin zip files in `build-assets`. In this case, you may want to add their paths to `.gitignore`.

### Import content

Here's an example of importing content.

```docker
# === Demo content ===

COPY ./build-assets/content.xml /root/
RUN cd wordpress ; \
     echo "Importing content.."; \
    ../wp-cli.phar --allow-root import /root/content.xml --authors=create
```

This assumes that you have put a WXR export file named `content.xml` in the folder `build-assets`. You can add its path to `.gitignore`.
