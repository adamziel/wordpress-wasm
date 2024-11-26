<?php

require_once __DIR__ . '/WP_Import_Logger.php';

/**
 * Implements the `wp data-liberation` command.
 *
 * ## EXAMPLES
 *
 *     # Import a WXR file.
 *     wp data-liberation import /path/to/file.xml
 *
 *     # Import all files inside a folder.
 *     wp data-liberation import /path/to/folder
 *
 *     # Import a WXR file from a URL.
 *     wp data-liberation import http://example.com/file.xml
 *
 *     Success: Imported data.
 */
class WP_Import_Command {
	/**
	 * @var bool $dry_run Whether to perform a dry run.
	 */
	private $dry_run = false;

	/**
	 * @var WP_Stream_Importer $importer The importer instance.
	 */
	private $importer = null;

	private $wxr_path = '';

	/**
	 * Import a WXR file.
	 *
	 * ## OPTIONS
	 *
	 * <path>
	 * : The path to the WXR file. Either a file, a directory or a URL.
	 *
	 * [--dry-run]
	 * : Perform a dry run if set.
	 *
	 * ## EXAMPLES
	 *
	 *     wp data-liberation import /path/to/file.xml
	 *
	 * @param array $args
	 * @param array $assoc_args
	 * @return void
	 */
	public function import( $args, $assoc_args ) {
		$path          = $args[0];
		$this->dry_run = WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );
		$options       = array(
			'logger' => new WP_Import_logger(),
		);

		if ( extension_loaded( 'pcntl' ) ) {
			// Set the signal handler.
			$this->register_handlers();
		}

		if ( filter_var( $path, FILTER_VALIDATE_URL ) ) {
			// Import URL.
			$this->import_wxr_url( $path, $options );
		} elseif ( is_dir( $path ) ) {
			$count = 0;
			// Get all the WXR files in the directory.
			foreach ( wp_visit_file_tree( $path ) as $event ) {
				foreach ( $event->files as $file ) {
					if ( $file->isFile() && 'xml' === pathinfo( $file->getPathname(), PATHINFO_EXTENSION ) ) {
						++$count;

						// Import the WXR file.
						$this->import_wxr_file( $file->getPathname(), $options );
					}
				}
			}

			if ( ! $count ) {
				WP_CLI::error( WP_CLI::colorize( "No WXR files found in the {$path} directory" ) );
			}
		} else {
			if ( ! is_file( $path ) ) {
				WP_CLI::error( WP_CLI::colorize( "File not found: %R{$path}%n" ) );
			}

			// Import the WXR file.
			$this->import_wxr_file( $path, $options );
		}
	}

	/**
	 * Import a WXR file.
	 *
	 * @param string $file_path The path to the WXR file.
	 * @return void
	 */
	private function import_wxr_file( $file_path, $options = array() ) {
		$this->wxr_path = $file_path;
		$this->importer = WP_Stream_Importer::create_for_wxr_file( $file_path, $options );

		$this->import_wxr();
	}

	/**
	 * Import a WXR file from a URL.
	 *
	 * @param string $url The URL to the WXR file.
	 * @return void
	 */
	private function import_wxr_url( $url, $options = array() ) {
		$this->wxr_path = $url;
		$this->importer = WP_Stream_Importer::create_for_wxr_url( $url, $options );

		$this->import_wxr();
	}

	/**
	 * Import the WXR file.
	 */
	private function import_wxr() {
		if ( ! $this->importer ) {
			WP_CLI::error( 'Could not create importer' );
		}

		WP_CLI::line( "Importing {$this->wxr_path}" );

		if ( $this->dry_run ) {
			WP_CLI::line( 'Dry run enabled.' );
		} else {
			while ( $this->importer->next_step() ) {
				$current_stage = $this->importer->get_current_stage();
				// WP_CLI::line( "Stage {$current_stage}" );
			}
		}

		WP_CLI::success( 'Import finished' );
	}

	/**
	 * Callback function registered to `pcntl_signal` to handle signals.
	 *
	 * @param int $signal The signal number.
	 * @return void
	 */
	protected function signal_handler( $signal ) {
		switch ( $signal ) {
			case SIGINT:
				WP_CLI::line( 'Received SIGINT signal' );
				exit( 0 );

			case SIGTERM:
				WP_CLI::line( 'Received SIGTERM signal' );
				exit( 0 );
		}
	}

	/**
	 * Register signal handlers for the command.
	 *
	 * @return void
	 */
	private function register_handlers() {
		// Handle the Ctrl + C signal to terminate the program.
		pcntl_signal( SIGINT, array( $this, 'signal_handler' ) );

		// Handle the `kill` command to terminate the program.
		pcntl_signal( SIGTERM, array( $this, 'signal_handler' ) );
	}
}
