<?php

/**
 * This interface describes standalone streams, but it can also be
 * used to describe a stream Processor like WP_XML_Processor.
 *
 * In this prototype there are no pipes, streams, and processors. There
 * are only Byte Streams that can be chained together with the StreamChain
 * class.
 */
class WP_Byte_Stream_State {
	const STATE_STREAMING = '#streaming';
	const STATE_FINISHED  = '#finished';

	public $input_eof     = false;
	public $input_bytes   = null;
	public $output_bytes  = null;
	public $state         = self::STATE_STREAMING;
	public $last_error    = null;
	public $input_context = null;

	public $file_id;
	public $last_skipped_file;

	public function reset_output() {
		$this->output_bytes = null;
		$this->file_id      = 'default';
		$this->last_error   = null;
	}

	public function consume_input_bytes() {
		$bytes             = $this->input_bytes;
		$this->input_bytes = null;
		return $bytes;
	}

	public function finish() {
		$this->state = self::STATE_FINISHED;
	}
}
