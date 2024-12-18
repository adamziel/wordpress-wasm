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

	it('should activate the plugin', async () => {
		const docroot = handler.documentRoot;
		php.writeFile(
			`${docroot}/wp-content/plugins/test-plugin.php`,
			`<?php /**\n * Plugin Name: Test Plugin */`
		);
		await activatePlugin(php, {
			pluginPath: 'test-plugin.php',
		});

		const response = await php.run({
			code: `<?php
				require_once '/wordpress/wp-load.php';
				require_once ${phpVar(docroot)}. "/wp-admin/includes/plugin.php" ;
				echo is_plugin_active('test-plugin.php') ? 'true' : 'false';
			`,
		});
		expect(response.text).toBe('true');
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
		expect(
			async () =>
				await activatePlugin(php, {
					pluginPath: 'test-plugin.php',
				})
		).rejects.toThrow(/Plugin test-plugin.php could not be activated/);
	});

	it('should run the activation hooks as a priviliged user', async () => {
		const docroot = handler.documentRoot;
		const createdFilePath =
			docroot + '/activation-ran-as-a-priviliged-user.txt';
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

	it('should succeed when the plugin redirects during activation', async () => {
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
		await expect(async () => {
			await activatePlugin(php, {
				pluginPath: 'test-plugin.php',
			});
		}).not.toThrow();
	});

	it('should activate the plugin if it produces output during activation', async () => {
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
		await expect(async () => {
			await activatePlugin(php, {
				pluginPath: 'test-plugin.php',
			});
		}).not.toThrow();
	});

	it('should successfully complete plugin activation when the plugin is already active', async () => {
		const docroot = handler.documentRoot;
		php.writeFile(
			`${docroot}/wp-content/plugins/test-plugin.php`,
			`<?php /**\n * Plugin Name: Test Plugin */`
		);
		await activatePlugin(php, {
			pluginPath: 'test-plugin.php',
		});
		await expect(async () => {
			await activatePlugin(php, {
				pluginPath: 'test-plugin.php',
			});
		}).not.toThrow();
	});
});
