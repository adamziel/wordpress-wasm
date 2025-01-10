<?php

/**
 * A processor class capable of reading and rewriting block markup.
 *
 * This class provides functionality to parse, traverse and modify WordPress block markup.
 * It extends WP_HTML_Tag_Processor to add block-specific capabilities like:
 * - Parsing block comments into name and attributes
 * - Tracking block nesting depth
 * - Modifying block attributes
 * - Validating block structure
 *
 * Contrary to WP_HTML_Tag_Processor, this class does not support streaming.
 * It assumes block markup blobs are small enough to fit into memory, otherwise
 * WordPress won't be able to render them anyway.
 *
 * @see WP_HTML_Tag_Processor
 */
class WP_Block_Markup_Processor extends WP_HTML_Tag_Processor {

	/**
	 * The name of the current block (e.g. 'core/paragraph')
	 *
	 * @var string|null
	 */
	private $block_name;

	/**
	 * The attributes of the current block as an associative array
	 *
	 * @var array<string, mixed>|null
	 */
	protected $block_attributes;

	/**
	 * Whether the current block's attributes have been modified and need to be serialized
	 *
	 * @var bool
	 */
	private $block_attributes_updated;

	/**
	 * Whether the current block token is a closing tag (e.g. <!-- /wp:paragraph -->)
	 *
	 * @var bool
	 */
	private $block_closer;

	/**
	 * Whether the current block is self-closing (e.g. <!-- wp:spacer /-->)
	 *
	 * @var bool
	 */
	private $self_closing_flag;

	/**
	 * Stack tracking the names of currently open blocks for validation
	 *
	 * @var array<string>
	 */
	private $stack_of_open_blocks = array();

	/**
	 * The most recent error encountered while parsing blocks
	 *
	 * @var string|null
	 */
	private $last_block_error;

	/**
	 * Iterator for traversing nested block attributes
	 * @var \RecursiveIteratorIterator
	 */
	private $block_attributes_iterator;

	/**
	 * Gets the type of the current token, adding a special '#block-comment' type
	 * for WordPress block delimiters.
	 *
	 * @return string|null The token type or null if no token
	 */
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
	 * Gets the most recent error encountered while parsing blocks
	 *
	 * @return string|null The error message or null if no error
	 */
	public function get_last_error(): ?string {
		return $this->last_block_error;
	}

	/**
	 * Advances past the block closer of the currently matched block and returns
	 * the HTML content found between the block's opener and closer.
	 *
	 * @return string|false The inner HTML content of the block or false if not a block opener.
	 */
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

	/**
	 * Gets the depth of the currently matched block on the block stack. It only
	 * considers the parent blocks and not HTML elements.
	 *
	 * For example, the paragraph block in the following markup has a depth of 1:
	 *
	 * <!-- wp:core/blockquote -->
	 *   <blockquote>
	 *     <!-- wp:core/paragraph -->
	 *       <p>Hello, there</p>
	 *     <!-- /wp:core/paragraph -->
	 *   </blockquote>
	 * <!-- /wp:core/blockquote -->
	 *
	 * @return int The number of ancestor blocks
	 */
	public function get_block_depth() {
		return count( $this->stack_of_open_blocks );
	}

	/**
	 * Gets the names of all currently open blocks from outermost to innermost
	 *
	 * @return array List of block names in nesting order
	 */
	public function get_block_breadcrumbs() {
		return $this->stack_of_open_blocks;
	}

	/**
	 * Returns the name of the block if the current token is a block comment.
	 *
	 * @return string|false The block name (e.g. 'core/paragraph') or false if not at a block
	 */
	public function get_block_name() {
		if ( null === $this->block_name ) {
			return false;
		}

		return $this->block_name;
	}

	/**
	 * Gets all attributes of the current block
	 *
	 * @return array|false The block attributes or false if not at a block
	 */
	public function get_block_attributes() {
		if ( null === $this->block_attributes ) {
			return false;
		}

		return $this->block_attributes;
	}

	/**
	 * Gets a specific attribute value from the current block
	 *
	 * @param string $attribute_name The name of the attribute to get
	 * @return mixed|false The attribute value or false if not found
	 */
	public function get_block_attribute( $attribute_name ) {
		if ( null === $this->block_attributes ) {
			return false;
		}

		return $this->block_attributes[ $attribute_name ] ?? false;
	}

	/**
	 * Overwrites all the block attributes of the currently matched block
	 * opener.
	 *
	 * @param array $attributes The new attributes to set
	 * @return bool Whether the attributes were successfully set
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

	/**
	 * Checks if the currently matched token is a block closer,
	 * e.g. <!-- /wp:paragraph -->.
	 *
	 * @return bool True if at a block closer.
	 */
	public function is_block_closer() {
		return $this->block_name !== null && $this->block_closer === true;
	}

	/**
	 * Checks if the currently matched token is a self-closing block,
	 * e.g. <!-- wp:spacer /-->.
	 *
	 * @return bool True if at a self-closing block.
	 */
	public function is_self_closing_block() {
		return $this->block_name !== null && $this->self_closing_flag === true;
	}

	/**
	 * Advances to the next token in the HTML stream. Matches:
	 * - The regular HTML tokens
	 * - WordPress block openers
	 * - WordPress block closers
	 * - WordPress self-closing blocks
	 *
	 * @return bool Whether a token was parsed.
	 */
	public function next_token(): bool {
		$this->get_updated_html();

		$this->block_name                = null;
		$this->block_attributes          = null;
		$this->block_attributes_iterator = null;
		$this->block_closer              = false;
		$this->self_closing_flag         = false;
		$this->block_attributes_updated  = false;

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
			$rest = ltrim( substr( $text, $at ) );
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
	}

	/**
	 * @inheritDoc
	 */
	public function get_updated_html(): string {
		$this->block_attribute_updates_to_modifiable_text_updates();
		return parent::get_updated_html();
	}

	/**
	 * Converts block attribute updates into lexical updates.
	 *
	 * @return bool Whether any lexical updates were created
	 */
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

	/**
	 * Advances to the next block attribute when a block is matched.
	 *
	 * @return bool Whether we successfully advanced to the next attribute.
	 */
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

	/**
	 * Gets the key of the currently matched block attribute.
	 *
	 * @return string|false The attribute key or false if no attribute was matched
	 */
	public function get_block_attribute_key() {
		if ( null === $this->block_attributes_iterator || false === $this->block_attributes_iterator->valid() ) {
			return false;
		}

		return $this->block_attributes_iterator->key();
	}

	/**
	 * Gets the value of the currently matched block attribute.
	 *
	 * @return mixed|false The attribute value or false if no attribute was matched
	 */
	public function get_block_attribute_value() {
		if ( null === $this->block_attributes_iterator || false === $this->block_attributes_iterator->valid() ) {
			return false;
		}

		return $this->block_attributes_iterator->current();
	}

	/**
	 * Sets the value of the currently matched block attribute.
	 *
	 * @param mixed $new_value The new value to set
	 * @return bool Whether the value was successfully set
	 */
	public function set_block_attribute_value( $new_value ) {
		if ( null === $this->block_attributes_iterator || false === $this->block_attributes_iterator->valid() ) {
			return false;
		}

		$this->block_attributes_iterator
			->getSubIterator( $this->block_attributes_iterator->getDepth() )
			->offsetSet(
				$this->get_block_attribute_key(),
				$new_value
			);
		$this->block_attributes_updated = true;

		return true;
	}
}
