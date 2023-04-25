import { StepHandler } from '.';
import { updateFile } from './common';

export interface DefineSiteUrlArgs {
	siteUrl: string;
}

/**
 * Sets site URL of the WordPress installation.
 *
 * @param playground The playground client.
 * @param siteUrl
 */
export const defineSiteUrl: StepHandler<DefineSiteUrlArgs> = async (
	playground,
	{ siteUrl }
) => {
	await updateFile(
		playground,
		`/wordpress/wp-config.php`,
		(contents) =>
			`<?php 
			if ( ! defined( 'WP_HOME' ) ) {
            	define('WP_HOME', "${siteUrl}");
			}
			if ( ! defined( 'WP_SITEURL' ) ) {
            	define('WP_SITEURL', "${siteUrl}");
			}
            ?>${contents}`
	);
};
