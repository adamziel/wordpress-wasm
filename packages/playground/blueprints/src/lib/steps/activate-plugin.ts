import { phpVar } from '@php-wasm/util';
import { StepHandler } from '.';
import { logger } from '@php-wasm/logger';
/**
 * @inheritDoc activatePlugin
 * @example
 *
 * <code>
 * {
 * 		"step": "activatePlugin",
 * 		"pluginName": "Gutenberg",
 * 		"pluginPath": "/wordpress/wp-content/plugins/gutenberg"
 * }
 * </code>
 */
export interface ActivatePluginStep {
	step: 'activatePlugin';
	/**
	 * Path to the plugin directory as absolute path
	 * (/wordpress/wp-content/plugins/plugin-name); or the plugin entry file
	 * relative to the plugins directory (plugin-name/plugin-name.php).
	 */
	pluginPath: string;
	/** Optional. Plugin name to display in the progress bar. */
	pluginName?: string;
}

/**
 * Activates a WordPress plugin (if it's installed).
 *
 * @param playground The playground client.
 */
export const activatePlugin: StepHandler<ActivatePluginStep> = async (
	playground,
	{ pluginPath, pluginName },
	progress
) => {
	progress?.tracker.setCaption(`Activating ${pluginName || pluginPath}`);

	const docroot = await playground.documentRoot;
	const activatePluginResult = await playground.run({
		code: `<?php
			define( 'WP_ADMIN', true );
			require_once( getenv('DOCROOT') . "/wp-load.php" );
			require_once( getenv('DOCROOT') . "/wp-admin/includes/plugin.php" );

			// Set current user to admin
			wp_set_current_user( get_users(array('role' => 'Administrator') )[0]->ID );

			$plugin_path = getenv('PLUGIN_PATH');
			$response = false;
			if (!is_dir($plugin_path)) {
				$response = activate_plugin($plugin_path);
			}

			// Activate plugin by name if activation by path wasn't successful
			if ( null !== $response ) {
				foreach ( ( glob( $plugin_path . '/*.php' ) ?: array() ) as $file ) {
					$info = get_plugin_data( $file, false, false );
					if ( ! empty( $info['Name'] ) ) {
						$response = activate_plugin( $file );
						break;
					}
				}
			}

			if (is_wp_error($response)) {
				die($response->get_error_message());
			}
		`,
		env: {
			PLUGIN_PATH: pluginPath,
			DOCROOT: docroot,
		},
	});

	/**
	 * Instead of checking the plugin activation response,
	 * check if the plugin is active by looking at the active plugins list.
	 *
	 * We have to split the activation and the check into two PHP runs
	 * because some plugins might redirect during activation,
	 * which would prevent any output that happens after activation from being returned.
	 *
	 * Relying on the plugin activation response is not reliable because if the plugin activation
	 * produces any output, WordPress will assume it's an activation error.
	 * See WordPress source code for more details:
	 * https://github.com/WordPress/wordpress-develop/blob/6.7/src/wp-admin/includes/plugin.php#L733
	 *
	 * Because some plugins can create an output, we need to use output buffering
	 * to ensure the 'true' response is not polluted by other outputs.
	 * If the plugin activation fails, we will return the buffered output as it might
	 * contain more information about the failure.
	 */
	const isActiveCheckResult = await playground.run({
		code: `<?php
			ob_start();
			require_once( getenv('DOCROOT') . "/wp-load.php" );

			$plugin_directory = getenv('DOCROOT') . "/wp-content/plugins/";
			$relative_plugin_path = getenv('PLUGIN_PATH');
			if (strpos($relative_plugin_path, $plugin_directory) === 0) {
				$relative_plugin_path = substr_replace(
					$relative_plugin_path,
					'',
					0,
					strlen($plugin_directory)
				);
			}
			$active_plugins = get_option( 'active_plugins' );
			foreach ( $active_plugins as $plugin ) {
				if ( strpos( $plugin, $relative_plugin_path ) === 0 ) {
					ob_end_clean();
					die('true');
				}
			}
			die(ob_get_flush() ?: 'false');
		`,
		env: {
			DOCROOT: docroot,
			PLUGIN_PATH: pluginPath,
		},
	});

	if (activatePluginResult.text) {
		logger.warn(activatePluginResult.text);
	}
	if (isActiveCheckResult.text !== 'true') {
		if (isActiveCheckResult.text !== 'false') {
			logger.debug(isActiveCheckResult.text);
		}
		throw new Error(
			`Plugin ${pluginPath} could not be activated – WordPress exited with no error. ` +
				`Sometimes, when $_SERVER or site options are not configured correctly, ` +
				`WordPress exits early with a 301 redirect. ` +
				`Inspect the "debug" logs in the console for more details.`
		);
	}
};
