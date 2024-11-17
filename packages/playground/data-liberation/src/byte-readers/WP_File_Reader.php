<?php

class WP_File_Reader implements WP_Byte_Reader {

	const STATE_STREAMING = '#streaming';
	const STATE_FINISHED  = '#finished';

	protected $file_path;
	protected $chunk_size;
	protected $file_pointer;
	protected $offset_in_file;
	protected $output_bytes;
	protected $last_error;
	protected $state = self::STATE_STREAMING;

	public function __construct( $file_path, $chunk_size = 8096 ) {
		$this->file_path  = $file_path;
		$this->chunk_size = $chunk_size;
	}

	/**
	 * Really these are just `tell()` and `seek()` operations, only the state is more
	 * involved than a simple offset. Hmm.
	 */
	public function pause(): array|bool {
		return array(
			'offset_in_file' => $this->offset_in_file,
		);
	}

	public function resume( $paused_state ): bool {
		if ( $this->file_pointer ) {
			_doing_it_wrong( __METHOD__, 'Cannot resume a file reader that is already initialized.', '1.0.0' );
			return false;
		}
		$this->offset_in_file = $paused_state['offset_in_file'];
		return true;
	}

	public function is_finished(): bool {
		return ! $this->output_bytes && $this->state === static::STATE_FINISHED;
	}

	public function get_bytes(): string {
		return $this->output_bytes;
	}

	public function get_last_error(): string|null {
		return $this->last_error;
	}

	public function next_bytes(): bool {
		$this->output_bytes = '';
		if ( $this->last_error || $this->is_finished() ) {
			return false;
		}
		if ( ! $this->file_pointer ) {
			$this->file_pointer = fopen( $this->file_path, 'r' );
			if ( $this->offset_in_file ) {
				fseek( $this->file_pointer, $this->offset_in_file );
			}
		}
		$bytes = fread( $this->file_pointer, $this->chunk_size );
		if ( ! $bytes && feof( $this->file_pointer ) ) {
			fclose( $this->file_pointer );
			$this->state = static::STATE_FINISHED;
			return false;
		}
		$this->offset_in_file += strlen( $bytes );
		$this->output_bytes   .= $bytes;
		return true;
	}
}
