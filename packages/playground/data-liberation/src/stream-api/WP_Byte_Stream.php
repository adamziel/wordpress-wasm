<?php

abstract class WP_Byte_Stream {

	protected $state;

	public function __construct() {
		$this->state = new WP_Byte_Stream_State();
	}

	public function is_eof(): bool {
		return ! $this->state->output_bytes && $this->state->state === WP_Byte_Stream_State::STATE_FINISHED;
	}

	public function get_file_id() {
		return $this->state->file_id;
	}

	public function skip_file(): void {
		$this->state->last_skipped_file = $this->state->file_id;
	}

	public function is_skipped_file() {
		return $this->state->file_id === $this->state->last_skipped_file;
	}

	public function get_chunk_type() {
		if ( $this->get_last_error() ) {
			return '#error';
		}

		if ( $this->is_eof() ) {
			return '#eof';
		}

		return '#bytes';
	}

	public function append_eof() {
		$this->state->input_eof = true;
	}

	public function append_bytes( string $bytes, $context = null ) {
		$this->state->input_bytes  .= $bytes;
		$this->state->input_context = $context;
	}

	public function get_bytes() {
		return $this->state->output_bytes;
	}

	public function next_bytes() {
		$this->state->reset_output();
		if ( $this->is_eof() ) {
			return false;
		}

		// Process any remaining buffered input:
		if ( $this->generate_next_chunk() ) {
			return ! $this->is_skipped_file();
		}

		if ( ! $this->state->input_bytes ) {
			if ( $this->state->input_eof ) {
				$this->state->finish();
			}
			return false;
		}

		$produced_bytes = $this->generate_next_chunk();

		return $produced_bytes && ! $this->is_skipped_file();
	}

	abstract protected function generate_next_chunk(): bool;

	public function get_last_error(): string|null {
		return $this->state->last_error;
	}
}
