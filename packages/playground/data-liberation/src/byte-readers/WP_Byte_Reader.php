<?php

interface WP_Byte_Reader {
	public function pause(): array|bool;
	public function resume( $paused_state ): bool;
	public function is_finished(): bool;
	public function next_bytes(): bool;
	public function get_bytes(): string|null;
	public function get_last_error(): string|null;
}
