---
sidebar_position: 8
title: Examples
slug: /blueprints/examples
---

import BlueprintExample from '@site/src/components/Blueprints/BlueprintExample.mdx';

# Blueprints Examples

:::tip
Check the [Blueprints Gallery](https://github.com/WordPress/blueprints/blob/trunk/GALLERY.md) to explore real-world code examples of using WordPress Playground to launch a WordPress site with a variety of setups.
:::

Let's see some cool things you can do with Blueprints.

## Install a Theme and a Plugin

<BlueprintExample blueprint={{
	"steps": [
		{
			"step": "installPlugin",
			"pluginData": {
				"resource": "wordpress.org/plugins",
				"slug": "coblocks"
			}
		},
		{
			"step": "installTheme",
			"themeData": {
				"resource": "wordpress.org/themes",
				"slug": "pendant"
			}
		}
	]
}} />

## Run custom PHP code

<BlueprintExample
display={`{
	"steps": [
		{
			"step": "runPHP",
			"code": "<?php include 'wordpress/wp-load.php'; wp_insert_post(array( 'post_title' => 'Post title', 'post_content' => 'Post content', 'post_status' => 'publish', 'post_author' => 1 )); "
		}
	]
}` }
blueprint={{
		"steps": [
			{
				"step": "runPHP",
				"code": `<?php
include 'wordpress/wp-load.php';
wp_insert_post(array(
'post_title' => 'Post title',
'post_content' => 'Post content',
'post_status' => 'publish',
'post_author' => 1
));
`
}
]
}} />

## Enable an option on the Gutenberg Experiments page

Here: Switch on the "new admin views" feature.

<BlueprintExample
display={`{
	"steps": [
		{
			"step": "runPHP",
			"code": "<?php require '/wordpress/wp-load.php'; update_option( 'gutenberg-experiments', array( 'gutenberg-dataviews' => true ) );"
		}
	]
}`}
blueprint={{
		"steps": [
			{
				"step": "runPHP",
				"code": "<?php require '/wordpress/wp-load.php'; update_option( 'gutenberg-experiments', array( 'gutenberg-dataviews' => true ) );"
			}
		]
}} />

## Showcase a product demo

<BlueprintExample noButton blueprint={{
	"steps": [
		{
			"step": "installPlugin",
			"pluginData": {
				"resource": "url",
				"url": "https://your-site.com/your-plugin.zip"
			}
		},
		{
			"step": "installTheme",
			"themeData": {
				"resource": "url",
				"url": "https://your-site.com/your-theme.zip"
			}
		},
		{
			"step": "importWxr",
			"file": {
				"resource": "url",
				"url": "https://your-site.com/starter-content.wxr"
			}
		},
		{
			"step": "setSiteOptions",
			"options": {
				"some_required_option_1": "your_favorite_values",
				"some_required_option_2": "your_favorite_values"
			}
		}
	]
}} />

## Enable networking

<BlueprintExample blueprint={{
	"landingPage": "/wp-admin/plugin-install.php",
	"features": {
		"networking": true
	},
	"steps": [
		{
			"step": "login"
		}
	]
}} />

## Load PHP code on every request (mu-plugin)

Use the `writeFile` step to add code to a mu-plugin that runs on every request.

<BlueprintExample blueprint={{
	"landingPage": "/category/uncategorized/",
	"features": {
		"networking": true
	},
	"steps": [
		{
			"step": "login"
		},
		{
			"step": "writeFile",
			"path": "/wordpress/wp-content/mu-plugins/rewrite.php",
			"data": "<?php add_action( 'after_setup_theme', function() { global $wp_rewrite; $wp_rewrite->set_permalink_structure('/%postname%/'); $wp_rewrite->flush_rules(); } );"
		}
	]
}} />

## Code editor (as a Gutenberg block)

<BlueprintExample blueprint={{
  "landingPage": "/wp-admin/post.php?post=4&action=edit",
  "steps": [
    {
      "step": "login",
      "username": "admin",
      "password": "password"
    },
    {
      "step": "installPlugin",
      "pluginData": {
        "resource": "wordpress.org/plugins",
        "slug": "interactive-code-block"
      }
    },
    {
      "step": "runPHP",
      "code": "<?php require '/wordpress/wp-load.php'; wp_insert_post(['post_title' => 'WordPress Playground block demo!','post_content' => '<!-- wp:wordpress-playground/playground /-->', 'post_status' => 'publish', 'post_type' => 'post',]);"
    }
  ]
}} />

You can share your own Blueprint examples in [this dedicated wiki](https://github.com/WordPress/wordpress-playground/wiki/Blueprint-examples).

## Load an older WordPress version

Playground only ships with a few recent WordPress releases. If you need to use an older version, this Blueprint can help you: change the version number in `"url": "https://playground.wordpress.net/plugin-proxy.php?url=https://wordpress.org/wordpress-5.9.9.zip"` from `5.9.9` to the release you want to load.

**Note:** the oldest supported WordPress version is `5.9.9`, following the SQLite integration plugin.

<BlueprintExample blueprint={{
    "landingPage": "/wp-admin",
    "steps": [
        {
            "step": "writeFile",
            "path": "/tmp/wordpress.zip",
            "data": {
                "resource": "url",
                "url": "https://playground.wordpress.net/plugin-proxy.php?url=https://wordpress.org/wordpress-5.9.9.zip",
                "caption": "Downloading the WordPress Release"
            }
        },
        {
            "step": "importWordPressFiles",
            "wordPressFilesZip": {
                "resource": "vfs",
                "path": "/tmp/wordpress.zip"
            },
            "pathInZip": "/wordpress",
            "progress": {
                "weight": 20,
                "caption": "Importing the WordPress release"
            }
        },
        {
            "step": "runPHP",
            "code": "<?php $_GET['step'] = 'upgrade_db'; require '/wordpress/wp-admin/upgrade.php'; "
        },
        {
            "step": "login",
            "username": "admin",
            "password": "password"
        }
    ]
}} />

## Run WordPress from trunk or a specific commit.

WordPress Playground can run `trunk` (the latest commit), the HEAD of a specific branch or a specific commit from the [WordPress/WordPress](https://github.com/WordPress/WordPress) GitHub repository.

You can specify the reference in `"url": "https://playground.wordpress.net/plugin-proxy.php?build-ref=trunk"`.

To specify the latest commit of a particular branch, you can change the reference to the branch version number, eg `6.6`. To run a specific commit, you can use the commit hash from [WordPress/WordPress](https://github.com/WordPress/WordPress), eg `7d7a52367dee9925337e7d901886c2e9b21f70b6`.

**Note:** the oldest supported WordPress version is `5.9.9`, following the SQLite integration plugin.

<BlueprintExample blueprint={{
    "landingPage": "/wp-admin",
	"login" : true,
	"preferredVersions" : {
		"php": "8.0",
		"wp": "https://playground.wordpress.net/plugin-proxy.php?build-ref=trunk"
	}
}} />
