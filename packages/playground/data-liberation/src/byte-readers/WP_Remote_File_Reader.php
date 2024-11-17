<?php

/**
 * Streams bytes from a remote file.
 */
class WP_Remote_File_Reader implements WP_Byte_Reader {

	/**
	 * @var WordPress\AsyncHttp\Client
	 */
	private $client;
	private $url;
	private $request;
	private $current_chunk;
	private $last_error;
	private $is_finished;
	private $bytes_already_read;
	private $skip_bytes = 0;

	public function __construct( $url ) {
		$this->client = new WordPress\AsyncHttp\Client();
		$this->url    = $url;
	}

	public function next_bytes(): bool {
		if ( null === $this->request ) {
			$this->request = new WordPress\AsyncHttp\Request(
				$this->url
			);
			if ( false === $this->client->enqueue( $this->request ) ) {
				// TODO: Think through error handling
				return false;
			}
		}

		$this->after_chunk();

		while ( $this->client->await_next_event() ) {
			switch ( $this->client->get_event() ) {
				case WordPress\AsyncHttp\Client::EVENT_BODY_CHUNK_AVAILABLE:
					$chunk = $this->client->get_response_body_chunk();
					if ( ! is_string( $chunk ) ) {
						// TODO: Think through error handling
						return false;
					}
					$this->current_chunk = $chunk;

					/**
					 * Naive seek() implementation – redownload the file from the start
					 * and ignore bytes until we reach the desired offset.
					 *
					 * @TODO: Use the range requests instead when the server supports them.
					 */
					if ( $this->skip_bytes > 0 ) {
						if ( $this->skip_bytes < strlen( $chunk ) ) {
							$this->current_chunk       = substr( $chunk, $this->skip_bytes );
							$this->bytes_already_read += $this->skip_bytes;
							$this->skip_bytes          = 0;
						} else {
							$this->skip_bytes -= strlen( $chunk );
							continue 2;
						}
					}
					return true;
				case WordPress\AsyncHttp\Client::EVENT_FAILED:
					// TODO: Think through error handling. Errors are expected when working with
					//       the network. Should we auto retry? Make it easy for the caller to retry?
					//       Something else?
					$this->last_error = $this->client->get_request()->error;
					return false;
				case WordPress\AsyncHttp\Client::EVENT_FINISHED:
					$this->is_finished = true;
					return false;
			}
		}
	}

	private function after_chunk() {
		if ( $this->current_chunk ) {
			$this->bytes_already_read += strlen( $this->current_chunk );
		}
		$this->current_chunk = null;
	}

	public function get_last_error(): string|null {
		return $this->last_error;
	}

	public function get_bytes(): string|null {
		return $this->current_chunk;
	}

	public function pause(): array|bool {
		return array(
			'offset_in_file' => $this->bytes_already_read + $this->skip_bytes,
		);
	}

	public function resume( $paused_state ): bool {
		if ( $this->request ) {
			_doing_it_wrong( __METHOD__, 'Cannot resume a remote file reader that is already initialized.', '1.0.0' );
			return false;
		}
		$this->skip_bytes = $paused_state['offset_in_file'];
		return true;
	}

	public function is_finished(): bool {
		return $this->is_finished;
	}
}
