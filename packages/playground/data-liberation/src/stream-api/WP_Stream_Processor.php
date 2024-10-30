<?php

interface WP_Stream_Processor {
	public function append_bytes( string $bytes );
	public function is_finished(): bool;
	public function is_paused_at_incomplete_input(): bool;
	public function get_last_error(): ?string;
}
