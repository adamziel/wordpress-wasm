<?php

/**
 * Entity importer logger class.
 */
class WP_Logger {
	/**
	 * Log a debug message.
	 *
	 * @param string $message Message to log
	 */
	public function debug( $message ) {
		// echo( '[DEBUG] ' . $message );
	}

	/**
	 * Log an info message.
	 *
	 * @param string $message Message to log
	 */
	public function info( $message ) {
		// echo( '[INFO] ' . $message );
	}

	/**
	 * Log a warning message.
	 *
	 * @param string $message Message to log
	 */
	public function warning( $message ) {
		echo( "[WARNING] {$message}\n" );
	}

	/**
	 * Log an error message.
	 *
	 * @param string $message Message to log
	 */
	public function error( $message ) {
		echo( "[ERROR] $message\n" );
	}

	/**
	 * Log a notice message.
	 *
	 * @param string $message Message to log
	 */
	public function notice( $message ) {
		// echo( '[NOTICE] ' . $message );
	}
}
