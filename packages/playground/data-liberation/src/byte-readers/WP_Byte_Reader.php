<?php

interface WP_Byte_Reader {
	public function tell(): int;
	public function seek( int $offset ): bool;
	public function is_finished(): bool;
	public function next_bytes(): bool;
	public function get_bytes(): ?string;
	public function get_last_error(): ?string;
}
