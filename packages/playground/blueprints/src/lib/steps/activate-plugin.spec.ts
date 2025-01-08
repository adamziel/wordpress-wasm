import { PHP } from '@php-wasm/universal';
import { RecommendedPHPVersion } from '@wp-playground/common';
import {
	getSqliteDatabaseModule,
	getWordPressModule,
} from '@wp-playground/wordpress-builds';
import { activatePlugin } from './activate-plugin';
import { phpVar } from '@php-wasm/util';
import { PHPRequestHandler } from '@php-wasm/universal';
import { loadNodeRuntime } from '@php-wasm/node';
import { bootWordPress } from '@wp-playground/wordpress';

describe('Blueprint step activatePlugin()', () => {
	let php: PHP;
	let handler: PHPRequestHandler;
	beforeEach(async () => {
		handler = await bootWordPress({
			createPhpRuntime: async () =>
				await loadNodeRuntime(RecommendedPHPVersion),
			siteUrl: 'http://playground-domain/',

			wordPressZip: await getWordPressModule(),
			sqliteIntegrationPluginZip: await getSqliteDatabaseModule(),
		});
		php = await handler.getPrimaryPhp();
	});

	it('should activate a plugin file located in the plugins directory', async () => {
		const docroot = handler.documentRoot;
		php.writeFile(
			`${docroot}/wp-content/plugins/test-plugin.php`,
			`<?php /**\n * Plugin Name: Test Plugin */`
		);

		await expect(
			activatePlugin(php, {
				pluginPath: 'test-plugin.php',
			})
		).resolves.not.toThrow();
	});

	it('should activate a plugin file located in a subdirectory of the plugins directory', async () => {
		const docroot = handler.documentRoot;
		const pluginDir = `${docroot}/wp-content/plugins/test-plugin`;
		php.mkdir(pluginDir);
		php.writeFile(
			`${pluginDir}/test-plugin.php`,
			`<?php /**\n * Plugin Name: Test Plugin */`
		);

		await expect(
			activatePlugin(php, {
				pluginPath: `test-plugin/test-plugin.php`,
			})
		).resolves.not.toThrow();
	});

	it('should activate a plugin if a absolute plugin path is provided', async () => {
		const docroot = handler.documentRoot;
		php.mkdir(`${docroot}/wp-content/plugins/test-plugin`);
		php.writeFile(
			`${docroot}/wp-content/plugins/test-plugin/index.php`,
			`<?php /**\n * Plugin Name: Test Plugin */`
		);

		await expect(
			activatePlugin(php, {
				pluginPath: `${docroot}/wp-content/plugins/test-plugin/index.php`,
			})
		).resolves.not.toThrow();
	});

	it('should activate a plugin if a absolute plugin directory path is provided', async () => {
		const docroot = handler.documentRoot;
		php.mkdir(`${docroot}/wp-content/plugins/test-plugin`);
		php.writeFile(
			`${docroot}/wp-content/plugins/test-plugin/test-plugin.php`,
			`<?php /**\n * Plugin Name: Test Plugin */`
		);

		await expect(
			activatePlugin(php, {
				pluginPath: `${docroot}/wp-content/plugins/test-plugin`,
			})
		).resolves.not.toThrow();
	});

	it('should activate a plugin if a absolute plugin directory path with a trailing slash is provided', async () => {
		const docroot = handler.documentRoot;
		php.mkdir(`${docroot}/wp-content/plugins/test-plugin`);
		php.writeFile(
			`${docroot}/wp-content/plugins/test-plugin/test-plugin.php`,
			`<?php /**\n * Plugin Name: Test Plugin */`
		);

		await expect(
			activatePlugin(php, {
				pluginPath: `${docroot}/wp-content/plugins/test-plugin/`,
			})
		).resolves.not.toThrow();
	});

	it('should detect a silent failure in activating the plugin', async () => {
		const docroot = handler.documentRoot;
		php.writeFile(
			`${docroot}/wp-content/plugins/test-plugin.php`,
			`<?php /**\n * Plugin Name: Test Plugin */`
		);
		php.mkdir(`${docroot}/wp-content/mu-plugins`);
		php.writeFile(
			`${docroot}/wp-content/mu-plugins/0-exit.php`,
			`<?php exit(0); `
		);
		await expect(
			activatePlugin(php, {
				pluginPath: 'test-plugin.php',
			})
		).rejects.toThrow(/Plugin test-plugin.php could not be activated/);
	});

	it('should run the activation hooks as a privileged user', async () => {
		const docroot = handler.documentRoot;
		const createdFilePath =
			docroot + '/activation-ran-as-a-privileged-user.txt';
		php.writeFile(
			`${docroot}/wp-content/plugins/test-plugin.php`,
			`<?php /**\n * Plugin Name: Test Plugin */
			function myplugin_activate() {
				if( ! current_user_can( 'activate_plugins' ) ) return;
				file_put_contents( ${phpVar(createdFilePath)}, 'Hello World');
			}
			register_activation_hook( __FILE__, 'myplugin_activate' );
			`
		);
		await activatePlugin(php, {
			pluginPath: 'test-plugin.php',
		});

		expect(php.fileExists(createdFilePath)).toBe(true);
	});

	it('should activate a plugin if it redirects during activation', async () => {
		const docroot = handler.documentRoot;
		php.writeFile(
			`${docroot}/wp-content/plugins/test-plugin.php`,
			`<?php
			/**
			 * Plugin Name: Test Plugin
			 */
			add_action( 'activated_plugin', function( $plugin ) {
				if( $plugin == plugin_basename( __FILE__ ) ) {
					wp_redirect( admin_url( 'edit.php' ) );
					exit();
				}
			} );
			`
		);
		await expect(
			activatePlugin(php, {
				pluginPath: 'test-plugin.php',
			})
		).resolves.not.toThrow();
	});

	it('should activate a plugin if it produces a output during activation', async () => {
		const docroot = handler.documentRoot;
		php.writeFile(
			`${docroot}/wp-content/plugins/test-plugin.php`,
			`<?php
			/**
			 * Plugin Name: Test Plugin
			 */
			echo 'Hello World';
			`
		);
		await expect(
			activatePlugin(php, {
				pluginPath: 'test-plugin.php',
			})
		).resolves.not.toThrow();
	});

	it('should not throw an error if the plugin is already active', async () => {
		const docroot = handler.documentRoot;
		php.writeFile(
			`${docroot}/wp-content/plugins/test-plugin.php`,
			`<?php /**\n * Plugin Name: Test Plugin */`
		);
		await activatePlugin(php, {
			pluginPath: 'test-plugin.php',
		});
		await expect(
			activatePlugin(php, {
				pluginPath: 'test-plugin.php',
			})
		).resolves.not.toThrow();
	});
});
