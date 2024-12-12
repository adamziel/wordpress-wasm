<?php

class WP_Entity_Iterator_Chain implements Iterator {
	private $assets_attempts_iterator = null;
	private $entities_iterator        = null;

	public function set_assets_attempts_iterator( Iterator $iterator ) {
		$this->assets_attempts_iterator = $iterator;
	}

	public function set_entities_iterator( Iterator $iterator ) {
		$this->entities_iterator = $iterator;
	}

	#[\ReturnTypeWillChange]
	public function current() {
		$iterator = $this->get_iterator();
		return $iterator ? $iterator->current() : null;
	}

	#[\ReturnTypeWillChange]
	public function key() {
		$iterator = $this->get_iterator();
		return $iterator ? $iterator->key() : null;
	}

	public function next(): void {
		$iterator = $this->get_iterator();
		if ( ! $iterator ) {
			return;
		}

		$iterator->next();
	}

	public function rewind(): void {
		$iterator = $this->get_iterator();
		if ( ! $iterator ) {
			return;
		}
		$iterator->rewind();
	}

	public function valid(): bool {
		$iterator = $this->get_iterator();
		return $iterator && $iterator->valid();
	}

	private function get_iterator(): ?Iterator {
		if ( $this->assets_attempts_iterator && $this->assets_attempts_iterator->valid() ) {
			return $this->assets_attempts_iterator;
		} elseif ( $this->entities_iterator && $this->entities_iterator->valid() ) {
			return $this->entities_iterator;
		}
		return null;
	}

	#[\ReturnTypeWillChange]
	public function get_reentrancy_cursor() {
		if ( ! $this->entities_iterator ) {
			return null;
		}
		if ( ! method_exists( $this->entities_iterator, 'get_reentrancy_cursor' ) ) {
			return null;
		}
		return $this->entities_iterator->get_reentrancy_cursor();
	}
}
