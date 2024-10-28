import { StepHandler } from '.';
import { defineWpConfigConsts } from './define-wp-config-consts';
import { setSiteOptions } from './site-data';
import { assertWpCli, wpCLI } from './wp-cli';

/**
 * @inheritDoc enableMultisite
 * @hasRunnableExample
 * @example
 *
 * <code>
 * {
 * 		"step": "enableMultisite"
 * }
 * </code>
 */
export interface EnableMultisiteStep {
	step: 'enableMultisite';
	/** wp-cli.phar path */
	wpCliPath?: string;
}

/**
 * Defines the [Multisite](https://developer.wordpress.org/advanced-administration/multisite/create-network/) constants in a `wp-config.php` file.
 *
 * This step can be called multiple times, and the constants will be merged.
 *
 * @param playground The playground client.
 * @param enableMultisite
 */
export const enableMultisite: StepHandler<EnableMultisiteStep> = async (
	playground,
	{ wpCliPath }
) => {
	await assertWpCli(playground, wpCliPath);

	await defineWpConfigConsts(playground, {
		consts: {
			WP_ALLOW_MULTISITE: 1,
		},
	});

	const url = new URL(await playground.absoluteUrl);
	if (url.port !== '') {
		let errorMessage = `The current host is ${url.host}, but WordPress multisites do not support custom ports.`;
		if (url.hostname === 'localhost') {
			errorMessage += ` For development, you can set up a playground.test domain using the instructions at https://wordpress.github.io/wordpress-playground/contributing/code.`;
		}
		throw new Error(errorMessage);
	}
	const sitePath = url.pathname.replace(/\/$/, '') + '/';
	const siteUrl = `${url.protocol}//${url.hostname}${sitePath}`;
	await setSiteOptions(playground, {
		options: {
			siteurl: siteUrl,
			home: siteUrl,
		},
	});

	await wpCLI(playground, {
		command: 'wp core multisite-convert',
	});
};
