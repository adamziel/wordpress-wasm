import {
	FileNotFoundAction,
	FileNotFoundGetActionCallback,
	FileTree,
	PHP,
	PHPProcessManager,
	PHPRequestHandler,
	SpawnHandler,
	proxyFileSystem,
	rotatePHPRuntime,
	setPhpIniEntries,
	withPHPIniValues,
	writeFiles,
} from '@php-wasm/universal';
import {
	preloadPhpInfoRoute,
	setupPlatformLevelMuPlugins,
	preloadSqliteIntegration,
	unzipWordPress,
	wordPressRewriteRules,
} from '.';
import { joinPaths } from '@php-wasm/util';
import { logger } from '@php-wasm/logger';

export type PhpIniOptions = Record<string, string>;
export type Hook = (php: PHP) => void | Promise<void>;
export interface Hooks {
	beforeWordPressFiles?: Hook;
	beforeDatabaseSetup?: Hook;
}

export type DatabaseType = 'sqlite' | 'mysql' | 'custom';

export interface BootOptions {
	createPhpRuntime: () => Promise<number>;
	/**
	 * Mounting and Copying is handled via hooks for starters.
	 *
	 * In the future we could standardize the
	 * browser-specific and node-specific mounts
	 * in the future.
	 */
	hooks?: Hooks;
	/**
	 * PHP SAPI name to be returned by get_sapi_name(). Overriding
	 * it is useful for running programs that check for this value,
	 * e.g. WP-CLI
	 */
	sapiName?: string;
	/**
	 * URL to use as the site URL. This is used to set the WP_HOME
	 * and WP_SITEURL constants in WordPress.
	 */
	siteUrl: string;
	documentRoot?: string;
	/** SQL file to load instead of installing WordPress. */
	dataSqlPath?: string;
	/** Zip with the WordPress installation to extract in /wordpress. */
	wordPressZip?: File | Promise<File> | undefined;
	/** Preloaded SQLite integration plugin. */
	sqliteIntegrationPluginZip?: File | Promise<File>;
	spawnHandler?: (processManager: PHPProcessManager) => SpawnHandler;
	/**
	 * PHP.ini entries to define before running any code. They'll
	 * be used for all requests.
	 */
	phpIniEntries?: PhpIniOptions;
	/**
	 * PHP constants to define for every request.
	 */
	constants?: Record<string, string | number | boolean | null>;
	/**
	 * Files to create in the filesystem before any mounts are applied.
	 *
	 * Example:
	 *
	 * ```ts
	 * {
	 * 		createFiles: {
	 * 			'/tmp/hello.txt': 'Hello, World!',
	 * 			'/internal/preload': {
	 * 				'1-custom-mu-plugin.php': '<?php echo "Hello, World!";',
	 * 			}
	 * 		}
	 * }
	 * ```
	 */
	createFiles?: FileTree;

	/**
	 * A callback that decides how to handle a file-not-found condition for a
	 * given request URI.
	 */
	getFileNotFoundAction?: FileNotFoundGetActionCallback;
}

/**
 * Boots a WordPress instance with the given options.
 *
 * High-level overview:
 *
 * * Boot PHP instances and PHPRequestHandler
 * * Setup VFS, run beforeWordPressFiles hook
 * * Setup WordPress files (if wordPressZip is provided)
 * * Run beforeDatabaseSetup hook
 * * Setup the database – SQLite, MySQL (@TODO), or rely on a mounted database
 * * Run WordPress installer, if the site isn't installed yet
 *
 * @param options Boot configuration options
 * @return PHPRequestHandler instance with WordPress installed.
 */

export async function bootWordPress(options: BootOptions) {
	async function createPhp(
		requestHandler: PHPRequestHandler,
		isPrimary: boolean
	) {
		const php = new PHP(await options.createPhpRuntime());
		if (options.sapiName) {
			php.setSapiName(options.sapiName);
		}
		if (requestHandler) {
			php.requestHandler = requestHandler;
		}
		if (options.phpIniEntries) {
			setPhpIniEntries(php, options.phpIniEntries);
		}
		/**
		 * Set up mu-plugins in /internal/shared/mu-plugins
		 * using auto_prepend_file to provide platform-level
		 * customization without altering the installed WordPress
		 * site.
		 *
		 * We only do that in the primary PHP instance –
		 * the filesystem there is the source of truth
		 * for all other PHP instances.
		 */
		if (isPrimary) {
			await setupPlatformLevelMuPlugins(php);
			await writeFiles(php, '/', options.createFiles || {});
			await preloadPhpInfoRoute(
				php,
				joinPaths(new URL(options.siteUrl).pathname, 'phpinfo.php')
			);
		} else {
			// Proxy the filesystem for all secondary PHP instances to
			// the primary one.
			proxyFileSystem(await requestHandler.getPrimaryPhp(), php, [
				'/tmp',
				requestHandler.documentRoot,
				'/internal/shared',
			]);
		}

		// Spawn handler is responsible for spawning processes for all the
		// `popen()`, `proc_open()` etc. calls.
		if (options.spawnHandler) {
			await php.setSpawnHandler(
				options.spawnHandler(requestHandler.processManager)
			);
		}

		// Rotate the PHP runtime periodically to avoid memory leak-related crashes.
		// @see https://github.com/WordPress/wordpress-playground/pull/990 for more context
		rotatePHPRuntime({
			php,
			cwd: requestHandler.documentRoot,
			recreateRuntime: options.createPhpRuntime,
			maxRequests: 400,
		});

		return php;
	}

	const requestHandler: PHPRequestHandler = new PHPRequestHandler({
		phpFactory: async ({ isPrimary }) =>
			createPhp(requestHandler, isPrimary),
		documentRoot: options.documentRoot || '/wordpress',
		absoluteUrl: options.siteUrl,
		rewriteRules: wordPressRewriteRules,
		getFileNotFoundAction:
			options.getFileNotFoundAction ?? getFileNotFoundActionForWordPress,
	});

	const php = await requestHandler.getPrimaryPhp();

	if (options.hooks?.beforeWordPressFiles) {
		await options.hooks.beforeWordPressFiles(php);
	}

	if (options.wordPressZip) {
		await unzipWordPress(php, await options.wordPressZip);
	}

	if (options.constants) {
		for (const key in options.constants) {
			php.defineConstant(key, options.constants[key] as string);
		}
	}

	php.defineConstant('WP_HOME', options.siteUrl);
	php.defineConstant('WP_SITEURL', options.siteUrl);

	// Run "before database" hooks to mount/copy more files in
	if (options.hooks?.beforeDatabaseSetup) {
		await options.hooks.beforeDatabaseSetup(php);
	}

	// @TODO Assert WordPress core files are in place

	if (options.sqliteIntegrationPluginZip) {
		await preloadSqliteIntegration(
			php,
			await options.sqliteIntegrationPluginZip
		);
	}

	if (!(await isWordPressInstalled(php))) {
		await installWordPress(php);
	}

	if (!(await isWordPressInstalled(php))) {
		throw new Error('WordPress installation has failed.');
	}

	return requestHandler;
}

async function isWordPressInstalled(php: PHP) {
	const result = await php.run({
		code: `<?php
$wp_load = getenv('DOCUMENT_ROOT') . '/wp-load.php';
if (!file_exists($wp_load)) {
	echo '0';
	exit;
}
require $wp_load;
echo is_blog_installed() ? '1' : '0';
`,
		env: {
			DOCUMENT_ROOT: php.documentRoot,
		},
	});
	return result.text === '1';
}

async function installWordPress(php: PHP) {
	// Disables networking for the installation wizard
	// to avoid loopback requests and also speed it up.
	await withPHPIniValues(
		php,
		{
			disable_functions: 'fsockopen',
			allow_url_fopen: '0',
		},
		async () =>
			await php.request({
				url: '/wp-admin/install.php?step=2',
				method: 'POST',
				body: {
					language: 'en',
					prefix: 'wp_',
					weblog_title: 'My WordPress Website',
					user_name: 'admin',
					admin_password: 'password',
					// The installation wizard demands typing the same password twice
					admin_password2: 'password',
					Submit: 'Install WordPress',
					pw_weak: '1',
					admin_email: 'admin@localhost.com',
				},
			})
	);

	const defaultedToPrettyPermalinks = await php.run({
		code: `<?php
$wp_load = getenv('DOCUMENT_ROOT') . '/wp-load.php';
if (!file_exists($wp_load)) {
	echo '0';
	exit;
}
require $wp_load;
$option_result = update_option(
	'permalink_structure',
	'/%year%/%monthnum%/%day%/%postname%/'
);
echo $option_result ? '1' : '0';
`,
		env: {
			DOCUMENT_ROOT: php.documentRoot,
		},
	});

	if (defaultedToPrettyPermalinks.text !== '1') {
		logger.warn('Failed to default to pretty permalinks after WP install.');
	}
}

export function getFileNotFoundActionForWordPress(
	// eslint-disable-next-line @typescript-eslint/no-unused-vars -- maintain consistent FileNotFoundGetActionCallback signature
	relativeUri: string
): FileNotFoundAction {
	// Delegate unresolved requests to WordPress. This makes WP magic possible,
	// like pretty permalinks and dynamically generated sitemaps.
	return {
		type: 'internal-redirect',
		uri: '/index.php',
	};
}
