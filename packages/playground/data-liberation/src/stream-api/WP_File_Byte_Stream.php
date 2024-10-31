<?php

class WP_File_Byte_Stream extends WP_Byte_Stream {

	protected $file_path;
	protected $chunk_size;
	protected $file_pointer;
	protected $offset_in_file;

	public function __construct( $file_path, $chunk_size = 8096 ) {
		$this->file_path  = $file_path;
		$this->chunk_size = $chunk_size;
		parent::__construct();
		$this->append_eof();
	}

	public function pause() {
		return array(
			'file_path' => $this->file_path,
			'chunk_size' => $this->chunk_size,
			'offset_in_file' => $this->offset_in_file,
			'output_bytes' => $this->state->output_bytes,
		);
	}

	public function resume( $paused_state ) {
		$this->offset_in_file      = $paused_state['offset_in_file'];
		$this->state->output_bytes = $paused_state['output_bytes'];
	}

	protected function generate_next_chunk(): bool {
		if ( ! $this->file_pointer ) {
			$this->file_pointer = fopen( $this->file_path, 'r' );
			if ( $this->offset_in_file ) {
				fseek( $this->file_pointer, $this->offset_in_file );
			}
		}
		$bytes = fread( $this->file_pointer, $this->chunk_size );
		if ( ! $bytes && feof( $this->file_pointer ) ) {
			fclose( $this->file_pointer );
			$this->state->finish();
			return false;
		}
		$this->offset_in_file      += strlen( $bytes );
		$this->state->output_bytes .= $bytes;
		return true;
	}
}
