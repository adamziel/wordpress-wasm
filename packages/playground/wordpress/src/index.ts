import { PHP, UniversalPHP } from '@php-wasm/universal';
import { joinPaths, phpVar } from '@php-wasm/util';
import { unzipFile, createMemoizedFetch } from '@wp-playground/common';
export { bootWordPress, getFileNotFoundActionForWordPress } from './boot';
export { getLoadedWordPressVersion } from './version-detect';

export * from './version-detect';
export * from './rewrite-rules';

/**
 * Preloads the platform mu-plugins from /internal/shared/mu-plugins.
 * This avoids polluting the WordPress installation with mu-plugins
 * that are only needed in the Playground environment.
 *
 * @param php
 */
export async function setupPlatformLevelMuPlugins(php: UniversalPHP) {
	await php.mkdir('/internal/shared/mu-plugins');
	await php.writeFile(
		'/internal/shared/preload/env.php',
		`<?php

        // Allow adding filters/actions prior to loading WordPress.
        // $function_to_add MUST be a string.
        function playground_add_filter( $tag, $function_to_add, $priority = 10, $accepted_args = 1 ) {
            global $wp_filter;
            $wp_filter[$tag][$priority][$function_to_add] = array('function' => $function_to_add, 'accepted_args' => $accepted_args);
        }
        function playground_add_action( $tag, $function_to_add, $priority = 10, $accepted_args = 1 ) {
            playground_add_filter( $tag, $function_to_add, $priority, $accepted_args );
        }

        // Load our mu-plugins after customer mu-plugins
        // NOTE: this means our mu-plugins can't use the muplugins_loaded action!
        playground_add_action( 'muplugins_loaded', 'playground_load_mu_plugins', 0 );
        function playground_load_mu_plugins() {
            // Load all PHP files from /internal/shared/mu-plugins, sorted by filename
            $mu_plugins_dir = '/internal/shared/mu-plugins';
            if(!is_dir($mu_plugins_dir)){
                return;
            }
            $mu_plugins = glob( $mu_plugins_dir . '/*.php' );
            sort( $mu_plugins );
            foreach ( $mu_plugins as $mu_plugin ) {
                require_once $mu_plugin;
            }
        }
    `
	);

	/**
	 * Automatically logs the user in to aid the login Blueprint step and
	 * the Playground runtimes.
	 *
	 * There are two ways to trigger the auto-login:
	 *
	 * ## The PLAYGROUND_AUTO_LOGIN_AS_USER constant
	 *
	 * Used by the login Blueprint step does.
	 *
	 * When the PLAYGROUND_AUTO_LOGIN_AS_USER constant is defined, this mu-plugin
	 * will automatically log the user in on their first visit. The username is
	 * the value of the constant.
	 *
	 * On subsequent visits, the playground_auto_login_already_happened cookie will be
	 * detected and the user will not be logged in. This means the "logout" feature
	 * will work as expected.
	 *
	 * ## The playground_force_auto_login_as_user GET parameter
	 *
	 * Used by the "login" button in various Playground runtimes.
	 *
	 * Only works if the PLAYGROUND_FORCE_AUTO_LOGIN_ENABLED constant is defined.
	 *
	 * When the playground_force_auto_login_as_user GET parameter is present,
	 * this mu-plugin will automatically log in any logged out visitor. This will
	 * happen every time they visit, not just on their first visit.
	 *
	 *
	 * ## Context
	 *
	 * The login step used to make a HTTP request to the /wp-login.php endpoint,
	 * but that approach had significant downsides:
	 *
	 * * It only worked in web browsers
	 * * It didn't support custom login mechanisms
	 * * It required storing plaintext passwords in the Blueprint files
	 */
	await php.writeFile(
		'/internal/shared/mu-plugins/1-auto-login.php',
		`<?php
		/**
		 * Returns the username to auto-login as, if any.
		 * @return string|false
		 */
		function playground_get_username_for_auto_login() {
			/**
			 * Allow users to auto-login as a specific user on their first visit.
			 *
			 * Prevent the auto-login if it already happened by checking for the
			 * playground_auto_login_already_happened cookie.
			 * This is used to allow the user to logout.
			 */
			if ( defined('PLAYGROUND_AUTO_LOGIN_AS_USER') && !isset($_COOKIE['playground_auto_login_already_happened']) ) {
				return PLAYGROUND_AUTO_LOGIN_AS_USER;
			}
			/**
			 * Allow users to auto-login as a specific user by passing the
			 * playground_force_auto_login_as_user GET parameter.
			 */
			if ( defined('PLAYGROUND_FORCE_AUTO_LOGIN_ENABLED') && isset($_GET['playground_force_auto_login_as_user']) ) {
				return $_GET['playground_force_auto_login_as_user'];
			}
			return false;
		}

		/**
		 * Logs the user in on their first visit if the Playground runtime told us to.
		 */
		function playground_auto_login() {
			/**
			 * The redirect should only run if the current PHP request is
			 * a HTTP request. If it's a PHP CLI run, we can't login the user
			 * because logins require cookies which aren't available in the CLI.
			 *
			 * Currently all Playground requests use the "cli" SAPI name
			 * to ensure support for WP-CLI, so the best way to distinguish
			 * between a CLI run and an HTTP request is by checking if the
			 * $_SERVER['REQUEST_URI'] global is set.
			 *
			 * If $_SERVER['REQUEST_URI'] is not set, we assume it's a CLI run.
			 */
			if (empty($_SERVER['REQUEST_URI'])) {
				return;
			}
			$user_name = playground_get_username_for_auto_login();
			if ( false === $user_name ) {
				return;
			}
			if (wp_doing_ajax() || defined('REST_REQUEST')) {
				return;
			}
			if ( is_user_logged_in() ) {
				return;
			}
			$user = get_user_by('login', $user_name);
			if (!$user) {
				return;
			}

			/**
			 * We're about to set cookies and redirect. It will log the user in
			 * if the headers haven't been sent yet.
			 *
			 * However, if they have been sent already – e.g. there a PHP
			 * notice was printed, we'll exit the script with a bunch of errors
			 * on the screen and without the user being logged in. This
			 * will happen on every page load and will effectively make Playground
			 * unusable.
			 *
			 * Therefore, we just won't auto-login if headers have been sent. Maybe
			 * we'll be able to finish the operation in one of the future requests
			 * or maybe not, but at least we won't end up with a permanent white screen.
			 */
			if (headers_sent()) {
				_doing_it_wrong('playground_auto_login', 'Headers already sent, the Playground runtime will not auto-login the user', '1.0.0');
				return;
			}

			/**
			 * This approach is described in a comment on
			 * https://developer.wordpress.org/reference/functions/wp_set_current_user/
			 */
			wp_set_current_user( $user->ID, $user->user_login );
			wp_set_auth_cookie( $user->ID );
			do_action( 'wp_login', $user->user_login, $user );

			setcookie('playground_auto_login_already_happened', '1');

			/**
			 * Confirm that nothing in WordPress, plugins, or filters have finalized
			 * the headers sending phase. See the comment above for more context.
			 */
			if (headers_sent()) {
				_doing_it_wrong('playground_auto_login', 'Headers already sent, the Playground runtime will not auto-login the user', '1.0.0');
				return;
			}

			/**
			 * Reload page to ensure the user is logged in correctly.
			 * WordPress uses cookies to determine if the user is logged in,
			 * so we need to reload the page to ensure the cookies are set.
			 */
			$redirect_url = $_SERVER['REQUEST_URI'];
			/**
			 * Intentionally do not use wp_redirect() here. It removes
			 * %0A and %0D sequences from the URL, which we don't want.
			 * There are valid use-cases for encoded newlines in the query string,
			 * for example html-api-debugger accepts markup with newlines
			 * encoded as %0A via the query string.
			 */
			header( "Location: $redirect_url", true, 302 );
			exit;
		}
		/**
		 * Autologin users from the wp-login.php page.
		 *
		 * The wp hook isn't triggered on
		 **/
		add_action('init', 'playground_auto_login', 1);

		/**
		 * Disable the Site Admin Email Verification Screen for any session started
		 * via autologin.
		 */
		add_filter('admin_email_check_interval', function($interval) {
			if(false === playground_get_username_for_auto_login()) {
				return 0;
			}
			return $interval;
		});
		`
	);

	await php.writeFile(
		'/internal/shared/mu-plugins/0-playground.php',
		`<?php
        // Needed because gethostbyname( 'wordpress.org' ) returns
        // a private network IP address for some reason.
        add_filter( 'allowed_redirect_hosts', function( $deprecated = '' ) {
            return array(
                'wordpress.org',
                'api.wordpress.org',
                'downloads.wordpress.org',
            );
        } );

		// Support pretty permalinks
        add_filter( 'got_url_rewrite', '__return_true' );

        // Create the fonts directory if missing
        if(!file_exists(WP_CONTENT_DIR . '/fonts')) {
            mkdir(WP_CONTENT_DIR . '/fonts');
        }

        $log_file = WP_CONTENT_DIR . '/debug.log';
        define('ERROR_LOG_FILE', $log_file);
        ini_set('error_log', $log_file);
        ?>`
	);

	// Load the error handler before any other PHP file to ensure it
	// treats all the errors, even those trigerred before mu-plugins
	// are loaded.
	await php.writeFile(
		'/internal/shared/preload/error-handler.php',
		`<?php
		(function() {
			$playground_consts = [];
			if(file_exists('/internal/shared/consts.json')) {
				$playground_consts = @json_decode(file_get_contents('/internal/shared/consts.json'), true) ?: [];
				$playground_consts = array_keys($playground_consts);
			}
			set_error_handler(function($severity, $message, $file, $line) use($playground_consts) {
				/**
				 * This is a temporary workaround to hide the 32bit integer warnings that
				 * appear when using various time related function, such as strtotime and mktime.
				 * Examples of the warnings that are displayed:
				 *
				 * Warning: mktime(): Epoch doesn't fit in a PHP integer in <file>
				 * Warning: strtotime(): Epoch doesn't fit in a PHP integer in <file>
				 */
				if (strpos($message, "fit in a PHP integer") !== false) {
					return;
				}
				/**
				 * Networking support in Playground registers a http_api_transports filter.
				 *
				 * This filter is deprecated, and no longer actively used, but is needed for wp_http_supports().
				 * @see https://core.trac.wordpress.org/ticket/37708
				 */
				if (
					strpos($message, "http_api_transports") !== false &&
					strpos($message, "since version 6.4.0 with no alternative available") !== false
				) {
					return;
				}
				/**
				 * Playground defines some constants upfront, and some of them may be redefined
				 * in wp-config.php. For example, SITE_URL or WP_DEBUG. This is expected and
				 * we want Playground constants to take priority without showing warnings like:
				 *
				 * Warning: Constant SITE_URL already defined in
				 */
				if (strpos($message, "already defined") !== false) {
					foreach($playground_consts as $const) {
						if(strpos($message, "Constant $const already defined") !== false) {
							return;
						}
					}
				}
				/**
				 * Don't complain about network errors when not connected to the network.
				 */
				if (
					(
						! defined('USE_FETCH_FOR_REQUESTS') ||
						! USE_FETCH_FOR_REQUESTS
					) &&
					strpos($message, "WordPress could not establish a secure connection to WordPress.org") !== false)
				{
					return;
				}
				return false;
			});
		})();`
	);
}

/**
 * Runs phpinfo() when the requested path is /phpinfo.php.
 */
export async function preloadPhpInfoRoute(
	php: UniversalPHP,
	requestPath = '/phpinfo.php'
) {
	await php.writeFile(
		'/internal/shared/preload/phpinfo.php',
		`<?php
    // Render PHPInfo if the requested page is /phpinfo.php
    if ( ${phpVar(requestPath)} === $_SERVER['REQUEST_URI'] ) {
        phpinfo();
        exit;
    }
    `
	);
}

export async function preloadSqliteIntegration(
	php: UniversalPHP,
	sqliteZip: File
) {
	if (await php.isDir('/tmp/sqlite-database-integration')) {
		await php.rmdir('/tmp/sqlite-database-integration', {
			recursive: true,
		});
	}
	await php.mkdir('/tmp/sqlite-database-integration');
	await unzipFile(php, sqliteZip, '/tmp/sqlite-database-integration');
	const SQLITE_PLUGIN_FOLDER = '/internal/shared/sqlite-database-integration';

	const temporarySqlitePluginFolder = (await php.isDir(
		'/tmp/sqlite-database-integration/sqlite-database-integration-main'
	))
		? // This is the name when the dev branch used to be called "main"
		  '/tmp/sqlite-database-integration/sqlite-database-integration-main'
		: // This is the name today when the dev branch is called "develop"
		  '/tmp/sqlite-database-integration/sqlite-database-integration-develop';
	await php.mv(temporarySqlitePluginFolder, SQLITE_PLUGIN_FOLDER);

	// Prevents the SQLite integration from trying to call activate_plugin()
	await php.defineConstant('SQLITE_MAIN_FILE', '1');
	const dbCopy = await php.readFileAsText(
		joinPaths(SQLITE_PLUGIN_FOLDER, 'db.copy')
	);
	const dbPhp = dbCopy
		.replace(
			"'{SQLITE_IMPLEMENTATION_FOLDER_PATH}'",
			phpVar(SQLITE_PLUGIN_FOLDER)
		)
		.replace(
			"'{SQLITE_PLUGIN}'",
			phpVar(joinPaths(SQLITE_PLUGIN_FOLDER, 'load.php'))
		);
	const dbPhpPath = joinPaths(await php.documentRoot, 'wp-content/db.php');
	const stopIfDbPhpExists = `<?php
	// Do not preload this if WordPress comes with a custom db.php file.
	if(file_exists(${phpVar(dbPhpPath)})) {
		return;
	}
	?>`;
	const SQLITE_MUPLUGIN_PATH =
		'/internal/shared/mu-plugins/sqlite-database-integration.php';
	await php.writeFile(SQLITE_MUPLUGIN_PATH, stopIfDbPhpExists + dbPhp);
	await php.writeFile(
		`/internal/shared/preload/0-sqlite.php`,
		stopIfDbPhpExists +
			`<?php

/**
 * Loads the SQLite integration plugin before WordPress is loaded
 * and without creating a drop-in "db.php" file.
 *
 * Technically, it creates a global $wpdb object whose only two
 * purposes are to:
 *
 * * Exist – because the require_wp_db() WordPress function won't
 *           connect to MySQL if $wpdb is already set.
 * * Load the SQLite integration plugin the first time it's used
 *   and replace the global $wpdb reference with the SQLite one.
 *
 * This lets Playground keep the WordPress installation clean and
 * solves dillemas like:
 *
 * * Should we include db.php in Playground exports?
 * * Should we remove db.php from Playground imports?
 * * How should we treat stale db.php from long-lived OPFS sites?
 *
 * @see https://github.com/WordPress/wordpress-playground/discussions/1379 for
 *      more context.
 */
class Playground_SQLite_Integration_Loader {
	public function __call($name, $arguments) {
		$this->load_sqlite_integration();
		if($GLOBALS['wpdb'] === $this) {
			throw new Exception('Infinite loop detected in $wpdb – SQLite integration plugin could not be loaded');
		}
		return call_user_func_array(
			array($GLOBALS['wpdb'], $name),
			$arguments
		);
	}
	public function __get($name) {
		$this->load_sqlite_integration();
		if($GLOBALS['wpdb'] === $this) {
			throw new Exception('Infinite loop detected in $wpdb – SQLite integration plugin could not be loaded');
		}
		return $GLOBALS['wpdb']->$name;
	}
	public function __set($name, $value) {
		$this->load_sqlite_integration();
		if($GLOBALS['wpdb'] === $this) {
			throw new Exception('Infinite loop detected in $wpdb – SQLite integration plugin could not be loaded');
		}
		$GLOBALS['wpdb']->$name = $value;
	}
    protected function load_sqlite_integration() {
        require_once ${phpVar(SQLITE_MUPLUGIN_PATH)};
    }
}
$wpdb = $GLOBALS['wpdb'] = new Playground_SQLite_Integration_Loader();

/**
 * WordPress is capable of using a preloaded global $wpdb. However, if
 * it cannot find the drop-in db.php plugin it still checks whether
 * the mysqli_connect() function exists even though it's not used.
 *
 * What WordPress demands, Playground shall provide.
 */
if(!function_exists('mysqli_connect')) {
	function mysqli_connect() {}
}

		`
	);
	/**
	 * Ensure the SQLite integration is loaded and clearly communicate
	 * if it isn't. This is useful because WordPress database errors
	 * may be cryptic and won't mention the SQLite integration.
	 */
	await php.writeFile(
		`/internal/shared/mu-plugins/sqlite-test.php`,
		`<?php
		global $wpdb;
		if(!($wpdb instanceof WP_SQLite_DB)) {
			var_dump(isset($wpdb));
			die("SQLite integration not loaded " . get_class($wpdb));
		}
		`
	);
}

/**
 * Prepare the WordPress document root given a WordPress zip file and
 * the sqlite-database-integration zip file.
 *
 * This is a TypeScript function for now, just to get something off the
 * ground, but it may be superseded by the PHP Blueprints library developed
 * at https://github.com/WordPress/blueprints-library/
 *
 * That PHP library will come with a set of functions and a CLI tool to
 * turn a Blueprint into a WordPress directory structure or a zip Snapshot.
 * Let's **not** invest in the TypeScript implementation of this function,
 * accept the limitation, and switch to the PHP implementation as soon
 * as that's viable.
 */
export async function unzipWordPress(php: PHP, wpZip: File) {
	php.mkdir('/tmp/unzipped-wordpress');
	await unzipFile(php, wpZip, '/tmp/unzipped-wordpress');

	// The zip file may contain another zip file if it's coming from GitHub
	// artifacts @TODO: Don't make so many guesses about the zip file contents.
	// Allow the API consumer to specify the exact "coordinates" of WordPress
	// inside the zip archive.
	if (php.fileExists('/tmp/unzipped-wordpress/wordpress.zip')) {
		await unzipFile(
			php,
			'/tmp/unzipped-wordpress/wordpress.zip',
			'/tmp/unzipped-wordpress'
		);
	}

	// The zip file may contain a subdirectory, or not.
	// @TODO: Don't make so many guesses about the zip file contents. Allow the
	//        API consumer to specify the exact "coordinates" of WordPress inside
	//        the zip archive.
	let wpPath = php.fileExists('/tmp/unzipped-wordpress/wordpress')
		? '/tmp/unzipped-wordpress/wordpress'
		: php.fileExists('/tmp/unzipped-wordpress/build')
		? '/tmp/unzipped-wordpress/build'
		: '/tmp/unzipped-wordpress';

	// Dive one directory deeper if the zip root does not contain the sample
	// config file. This is relevant when unzipping a zipped branch from the
	// https://github.com/WordPress/WordPress repository.
	if (!php.fileExists(joinPaths(wpPath, 'wp-config-sample.php'))) {
		// Still don't know the directory structure of the zip file.
		// 1. Get the first item in path.
		const files = php.listFiles(wpPath);
		if (files.length) {
			const firstDir = files[0];
			// 2. If it's a directory that contains wp-config-sample.php, use it.
			if (
				php.fileExists(
					joinPaths(wpPath, firstDir, 'wp-config-sample.php')
				)
			) {
				wpPath = joinPaths(wpPath, firstDir);
			}
		}
	}

	if (
		php.isDir(php.documentRoot) &&
		isCleanDirContainingSiteMetadata(php.documentRoot, php)
	) {
		// We cannot mv the directory over a non-empty directory,
		// but we can move the children one by one.
		for (const file of php.listFiles(wpPath)) {
			const sourcePath = joinPaths(wpPath, file);
			const targetPath = joinPaths(php.documentRoot, file);
			php.mv(sourcePath, targetPath);
		}
		php.rmdir(wpPath, { recursive: true });
	} else {
		php.mv(wpPath, php.documentRoot);
	}

	if (
		!php.fileExists(joinPaths(php.documentRoot, 'wp-config.php')) &&
		php.fileExists(joinPaths(php.documentRoot, 'wp-config-sample.php'))
	) {
		php.writeFile(
			joinPaths(php.documentRoot, 'wp-config.php'),
			php.readFileAsText(
				joinPaths(php.documentRoot, '/wp-config-sample.php')
			)
		);
	}
}

function isCleanDirContainingSiteMetadata(path: string, php: PHP) {
	const files = php.listFiles(path);
	if (files.length === 0) {
		return true;
	}

	if (
		files.length === 1 &&
		// TODO: use a constant from a site storage package
		files[0] === 'playground-site-metadata.json'
	) {
		return true;
	}

	return false;
}

const memoizedFetch = createMemoizedFetch(fetch);

/**
 * Resolves a specific WordPress release URL and version string based on
 * a version query string such as "latest", "beta", or "6.6".
 *
 * Examples:
 * ```js
 * const { releaseUrl, version } = await resolveWordPressRelease('latest')
 * // becomes https://wordpress.org/wordpress-6.6.2.zip and '6.6.2'
 *
 * const { releaseUrl, version } = await resolveWordPressRelease('beta')
 * // becomes https://wordpress.org/wordpress-6.6.2-RC1.zip and '6.6.2-RC1'
 *
 * const { releaseUrl, version } = await resolveWordPressRelease('6.6')
 * // becomes https://wordpress.org/wordpress-6.6.2.zip and '6.6.2'
 * ```
 *
 * @param versionQuery - The WordPress version query string to resolve.
 * @returns The resolved WordPress release URL and version string.
 */
export async function resolveWordPressRelease(versionQuery = 'latest') {
	if (
		versionQuery.startsWith('https://') ||
		versionQuery.startsWith('http://')
	) {
		const shasum = await crypto.subtle.digest(
			'SHA-1',
			new TextEncoder().encode(versionQuery)
		);
		const sha1 = Array.from(new Uint8Array(shasum))
			.map((b) => b.toString(16).padStart(2, '0'))
			.join('');
		return {
			releaseUrl: versionQuery,
			version: 'custom-' + sha1.substring(0, 8),
			source: 'inferred',
		};
	} else if (versionQuery === 'trunk' || versionQuery === 'nightly') {
		return {
			releaseUrl:
				'https://wordpress.org/nightly-builds/wordpress-latest.zip',
			version: 'nightly-' + new Date().toISOString().split('T')[0],
			source: 'inferred',
		};
	}

	const response = await memoizedFetch(
		'https://api.wordpress.org/core/version-check/1.7/?channel=beta'
	);
	let latestVersions = await response.json();

	latestVersions = latestVersions.offers.filter(
		(v: any) => v.response === 'autoupdate'
	);

	for (const apiVersion of latestVersions) {
		if (versionQuery === 'beta' && apiVersion.version.includes('beta')) {
			return {
				releaseUrl: apiVersion.download,
				version: apiVersion.version,
				source: 'api',
			};
		} else if (
			versionQuery === 'latest' &&
			!apiVersion.version.includes('beta')
		) {
			// The first non-beta item in the list is the latest version.
			return {
				releaseUrl: apiVersion.download,
				version: apiVersion.version,
				source: 'api',
			};
		} else if (
			apiVersion.version.substring(0, versionQuery.length) ===
			versionQuery
		) {
			return {
				releaseUrl: apiVersion.download,
				version: apiVersion.version,
				source: 'api',
			};
		}
	}

	return {
		releaseUrl: `https://wordpress.org/wordpress-${versionQuery}.zip`,
		version: versionQuery,
		source: 'inferred',
	};
}
