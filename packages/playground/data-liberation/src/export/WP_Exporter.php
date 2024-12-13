<?php

use WordPress\Zip\ZipStreamReader;
use WordPress\Zip\ZipStreamWriter;

class WP_Exporter {
	public static function stream_export( $output_stream = false ) {
		// @TODO: This is a hack. Maybe we should have a way to export without setting headers.
		$preexisting_response_headers = headers_list();

		require_once ABSPATH . 'wp-admin/includes/export.php';
		ob_start();
		export_wp();

		// @TODO: This is a hack to avoid headers set by export_wp(). Maybe we should have a way to export without setting headers.
		header_remove();
		foreach ( $preexisting_response_headers as $header ) {
			header( $header, false );
		}

		$wxr_content = ob_get_clean();

		// @TODO: Replace upload URLs with relative file URLs.

		header('Content-Type: application/zip');

		// @TODO: Can we get rid of this open-stdout-on-demand workaround?
		// NOTE: Opening stdout on demand after output buffering the export
		// because output buffering seemed to interfere with a preexisting stdout stream.
		// By opening stdout after output buffering, streaming the zip to stdout appears to work.
		if ( !$output_stream ) {
			$output_stream = fopen('php://output', 'wb');
		}
		$zip_writer = new ZipStreamWriter( $output_stream );
		$zip_writer->writeFileFromString( 'META-INF/export.wxr', $wxr_content );

		$uploads = wp_upload_dir();
		$uploads_path = $uploads['basedir'];

		$flags = \FilesystemIterator::SKIP_DOTS;
		$uploads_iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator(
				$uploads_path,
				$flags
			)	
		);

		foreach ( $uploads_iterator as $file ) {
			if ( $file->isDir() ) {
				continue;
			}
			$absolute_path = $file->getPathname();
			$relative_path = substr( $absolute_path, strlen($uploads_path) + 1 );
			$zip_writer->writeFileFromPath(
				// TODO: How to handle unconventional upload locations?
				"wp-content/uploads/$relative_path",
				$absolute_path
			);

			// TODO: Is this necessary to make sure per-file output is flushed?
			fflush( $output_stream );
		}

		$zip_writer->finish();
	}
}