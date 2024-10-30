<?php
/**
 * Initialize the Playground request handler
 * on the Playground.WordPress.net server.
 */
if ( 'cli' !== php_sapi_name() ) {
	define('PLAYGROUND_DEBUG', false);
	require_once __DIR__ . '/custom-redirects-lib.php';
	playground_handle_request();
}
