<?php

class WP_GZ_File_Reader extends WP_File_Reader {

	protected function generate_next_chunk(): bool {
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
		$this->offset_in_file      += strlen( $bytes );
		$this->state->output_bytes .= $bytes;
		return true;
	}
}
