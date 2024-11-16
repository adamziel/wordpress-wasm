<?php

use WordPress\AsyncHTTP\Client;
use WordPress\AsyncHTTP\Request;

class WP_Attachment_Downloader {
	private $client;
	private $fps = array();
	private $output_root;
	private $partial_files = array();
	private $output_paths  = array();

	public function __construct( $output_root ) {
		$this->client      = new Client();
		$this->output_root = $output_root;
	}

	public function enqueue_if_not_exists( $url, $output_path = null ) {
		if ( null === $output_path ) {
			// Use the path from the URL.
			$parsed_url = parse_url( $url );
			if ( false === $parsed_url ) {
				return false;
			}
			$output_path = $parsed_url['path'];
		}
		$output_path = $this->output_root . '/' . ltrim( $output_path, '/' );
		if ( file_exists( $output_path ) ) {
			// @TODO: Reconsider the return value. The enqueuing operation failed,
			//        but overall already having a file seems like a success.
			return true;
		}

		$output_dir = dirname( $output_path );
		if ( ! file_exists( $output_dir ) ) {
			// @TODO: think through the chmod of the created directory.
			mkdir( $output_dir, 0777, true );
		}

		$protocol = parse_url( $url, PHP_URL_SCHEME );
		if ( null === $protocol ) {
			return false;
		}

		switch ( $protocol ) {
			case 'file':
				$local_path = parse_url( $url, PHP_URL_PATH );
				if ( false === $local_path ) {
					return false;
				}
				// Just copy the file over.
				// @TODO: think through the chmod of the created file.
				return copy( $local_path, $output_path );
			case 'http':
			case 'https':
				$request                            = new Request( $url );
				$this->output_paths[ $request->id ] = $output_path;
				$this->client->enqueue( $request );
				return true;
		}
		return false;
	}

	public function queue_full() {
		return count( $this->client->get_active_requests() ) >= 10;
	}

	public function poll() {
		if ( ! $this->client->await_next_event() ) {
			return false;
		}
		$event   = $this->client->get_event();
		$request = $this->client->get_request();
		// The request object we get from the client may be a redirect.
		// Let's keep referring to the original request.
		$original_request_id = $request->original_request()->id;

		switch ( $event ) {
			case Client::EVENT_GOT_HEADERS:
				if ( ! $request->is_redirected() ) {
					$this->partial_files[ $original_request_id ] = $this->output_paths[ $original_request_id ] . '.partial';
					if ( file_exists( $this->partial_files[ $original_request_id ] ) ) {
						unlink( $this->partial_files[ $original_request_id ] );
					}
					$this->fps[ $original_request_id ] = fopen( $this->output_paths[ $original_request_id ] . '.partial', 'wb' );
					if ( false === $this->fps[ $original_request_id ] ) {
						// @TODO: Log an error.
					}
				}
				break;
			case Client::EVENT_BODY_CHUNK_AVAILABLE:
				$chunk = $this->client->get_response_body_chunk();
				if ( false === fwrite( $this->fps[ $original_request_id ], $chunk ) ) {
					// @TODO: Log an error.
				}
				break;
			case Client::EVENT_FAILED:
				if ( isset( $this->fps[ $original_request_id ] ) ) {
					fclose( $this->fps[ $original_request_id ] );
				}
				if ( isset( $this->partial_files[ $original_request_id ] ) ) {
					$partial_file = $this->output_root . '/' . $this->partial_files[ $original_request_id ] . '.partial';
					if ( file_exists( $partial_file ) ) {
						unlink( $partial_file );
					}
				}
				unset( $this->output_paths[ $original_request_id ] );
				break;
			case Client::EVENT_FINISHED:
				if ( ! $request->is_redirected() ) {
					// Only clean up if this was the last request in the chain.
					if ( isset( $this->fps[ $original_request_id ] ) ) {
						fclose( $this->fps[ $original_request_id ] );
					}
					if ( isset( $this->output_paths[ $original_request_id ] ) && isset( $this->partial_files[ $original_request_id ] ) ) {
						if ( false === rename(
							$this->partial_files[ $original_request_id ],
							$this->output_paths[ $original_request_id ]
						) ) {
							// @TODO: Log an error.
						}
					}
					unset( $this->partial_files[ $original_request_id ] );
				}
				break;
		}

		return true;
	}
}
