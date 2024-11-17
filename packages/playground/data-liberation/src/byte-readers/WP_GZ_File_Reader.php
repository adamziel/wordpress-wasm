<?php

class WP_GZ_File_Reader extends WP_File_Reader {

	public function next_bytes(): bool {
		$this->output_bytes = '';
		if ( $this->last_error || $this->is_finished() ) {
			return false;
		}
		if ( ! $this->file_pointer ) {
			$this->file_pointer = gzopen( $this->file_path, 'r' );
			if ( $this->offset_in_file ) {
				gzseek( $this->file_pointer, $this->offset_in_file );
			}
		}
		$bytes = gzread( $this->file_pointer, $this->chunk_size );
		if ( ! $bytes && gzeof( $this->file_pointer ) ) {
			gzclose( $this->file_pointer );
			$this->state->finish();
			return false;
		}
		$this->offset_in_file += strlen( $bytes );
		$this->output_bytes   .= $bytes;
		return true;
	}
}
