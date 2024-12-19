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
class WP_Block_Markup_Processor extends WP_HTML_Processor {

	private $block_name;
	protected $block_attributes;
	private $block_attributes_updated;
	private $block_closer;
	private $self_closing_flag;
	private $stack_of_open_blocks = array();
	private $last_block_error;

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

	public function get_last_error(): ?string {
		return $this->last_block_error ?? parent::get_last_error();
	}

	public function skip_and_get_block_inner_html() {
		if ( '#block-comment' !== $this->get_token_type() ) {
			return false;
		}

		if ( $this->is_block_closer() ) {
			return false;
		}

		if ( false === WP_HTML_Tag_Processor::set_bookmark( 'block-start' ) ) {
			return false;
		}

		$starting_block_depth = $this->get_block_depth();
		while ( $this->next_token() ) {
			if (
				$this->get_token_type() === '#block-comment' &&
				$this->is_block_closer() &&
				$this->get_block_depth() === $starting_block_depth - 1
			) {
				break;
			}
		}

		if ( false === WP_HTML_Tag_Processor::set_bookmark( 'block-end' ) ) {
			WP_HTML_Tag_Processor::release_bookmark( 'block-start' );
			return false;
		}

		$inner_html_start = $this->bookmarks['block-start']->start + $this->bookmarks['block-start']->length;
		$inner_html_end   = $this->bookmarks['block-end']->start - $inner_html_start;

		WP_HTML_Tag_Processor::release_bookmark( 'block-start' );
		WP_HTML_Tag_Processor::release_bookmark( 'block-end' );

		return substr(
			$this->html,
			$inner_html_start,
			$inner_html_end
		);
	}

	public function get_block_depth() {
		return count( $this->stack_of_open_blocks );
	}

	public function get_block_breadcrumbs() {
		return $this->stack_of_open_blocks;
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

	public function get_block_attribute( $attribute_name ) {
		if ( null === $this->block_attributes ) {
			return false;
		}

		return $this->block_attributes[ $attribute_name ] ?? false;
	}

	public function is_block_closer() {
		return $this->block_name !== null && $this->block_closer === true;
	}

	public function is_self_closing_block() {
		return $this->block_name !== null && $this->self_closing_flag === true;
	}

	private $in_next_token = false;
	public function next_token(): bool {
		// Prevent running next_token() logic twice when the parent method
		// makes recursive calls to itself.
		if ( $this->in_next_token ) {
			return parent::next_token();
		}
		$this->in_next_token = true;
		try {
			$this->get_updated_html();

			$this->block_name               = null;
			$this->block_attributes         = null;
			$this->block_closer             = false;
			$this->self_closing_flag        = false;
			$this->block_attributes_updated = false;

			while ( true ) {
				if ( parent::next_token() === false ) {
					return false;
				}

				if (
					$this->get_token_type() === '#tag' && (
						$this->get_tag() === 'HTML' ||
						$this->get_tag() === 'HEAD' ||
						$this->get_tag() === 'BODY'
					)
				) {
					continue;
				}

				break;
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

			// Assume no attributes by default.
			$attributes = array();

			// Skip the whitespace that follows the block name.
			$at += strspn( $text, ' \t\f\r\n', $at );
			if ( $at < strlen( $text ) ) {
				// It may be a self-closing block or a block with attributes.

				// However, block closers can be neither – let's short-circuit.
				if ( $this->block_closer ) {
					return true;
				}

				// The rest of the comment can only consist of block attributes
				// and an optional solidus character.
				$rest = trim( substr( $text, $at ) );
				$at   = strlen( $text );

				// Inspect our potential JSON for the self-closing solidus (`/`) character.
				$json_maybe = $rest;
				if ( substr( $json_maybe, -1 ) === '/' ) {
					// Self-closing block (<!-- wp:image /-->)
					$this->self_closing_flag = true;
					$json_maybe              = substr( $json_maybe, 0, -1 );
				}

				// Let's try to parse attributes as JSON.
				if ( strlen( $json_maybe ) > 0 ) {
					$attributes = json_decode( $json_maybe, true );
					if ( null === $attributes || ! is_array( $attributes ) ) {
						// This comment looked like a block comment, but the attributes didn't
						// parse as a JSON array. This means it wasn't a block after all.
						return true;
					}
				}
			}

			// We have a block name and a valid attributes array. We may not find a block
			// closer, but let's assume is a block and process it as such.
			// @TODO: Confirm that WordPress block parser would have parsed this as a block.
			$this->block_name       = $name;
			$this->block_attributes = $attributes;

			if ( $this->block_closer ) {
				$popped = array_pop( $this->stack_of_open_blocks );
				if ( $popped !== $name ) {
					$this->last_block_error = sprintf( 'Block closer %s does not match the last opened block %s.', $name, $popped );
					return false;
				}
			} elseif ( ! $this->self_closing_flag ) {
				array_push( $this->stack_of_open_blocks, $name );
			}

			return true;
		} finally {
			$this->in_next_token = false;
		}
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
		$this->set_modifiable_text(
			' ' .
			$this->block_name . ' ' .
			json_encode(
				$this->block_attributes_iterator
					? $this->block_attributes_iterator->getSubIterator( 0 )->getArrayCopy()
					: $this->block_attributes,
				JSON_HEX_TAG | // Convert < and > to \u003C and \u003E
				JSON_HEX_AMP   // Convert & to \u0026
			)
			. ' '
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
