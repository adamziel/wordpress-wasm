<?php

abstract class WP_Entity_Reader implements Iterator {

	abstract public function get_entity();
	abstract public function next_entity();

	/**
	 * Checks if processing is finished.
	 *
	 * @since WP_VERSION
	 *
	 * @return bool Whether processing is finished.
	 */
	abstract public function is_finished(): bool;

	/**
	 * Gets the last error that occurred.
	 *
	 * @since WP_VERSION
	 *
	 * @return string|null The error message, or null if no error occurred.
	 */
	abstract public function get_last_error(): ?string;

	/**
	 * Returns a cursor that can be used to restore the reader's state.
	 *
	 * @TODO: Define a general interface for entity readers.
	 *
	 * @return string
	 */
	public function get_reentrancy_cursor() {
		return '';
	}

	public function current(): object {
		if ( null === $this->get_entity() && ! $this->is_finished() && ! $this->get_last_error() ) {
			$this->next();
		}
		return $this->get_entity();
	}

	private $last_next_result = null;
	public function next(): void {
		// @TODO: Don't keep track of this. Just make sure the next_entity()
		//        call will make the is_finished() true.
		$this->last_next_result = $this->next_entity();
	}

	public function key(): string {
		return $this->get_reentrancy_cursor();
	}

	public function valid(): bool {
		return false !== $this->last_next_result && ! $this->is_finished() && ! $this->get_last_error();
	}

	public function rewind(): void {
		// Haven't started yet.
		if ( null === $this->last_next_result ) {
			return;
		}
		_doing_it_wrong(
			__METHOD__,
			'WP_WXR_Entity_Reader does not support rewinding.',
			null
		);
	}
}
