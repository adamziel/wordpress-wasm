<?php
/**
 * Pipeline for passing data through a chain of stream processors.
 *
 * Design goals:
 *
 * * Easy to use API
 * * Can process 1TB of data with 100MB RAM
 * * Can be paused and resumed
 * * Can be resumed after a crash
 * * Can chain together any number of stream processors
 *
 * This API is still a work in progress and may change heavily.
 *
 * A number of other approaches were explored in a separate PR.
 * Consult it for reasoning and usage examples:
 *
 * https://github.com/adamziel/wxr-normalize/pull/1
 *
 * @TODO: Allow each stream to indicate its output reached EOF
 *        and propagate that information downstream. Otherwise,
 *        WP_XML_Processor will always end in an "incomplete input"
 *        state.
 */
class WP_Stream_Chain extends WP_Byte_Stream implements ArrayAccess, Iterator {
	private $first_stream;
	private $last_stream;
	/**
	 * @var WP_Byte_Stream[]
	 */
	private $streams         = array();
	private $streams_names   = array();
	private $execution_stack = array();
	private $chunk_context   = array();

	public function __construct( $streams ) {
		$this->chunk_context['chain'] = $this;

		$named_streams = array();
		foreach ( $streams as $name => $stream ) {
			$string_name                   = is_numeric( $name ) ? 'stream_' . $name : $name;
			$named_streams[ $string_name ] = $streams[ $name ];
		}

		$this->streams       = $named_streams;
		$this->streams_names = array_keys( $this->streams );
		$this->first_stream  = $this->streams[ $this->streams_names[0] ];
		$this->last_stream   = $this->streams[ $this->streams_names[ count( $streams ) - 1 ] ];
		parent::__construct();
	}

	public function pause() {
		$paused_streams = array();
		foreach ( $this->streams as $name => $stream ) {
			$paused_streams[ $name ] = $stream->pause();
		}
		$paused_execution_stack = array();
		foreach ( $this->execution_stack as $stream ) {
			$name                     = array_search( $stream, $this->streams, true );
			$paused_execution_stack[] = $name;
		}
		return array(
			'streams' => $paused_streams,
			'execution_stack' => $paused_execution_stack,
		);
	}

	public function resume( $paused_state ) {
		foreach ( $paused_state['streams'] as $name => $stream ) {
			$this->streams[ $name ]->resume( $stream );
		}
		foreach ( $paused_state['execution_stack'] as $name ) {
			$this->push_stream( $this->streams[ $name ] );
		}
	}

	public function run_to_completion() {
		$output = '';
		foreach ( $this as $chunk ) {
			switch ( $chunk->get_chunk_type() ) {
				case '#error':
					return false;
				case '#bytes':
					$output .= $chunk->get_bytes();
					break;
			}
		}
		return $output;
	}

	/**
	 * ## Next chunk generation
	 *
	 * Pushes data through a chain of streams. Every downstream data chunk
	 * is fully processed before asking for more chunks upstream.
	 *
	 * For example, suppose we:
	 *
	 * * Send 3 HTTP requests, and each of them produces a ZIP file
	 * * Each ZIP file has 3 XML files inside
	 * * Each XML file is rewritten using the XML_Processor
	 *
	 * Once the HTTP client has produced the first ZIP file, we start processing it.
	 * The ZIP decoder may already have enough data to unzip three files, but we only
	 * produce the first chunk of the first file and pass it to the XML processor.
	 * Then we handle the second chunk of the first file, and so on, until the first
	 * file is fully processed. Only then we move to the second file.
	 *
	 * Then, once the ZIP decoder exhausted the data for the first ZIP file, we move
	 * to the second ZIP file, and so on.
	 *
	 * This way we can maintain a predictable $context variable that carries upstream
	 * metadata and exposes methods like skip_file().
	 */
	protected function generate_next_chunk(): bool {
		if ( $this->last_stream->is_eof() ) {
			$this->state->finish();
			return false;
		}

		while ( true ) {
			$bytes = $this->state->consume_input_bytes();
			if ( null === $bytes || false === $bytes ) {
				break;
			}
			$this->first_stream->append_bytes(
				$bytes
			);
		}

		if ( $this->is_eof() ) {
			$this->first_stream->state->append_eof();
		}

		if ( empty( $this->execution_stack ) ) {
			array_push( $this->execution_stack, $this->first_stream );
		}

		while ( count( $this->execution_stack ) ) {
			// Unpeel the context stack until we find a stream that
			// produces output.
			$stream = $this->pop_stream();
			if ( $stream->is_eof() ) {
				continue;
			}

			if ( true !== $this->stream_next( $stream ) ) {
				continue;
			}

			// We've got output from the stream, yay! Let's
			// propagate it downstream.
			$this->push_stream( $stream );

			$prev_stream = $stream;
			for ( $i = count( $this->execution_stack ); $i < count( $this->streams_names ); $i++ ) {
				$next_stream = $this->streams[ $this->streams_names[ $i ] ];
				if ( $prev_stream->is_eof() ) {
					$next_stream->append_eof();
				}

				$next_stream->append_bytes(
					$prev_stream->state->output_bytes,
					$this->chunk_context
				);
				if ( true !== $this->stream_next( $next_stream ) ) {
					return false;
				}
				$this->push_stream( $next_stream );
				$prev_stream = $next_stream;
			}

			// When the last process in the chain produces output,
			// we write it to the output pipe and bale.
			if ( $this->last_stream->is_eof() ) {
				$this->state->finish();
				break;
			}
			$this->state->file_id      = $this->last_stream->state->file_id;
			$this->state->output_bytes = $this->last_stream->state->output_bytes;
			return true;
		}

		// We produced no output and the upstream pipe is EOF.
		// We're done.
		if ( $this->first_stream->is_eof() ) {
			$this->finish();
		}

		return false;
	}

	protected function finish() {
		$this->state->finish();
		foreach ( $this->streams as $stream ) {
			$stream->state->finish();
		}
	}

	private function pop_stream(): WP_Byte_Stream {
		$name = $this->streams_names[ count( $this->execution_stack ) - 1 ];
		unset( $this->chunk_context[ $name ] );
		return array_pop( $this->execution_stack );
	}

	private function push_stream( WP_Byte_Stream $stream ) {
		array_push( $this->execution_stack, $stream );
		$name                         = $this->streams_names[ count( $this->execution_stack ) - 1 ];
		$this->chunk_context[ $name ] = $stream;
	}

	private function stream_next( WP_Byte_Stream $stream ) {
		$produced_output = $stream->next_bytes();
		if ( $stream->state->last_error ) {
			$name                    = array_search( $stream, $this->streams, true );
			$this->state->last_error = "Process $name has crashed (" . $stream->state->last_error . ')';
		}
		return $produced_output;
	}

	// Iterator methods. These don't make much sense on a regular
	// process class because they cannot pull more input chunks from
	// the top of the stream like ProcessChain can.

	public function current(): mixed {
		return $this;
	}

	public function key(): mixed {
		return $this->get_chunk_type();
	}

	public function rewind(): void {
		$this->next();
	}

	private $should_stop_on_errors = false;
	public function stop_on_errors( $should_stop_on_errors ) {
		$this->should_stop_on_errors = $should_stop_on_errors;
	}

	public function next(): void {
		while ( ! $this->next_bytes() ) {
			if ( $this->should_stop_on_errors && $this->state->last_error ) {
				break;
			}
			if ( $this->is_eof() ) {
				break;
			}
			usleep( 10000 );
		}
	}

	public function valid(): bool {
		return ! $this->is_eof() || ( $this->should_stop_on_errors && $this->state->last_error );
	}


	// ArrayAccess on ProcessChain exposes specific
	// sub-processes by their names.
	public function offsetExists( $offset ): bool {
		return isset( $this->chunk_context[ $offset ] );
	}

	public function offsetGet( $offset ): mixed {
		return $this->chunk_context[ $offset ] ?? null;
	}

	public function offsetSet( $offset, $value ): void {
		// No op
	}

	public function offsetUnset( $offset ): void {
		// No op
	}
}
