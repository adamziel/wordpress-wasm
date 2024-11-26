---
title: Test
slug: /about/test
---

# Test

Upgrade your QA process with the ability to review progress in your browser in a single click. When you’re ready, push updates instantly.

## Test any theme or plugin

With Playground, you can test any plugin or theme. Use the [Query API](/developers/apis/query-api) to quickly load any plugin or theme published in wordpress.org [plugins](https://wordpress.org/plugins) and [themes](https://wordpress.org/themes/) directories.

For example, the following link will load the [“pendant” theme](https://wordpress.org/themes/pendant/) and the[ “gutenberg” plugin](https://wordpress.org/plugins/gutenberg/) on a Playground instance:

[https://playground.wordpress.net/?theme=pendant&plugin=gutenberg](https://playground.wordpress.net/?theme=pendant&plugin=gutenberg)

But you can also test [more elaborate configurations using blueprints](https://github.com/WordPress/blueprints/blob/trunk/GALLERY.md), for example testing a plugin’s code from a gist (see [blueprint](https://github.com/wordpress/blueprints/blob/trunk/blueprints/install-plugin-from-gist/blueprint.json) and [live demo](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/wordpress/blueprints/trunk/blueprints/install-plugin-from-gist/blueprint.json))

## Live preview pull requests

Testing pull requests is one of the most exciting use cases for the Playground project. With Playground, you can enable a Live preview link on each Pull Request of a WordPress-related project in GitHub so that developers can see in action the effects of code in that Pull Request. Read more about this at [Preview WordPress Core Pull Requests with Playground](https://wptavern.com/preview-wordpress-core-pull-requests-with-playground#:~:text=Previewing%20WordPress%20Pull%20Requests%20requires,testing%20and%20team%20workflows%20difficult.).

There are some public implementations of this use case such as [WordPress Core PR previewer](https://playground.wordpress.net/wordpress.html) and [Gutenberg PR previewer](https://playground.wordpress.net/gutenberg.html). Users can input the PR number or URL to be redirected to a WordPress instance, powered by Playground, where the changes from the PR are applied.

GitHub actions such as [WP Playground PR Preview](https://github.com/vcanales/action-wp-playground-pr-preview) allows you to add PR previews powered by WP Playground on any repository. For example, this feature [was enabled](https://github.com/WordPress/twentytwentyfive/pull/359) in the [WordPress/twentytwentyfive](https://github.com/WordPress/twentytwentyfive) repository.

## Clone your site and experiment in a private sandbox.

With the [Sandbox Site powered by Playground](https://wordpress.org/plugins/playground/) plugin you can create a private WordPress Playground copy of your site to test plugins safely or do any other experiments on your site’s replica without uploading any data to the cloud and without affecting the original site.

## Test different WordPress and PHP versions.

With Playground, you can quickly test any major WordPress or PHP version by _customizing its settings_ or using a custom blueprint with the `preferredVersions` property.

For example, you can always test the latest development version of WordPress, also called [Beta Nightly](https://wordpress.org/download/beta-nightly/), from this link: [https://playground.wordpress.net/?wp=nightly](https://playground.wordpress.net/?wp=nightly)

During the Beta period of any WordPress release, you can also test the latest WordPress Beta or RC release with theme test data and debugging plugins (see [blueprint](https://github.com/WordPress/blueprints/blob/trunk/blueprints/beta-rc/blueprint.json) and [live demo). ](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/wordpress/blueprints/trunk/blueprints/beta-rc/blueprint.json)

You can also load any [theme, plugin](/developers/apis/query-api), or [configuration](/blueprints) in any of the available WordPress and PHP versions to check how they work in that environment.

The [WordPress Playground: the ultimate learning, testing, & teaching tool for WordPress](https://www.youtube.com/watch?v=dN_LaenY8bI) provides a great overview of the testing possibilities with Playground.
