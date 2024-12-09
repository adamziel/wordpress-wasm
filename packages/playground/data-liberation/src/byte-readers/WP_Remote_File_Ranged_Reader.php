<?php
/**
 * Streams bytes from a remote file. Supports seeking to a specific offset and
 * requesting sub-ranges of the file.
 *
 * Usage:
 *
 * $file = new WP_Remote_File_Ranged_Reader('https://example.com/file.txt');
 * $file->seek(0);
 * $file->request_bytes(100);
 * while($file->next_chunk()) {
 *     var_dump($file->get_bytes());
 * }
 * $file->seek(600);
 * $file->request_bytes(40);
 * while($file->next_chunk()) {
 *     var_dump($file->get_bytes());
 * }
 *
 * @TODO: Verify that the remote server supports range requests.
 * @TODO: Support requesting multiple ranges in a single request.
 * @TODO: Abort in-progress requests when seeking to a new offset.
 */
class WP_Remote_File_Ranged_Reader {

	/**
	 * @var WordPress\AsyncHttp\Client
	 */
	private $client;
	private $url;
	private $remote_file_length;

	private $current_request;
	private $offset_in_remote_file   = 0;
	private $offset_in_current_chunk = 0;
	private $current_chunk;
	private $expected_chunk_size;

	public function __construct( $url, $options = array() ) {
		$this->client = new WordPress\AsyncHttp\Client();
		$this->url    = $url;
	}

	public function request_bytes( $bytes ) {
		if ( null === $this->remote_file_length ) {
			$content_length = $this->resolve_content_length();
			if ( false === $content_length ) {
				// The remote server won't tell us what the content length is
				// @TODO: What should we do in this case? Content-length is critical for
				//        stream-decompressing remote zip files, but we may not need it
				//        for other use-cases.
				return false;
			}
			$this->remote_file_length = $content_length;
		}

		if ( $this->offset_in_remote_file < 0 || $this->offset_in_remote_file + $bytes > $this->remote_file_length ) {
			// TODO: Think through error handling
			return false;
		}

		$this->seek( $this->offset_in_remote_file );

		$this->current_request         = new WordPress\AsyncHttp\Request(
			$this->url,
			array(
				'headers' => array(
					'Range' => 'bytes=' . $this->offset_in_remote_file . '-' . ( $this->offset_in_remote_file + $bytes - 1 ),
				),
			)
		);
		$this->expected_chunk_size     = $bytes;
		$this->offset_in_current_chunk = 0;
		if ( false === $this->client->enqueue( $this->current_request ) ) {
			// TODO: Think through error handling
			return false;
		}
		return true;
	}

	public function seek( $offset ) {
		$this->offset_in_remote_file = $offset;
		// @TODO cancel any pending requests
		$this->current_request = null;
	}

	public function tell() {
		return $this->offset_in_remote_file;
	}

	public function resolve_content_length() {
		if ( null !== $this->remote_file_length ) {
			return $this->remote_file_length;
		}

		$request = new WordPress\AsyncHttp\Request(
			$this->url,
			array( 'method' => 'HEAD' )
		);
		if ( false === $this->client->enqueue( $request ) ) {
			// TODO: Think through error handling
			return false;
		}
		while ( $this->client->await_next_event() ) {
			switch ( $this->client->get_event() ) {
				case WordPress\AsyncHttp\Client::EVENT_GOT_HEADERS:
					$response = $request->response;
					if ( false === $response ) {
						return false;
					}
					$content_length = $response->get_header( 'Content-Length' );
					if ( false === $content_length ) {
						return false;
					}
					return (int) $content_length;
			}
		}
		return false;
	}

	public function next_chunk() {
		while ( $this->client->await_next_event() ) {
			/**
			 * Only process events related to the most recent request.
			 * @TODO: Support redirects.
			 * @TODO: Cleanup resources for stale requests.
			 */
			if ( $this->current_request->id !== $this->client->get_request()->id ) {
				continue;
			}

			if ( $this->offset_in_current_chunk >= $this->expected_chunk_size ) {
				// The remote server doesn't support range requests and sent us a chunk larger than expected.
				// @TODO: Handle this case. Should we stream the entire file, or give up?
				//        Should we cache the download locally, or request the entire file again every
				//        time we need to seek()?
				return false;
			}

			switch ( $this->client->get_event() ) {
				case WordPress\AsyncHttp\Client::EVENT_GOT_HEADERS:
					$request = $this->client->get_request();
					if ( ! $request ) {
						return false;
					}
					$response = $request->response;
					if ( false === $response ) {
						return false;
					}
					if (
						$response->status_code !== 206 ||
						false === $response->get_header( 'Range' )
					) {
						// The remote server doesn't support range requests
						// @TODO: Handle this case. Should we stream the entire file, or give up?
						//        Should we cache the download locally, or request the entire file again every
						//        time we need to seek()?
						return false;
					}
					break;
				case WordPress\AsyncHttp\Client::EVENT_BODY_CHUNK_AVAILABLE:
					$chunk = $this->client->get_response_body_chunk();
					if ( ! is_string( $chunk ) ) {
						// TODO: Think through error handling
						return false;
					}
					$this->current_chunk            = $chunk;
					$this->offset_in_remote_file   += strlen( $chunk );
					$this->offset_in_current_chunk += strlen( $chunk );

					return true;
				case WordPress\AsyncHttp\Client::EVENT_FAILED:
					// TODO: Think through error handling. Errors are expected when working with
					//       the network. Should we auto retry? Make it easy for the caller to retry?
					//       Something else?
					return false;
				case WordPress\AsyncHttp\Client::EVENT_FINISHED:
					// TODO: Think through error handling
					return false;
			}
		}
	}

	public function get_bytes() {
		return $this->current_chunk;
	}
}
