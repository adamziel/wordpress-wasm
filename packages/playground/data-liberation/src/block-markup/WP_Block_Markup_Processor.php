<?php

/**
 * Detects and rewrites URLs in block markup.
 *
 * ## Design choices
 *
 * ### No streaming
 *
 * This class loads the entire block markup into memory without streaming.
 * If the post cannot fit into memory, WordPress won't be able to render it
 * anyway.
 */
class WP_Block_Markup_Processor extends WP_HTML_Tag_Processor {

	private $block_name;
	protected $block_attributes;
	private $block_attributes_updated;
	private $block_closer;

	/**
	 * @var \RecursiveIteratorIterator
	 */
	private $block_attributes_iterator;

	public function get_token_type(): ?string {
		switch ( $this->parser_state ) {
			case self::STATE_COMMENT:
				if ( null !== $this->block_name ) {
					return '#block-comment';
				}

				return '#comment';

			default:
				return parent::get_token_type();
		}
	}

	/**
	 * Returns the name of the block if the current token is a block comment.
	 *
	 * @return string|false
	 */
	public function get_block_name() {
		if ( null === $this->block_name ) {
			return false;
		}

		return $this->block_name;
	}

	public function get_block_attributes() {
		if ( null === $this->block_attributes ) {
			return false;
		}

		return $this->block_attributes;
	}

	/**
	 * Overwrites all the block attributes of the currently matched block
	 * opener.
	 *
	 * @param array $attributes The attributes to set.
	 * @return bool Whether the attributes were set.
	 */
	public function set_block_attributes( $attributes ) {
		if ( '#block-comment' !== $this->get_token_type() ) {
			return false;
		}
		if ( $this->is_block_closer() ) {
			return false;
		}
		$this->block_attributes         = $attributes;
		$this->block_attributes_updated = true;
		return true;
	}

	public function is_block_closer() {
		return $this->block_name !== null && $this->block_closer === true;
	}

	public function next_token(): bool {
		$this->get_updated_html();

		$this->block_name               = null;
		$this->block_attributes         = null;
		$this->block_closer             = false;
		$this->block_attributes_updated = false;

		if ( parent::next_token() === false ) {
			return false;
		}

		if ( parent::get_token_type() !== '#comment' ) {
			return true;
		}

		$text = parent::get_modifiable_text();
		/**
		 * Try to parse as a block. The block parser won't cut it because
		 * while it can parse blocks, it has no semantics for rewriting the
		 * block markup. Let's do our best here:
		 */
		$at = strspn( $text, ' \t\f\r\n' ); // Whitespace.

		if ( $at >= strlen( $text ) ) {
			// This is an empty comment. Not a block.
			return true;
		}

		// Blocks closers start with the solidus character (`/`).
		if ( '/' === $text[ $at ] ) {
			$this->block_closer = true;
			++$at;
		}

		// Blocks start with wp.
		if ( ! (
			$at + 3 < strlen( $text ) &&
			$text[ $at ] === 'w' &&
			$text[ $at + 1 ] === 'p' &&
			$text[ $at + 2 ] === ':'
		) ) {
			return true;
		}

		$name_starts_at = $at;

		// Skip wp.
		$at += 3;

		// Parse the actual block name after wp.
		$name_length = strspn( $text, 'abcdefghijklmnopqrstuwxvyzABCDEFGHIJKLMNOPRQSTUWXVYZ0123456789_-', $at );
		if ( $name_length === 0 ) {
			// This wasn't a block after all, just a regular comment.
			return true;
		}
		$name = substr( $text, $name_starts_at, $name_length + 3 );
		$at  += $name_length;

		// Skip the whitespace that follows the block name.
		$at += strspn( $text, ' \t\f\r\n', $at );
		if ( $at >= strlen( $text ) ) {
			// It's a block without attributes.
			$this->block_name = $name;

			return true;
		}

		// It seems we may have block attributes here.

		// Block closers cannot have attributes.
		if ( $this->block_closer ) {
			return true;
		}

		// Let's try to parse them as JSON.
		$json_maybe = substr( $text, $at );
		$attributes = json_decode( $json_maybe, true );
		if ( null === $attributes || ! is_array( $attributes ) ) {
			// This comment looked like a block comment, but the attributes didn't
			// parse as a JSON array. This means it wasn't a block after all.
			return true;
		}

		// We have a block name and a valid attributes array. We may not find a block
		// closer, but let's assume is a block and process it as such.
		// @TODO: Confirm that WordPress block parser would have parsed this as a block.
		$this->block_name       = $name;
		$this->block_attributes = $attributes;

		return true;
	}

	public function get_updated_html(): string {
		$this->block_attribute_updates_to_modifiable_text_updates();
		return parent::get_updated_html();
	}

	private function block_attribute_updates_to_modifiable_text_updates() {
		// Apply block attribute updates, if any.
		if ( ! $this->block_attributes_updated ) {
			return false;
		}
		$encoded_attributes = json_encode(
			$this->block_attributes_iterator
				? $this->block_attributes_iterator->getSubIterator( 0 )->getArrayCopy()
				: $this->block_attributes,
			JSON_HEX_TAG | // Convert < and > to \u003C and \u003E
			JSON_HEX_AMP   // Convert & to \u0026
		);
		if ( $encoded_attributes === '[]' ) {
			$encoded_attributes = '';
		} else {
			$encoded_attributes .= ' ';
		}
		$this->set_modifiable_text(
			' ' .
			$this->block_name .
			' ' .
			$encoded_attributes
		);

		return true;
	}

	public function next_block_attribute() {
		if ( '#block-comment' !== $this->get_token_type() ) {
			return false;
		}

		if ( null === $this->block_attributes_iterator ) {
			$block_attributes = $this->get_block_attributes();
			if ( ! is_array( $block_attributes ) ) {
				return false;
			}
			// Re-entrant iteration over the block attributes.
			$this->block_attributes_iterator = new \RecursiveIteratorIterator(
				new \RecursiveArrayIterator( $block_attributes ),
				\RecursiveIteratorIterator::SELF_FIRST
			);
		}

		while ( true ) {
			$this->block_attributes_iterator->next();
			if ( ! $this->block_attributes_iterator->valid() ) {
				break;
			}
			return true;
		}

		return false;
	}

	public function get_block_attribute_key() {
		if ( null === $this->block_attributes_iterator || false === $this->block_attributes_iterator->valid() ) {
			return false;
		}

		return $this->block_attributes_iterator->key();
	}

	public function get_block_attribute_value() {
		if ( null === $this->block_attributes_iterator || false === $this->block_attributes_iterator->valid() ) {
			return false;
		}

		return $this->block_attributes_iterator->current();
	}

	public function set_block_attribute_value( $new_value ) {
		if ( null === $this->block_attributes_iterator || false === $this->block_attributes_iterator->valid() ) {
			return false;
		}

		$this->block_attributes_iterator->getSubIterator(
			$this->block_attributes_iterator->getDepth()
		)->offsetSet(
			$this->get_block_attribute_key(),
			$new_value
		);
		$this->block_attributes_updated = true;

		return true;
	}
}
