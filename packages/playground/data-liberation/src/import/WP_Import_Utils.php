<?php

class WP_Import_Utils {

	public static function block_opener( $block_name, $attrs = array() ) {
		$encoded_attrs = json_encode( $attrs );
		if ( $attrs && $encoded_attrs !== '{}' ) {
			$encoded_attrs .= ' ';
		} else {
			$encoded_attrs = '';
		}
		return "<!-- wp:{$block_name} {$encoded_attrs}-->";
	}

	public static function block_closer( $block_name ) {
		return "<!-- /wp:{$block_name} -->";
	}

	/**
	 * Convert an array of WP_Block_Object objects to HTML markup.
	 *
	 * @param array $blocks The blocks to convert to markup.
	 * @return string The HTML markup.
	 */
	public static function convert_blocks_to_markup( $blocks ) {
		$block_markup = '';

		foreach ( $blocks as $block ) {
			// Allow mixing of inner blocks and content strings.
			if ( is_string( $block ) ) {
				$block_markup .= $block;
				continue;
			}
			// Start of block comment
			$attrs_without_content = $block->attrs;
			$content = $block->attrs['content'] ?? '';
			unset( $attrs_without_content['content'] );
			$block_markup .= self::block_opener( $block->block_name, $attrs_without_content );
			$block_markup .= $content;
			$block_markup .= self::convert_blocks_to_markup( $block->inner_blocks );
			$block_markup .= self::block_closer( $block->block_name );
		}

		return $block_markup;
	}

	public static function slug_to_title( $filename ) {
		$name = pathinfo( $filename, PATHINFO_FILENAME );
		$name = preg_replace( '/^\d+/', '', $name );
		$name = str_replace(
			array( '-', '_' ),
			' ',
			$name
		);
		$name = ucwords( $name );
		return $name;
	}

	public static function remove_first_h1_block_from_block_markup( $html ) {
		$p = WP_Import_HTML_Processor::create_fragment( $html );
		if ( false === $p->next_tag() ) {
			return false;
		}
		if ( $p->get_tag() !== 'H1' ) {
			return false;
		}
		$depth = $p->get_current_depth();
		$title = '';
		do {
			if ( false === $p->next_token() ) {
				break;
			}
			if ( $p->get_token_type() === '#text' ) {
				$title .= $p->get_modifiable_text() . ' ';
			}
		} while ( $p->get_current_depth() > $depth );

		if ( ! $title ) {
			return false;
		}

		// Move past the closing comment
		$p->next_token();
		if ( $p->get_token_type() === '#text' ) {
			$p->next_token();
		}
		if ( $p->get_token_type() !== '#comment' ) {
			return false;
		}

		return array(
			'h1_content' => trim( $title ),
			'remaining_html' => substr(
				$html,
				$p->get_string_index_after_current_token()
			),
		);
	}
}
