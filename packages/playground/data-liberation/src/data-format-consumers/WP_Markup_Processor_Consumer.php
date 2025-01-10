<?php

/**
 * Creates block markup from a WP_HTML_Processor or WP_XML_Processor instance.
 *
 * It only considers the markup and won't consider any visual
 * changes introduced via CSS or JavaScript.
 *
 * Example:
 *
 * <meta name="post_title" content="My first post">
 * <p>Hello <b>world</b>!</p>
 *
 * Becomes:
 *
 * <!-- wp:paragraph -->
 * <p>Hello <b>world</b>!</p>
 * <!-- /wp:paragraph -->
 *
 * With the following metadata:
 *
 * array(
 *     'post_title' => array( 'My first post' ),
 * )
 */
class WP_Markup_Processor_Consumer implements WP_Data_Format_Consumer {
	private $markup_processor;
	private $ignore_text            = false;
	private $in_ephemeral_paragraph = false;
	private $block_stack            = array();

	private $parsed;
	private $block_markup = '';
	private $metadata     = array();

	public function __construct( $markup_processor ) {
		$this->markup_processor = $markup_processor;
	}

	public function consume() {
		if ( ! $this->parsed ) {
			while ( $this->markup_processor->next_token() ) {
				switch ( $this->markup_processor->get_token_type() ) {
					case '#text':
						if ( $this->ignore_text ) {
							break;
						}
						$this->append_rich_text( htmlspecialchars( $this->markup_processor->get_modifiable_text() ) );
						break;
					case '#tag':
						$this->handle_tag();
						break;
				}
			}

			if ( $this->markup_processor->get_last_error() ) {
				$exception = $this->markup_processor->get_unsupported_exception();
				if ( $exception ) {
					throw $exception;
				}
				throw new Data_Liberation_Exception( $this->markup_processor->get_last_error() );
			}

			$this->close_ephemeral_paragraph();
			$this->parsed = new WP_Blocks_With_Metadata( $this->block_markup, $this->metadata );
		}

		return $this->parsed;
	}

	private function handle_tag() {
		$html          = $this->markup_processor;
		$tag           = strtoupper( $html->get_tag() );
		$tag_lowercase = strtolower( $tag );

		$is_void_tag = ! $html->expects_closer() && ! $html->is_tag_closer();
		if ( $is_void_tag ) {
			switch ( $tag ) {
				case 'META':
					$key   = $html->get_attribute( 'name' );
					$value = $html->get_attribute( 'content' );
					if ( ! array_key_exists( $key, $this->metadata ) ) {
						$this->metadata[ $key ] = array();
					}
					$this->metadata[ $key ][] = $value;
					break;
				case 'IMG':
					$template = new \WP_HTML_Tag_Processor( '<img>' );
					$template->next_tag();
					foreach ( array( 'alt', 'title', 'src' ) as $attr ) {
						if ( $html->get_attribute( $attr ) ) {
							$template->set_attribute( $attr, $html->get_attribute( $attr ) );
						}
					}
					/**
					 *
					 */
					$this->append_rich_text( $template->get_updated_html() );
					break;
				default:
					// @TODO: What to do with other void tags, e.g. <input>?
					//        Just insert an HTML block or what?
					break;
			}
		} elseif ( ! $html->is_tag_closer() ) {
			switch ( $tag ) {
				// Block elements
				case 'SCRIPT':
					$this->ignore_text = true;
					break;
				case 'UL':
				case 'OL':
					$this->push_block( 'list', array( 'ordered' => $tag === 'ol' ) );
					$this->block_markup .= '<ul class="wp-block-list">';
					break;
				case 'LI':
					$this->push_block( 'list-item' );
					$this->block_markup .= '<' . $tag_lowercase . '>';
					break;
				case 'TABLE':
					$this->push_block( 'table' );
					$this->block_markup .= '<figure class="wp-block-table">';
					$this->block_markup .= '<table class="has-fixed-layout">';
					break;
				case 'THEAD':
				case 'TBODY':
				case 'TFOOT':
				case 'TR':
				case 'TD':
				case 'TH':
					$this->block_markup .= '<' . $tag_lowercase . '>';
					break;
				case 'BLOCKQUOTE':
					$this->push_block( 'quote' );
					$this->block_markup .= '<' . $tag_lowercase . '>';
					break;
				case 'PRE':
					$this->push_block( 'code' );
					$this->block_markup .= '<' . $tag_lowercase . '  class="wp-block-code">';
					break;
				case 'CODE':
					// Guess whether this is:
					// * An inline <code> element? Let's convert it into a formatting element.
					// * A block <code> element? Let's convert it into a block.
					if ( $this->is_at_inline_code_element() ) {
						$this->append_rich_text( '<' . $tag_lowercase . '>' );
					} else {
						$this->push_block( 'code' );
						$this->block_markup .= '<' . $tag_lowercase . '  class="wp-block-code">';
					}
					break;
				case 'HR':
					$this->push_block( 'separator' );
					break;
				case 'P':
					$this->push_block( 'paragraph' );
					$this->block_markup .= '<p>';
					break;
				case 'H1':
				case 'H2':
				case 'H3':
				case 'H4':
				case 'H5':
				case 'H6':
					$this->push_block(
						'heading',
						array(
							'level' => (int) $tag[1] ? (int) $tag[1] : 1,
						)
					);
					$this->block_markup .= '<h' . $tag[1] . '>';
					break;

				// Inline elements
				case 'A':
					$template = new \WP_HTML_Tag_Processor( '<a>' );
					$template->next_tag();
					if ( $html->get_attribute( 'href' ) ) {
						$template->set_attribute( 'href', $html->get_attribute( 'href' ) );
					}
					/**
					 *
					 */
					$this->append_rich_text( $template->get_updated_html() );
					break;

				// Formats – just pass through (minus the HTML attributes)
				default:
					if ( $this->should_preserve_tag_in_rich_text( $tag ) ) {
						$this->append_rich_text( '<' . $tag_lowercase . '>' );
					} else {
						// @TODO: What to do with other tags? Just insert an HTML block or what?
					}
					break;
			}
		} elseif ( $html->is_tag_closer() ) {
			switch ( $tag ) {
				case 'SCRIPT':
					$this->ignore_text = false;
					break;
				// Maintain the same lists as in the tag opener code branch above,
				// otherwise we won't pop the correct block.

				// Block elements
				// Post-process the collected table data.
				case 'TABLE':
					$this->block_markup .= '</table>';
					$this->block_markup .= '</figure>';
					$this->pop_block();
					break;

				case 'THEAD':
				case 'TBODY':
				case 'TFOOT':
				case 'TR':
				case 'TD':
				case 'TH':
					$this->block_markup .= '</' . $tag_lowercase . '>';
					break;

				case 'CODE':
					$this->block_markup .= '</' . $tag_lowercase . '>';
					if ( ! $this->is_at_inline_code_element() ) {
						$this->pop_block();
					}
					break;

				// Block elements
				case 'UL':
				case 'OL':
					$this->block_markup .= '</ul>';
					$this->pop_block();
					break;

				case 'LI':
				case 'BLOCKQUOTE':
				case 'PRE':
				case 'HR':
				case 'P':
				case 'H1':
				case 'H2':
				case 'H3':
				case 'H4':
				case 'H5':
				case 'H6':
					$this->block_markup .= '</' . $tag_lowercase . '>';
					$this->pop_block();
					break;

				case 'A':
					$this->block_markup .= '</a>';
					break;

				// Formats
				default:
					if ( $this->should_preserve_tag_in_rich_text( $tag ) ) {
						$this->block_markup .= '</' . $tag_lowercase . '>';
					}
					break;
			}
		}
	}

	/**
	 * Checks whether the given tag is an inline formatting element
	 * that we want to preserve when parsing rich text. For example,
	 * <b> tags are meaningful from the rich text perspective, but
	 * <div> tags are not.
	 *
	 * @param string $tag The tag to check.
	 * @return bool Whether the tag should be preserved in rich text.
	 */
	private function should_preserve_tag_in_rich_text( $tag ) {
		return in_array(
			$tag,
			array(
				'B',
				'STRONG',
				'I',
				'U',
				'S',
				'SMALL',
				'SUP',
				'SUB',
				'MARK',
				'EM',
				'CITE',
				'DFN',
				'CODE',
				'KBD',
				'SAMP',
				'VAR',
			),
			true
		);
	}

	private function is_at_inline_code_element() {
		$breadcrumbs = $this->markup_processor->get_breadcrumbs();
		foreach ( $breadcrumbs as $tag ) {
			switch ( $tag ) {
				case 'A':
				case 'P':
				case 'LI':
				case 'TABLE':
				case 'H1':
				case 'H2':
				case 'H3':
				case 'H4':
				case 'H5':
				case 'H6':
					return true;
			}
		}
		return false;
	}

	/**
	 * Appends a snippet of HTML to the block markup.
	 * Ensures given $html is a part of a block. If no block is
	 * currently open, it appends a new paragraph block.
	 *
	 * @param string $html The HTML snippet to append.
	 */
	private function append_rich_text( $html ) {
		$html = trim( $html );
		if ( empty( $html ) ) {
			return;
		}
		// Make sure two subsequent append_text() calls don't merge the text.
		$html .= ' ';
		$this->ensure_open_block();
		$this->block_markup .= $html;
	}

	/**
	 * Pushes a new block onto the stack of open blocks and appends the block
	 * opener to the block markup.
	 *
	 * @param string $name The name of the block to push.
	 * @param array $attributes The attributes of the block to push.
	 */
	private function push_block( $name, $attributes = array() ) {
		$this->close_ephemeral_paragraph();
		$block = new \WP_Block_Object( $name, $attributes );
		array_push( $this->block_stack, $block );
		$this->block_markup .= WP_Import_Utils::block_opener( $block->block_name, $block->attrs ) . "\n";
	}

	/**
	 * Pops the last block from the stack of open blocks and appends the block
	 * closer to the block markup.
	 *
	 * @return \WP_Block_Object The last block that was popped.
	 */
	private function pop_block() {
		if ( ! empty( $this->block_stack ) ) {
			$popped              = array_pop( $this->block_stack );
			$this->block_markup .= WP_Import_Utils::block_closer( $popped->block_name ) . "\n";
			return $popped;
		}
	}

	/**
	 * Ensures that a block is open. If no block is currently open, it appends
	 * a new, ephemeral paragraph block that will be automatically closed
	 * when the next block opens OR when the HTML ends.
	 */
	private function ensure_open_block() {
		if ( empty( $this->block_stack ) && ! $this->in_ephemeral_paragraph ) {
			$this->block_markup          .= WP_Import_Utils::block_opener( 'paragraph' ) . "\n";
			$this->block_markup          .= '<p>';
			$this->in_ephemeral_paragraph = true;
		}
	}

	/**
	 * Closes the ephemeral paragraph if it is currently open.
	 */
	private function close_ephemeral_paragraph() {
		if ( $this->in_ephemeral_paragraph ) {
			$this->block_markup          .= '</p>';
			$this->block_markup          .= WP_Import_Utils::block_closer( 'paragraph' );
			$this->in_ephemeral_paragraph = false;
		}
	}
}
