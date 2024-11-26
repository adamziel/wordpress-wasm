<?php

/**
 * Entity importer logger class.
 */
class WP_Import_Logger {
	/**
	 * Log a debug message.
	 *
	 * @param string $message Message to log
	 */
	public function debug( $message ) {
		WP_CLI::debug( $message );
	}

	/**
	 * Log an info message.
	 *
	 * @param string $message Message to log
	 */
	public function info( $message ) {
		WP_CLI::log( $message );
	}

	/**
	 * Log a warning message.
	 *
	 * @param string $message Message to log
	 */
	public function warning( $message ) {
		WP_CLI::warning( $message );
	}

	/**
	 * Log an error message.
	 *
	 * @param string $message Message to log
	 */
	public function error( $message ) {
		WP_CLI::error( $message, false );
	}

	/**
	 * Log a notice message.
	 *
	 * @param string $message Message to log
	 */
	public function notice( $message ) {
		WP_CLI::log( $message );
	}
}
