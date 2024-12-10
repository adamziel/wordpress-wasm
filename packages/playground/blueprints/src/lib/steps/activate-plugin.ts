import { phpVar } from '@php-wasm/util';
import { StepHandler } from '.';
import { logger } from '@php-wasm/logger';
import { PHPResponse } from '@php-wasm/universal';

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
	const result = await playground.run({
		code: `<?php
			define( 'WP_ADMIN', true );
			require_once( ${phpVar(docroot)}. "/wp-load.php" );
			require_once( ${phpVar(docroot)}. "/wp-admin/includes/plugin.php" );

			// Set current user to admin
			wp_set_current_user( get_users(array('role' => 'Administrator') )[0]->ID );

			$plugin_path = ${phpVar(pluginPath)};
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

			if ( null === $response ) {
				die('Plugin activated successfully');
			} else if ( is_wp_error( $response ) ) {
				throw new Exception( $response->get_error_message() );
			}

			throw new Exception( 'Unable to activate plugin' );
		`,
	});

	/**
	 * A valid plugin activation response is either
	 * a 200 status code response with the text 'Plugin activated successfully'
	 * or a redirect status code (300-399) response with the text ''
	 *
	 * Some plugins redirect on activation.
	 */
	function isValidActivationResponse(result: PHPResponse) {
		return (
			(result.httpStatusCode === 200 &&
				result.text === 'Plugin activated successfully') ||
			(result.httpStatusCode >= 300 &&
				result.httpStatusCode < 400 &&
				result.text === '')
		);
	}

	if (!isValidActivationResponse(result)) {
		logger.debug(result);
		throw new Error(
			`Plugin ${pluginPath} could not be activated – WordPress exited with no error. ` +
				`Sometimes, when $_SERVER or site options are not configured correctly, ` +
				`WordPress exits early with a 301 redirect. ` +
				`Inspect the "debug" logs in the console for more details`
		);
	}
};
