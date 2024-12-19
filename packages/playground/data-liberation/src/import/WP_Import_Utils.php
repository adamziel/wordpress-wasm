<?php

/**
 * A collection of utility functions for importing data into WordPress.
 *
 * This is a stopgap solution until we have an expressive enough API
 * to co-locate these tactical functions with their domain-specific code.
 */
class WP_Import_Utils {

	/**
	 * Generates a block opener comment with given attributes.
	 *
	 * @param string $block_name The name of the block.
	 * @param array $attrs The attributes of the block.
	 * @return string The block opener.
	 */
	public static function block_opener( $block_name, $attrs = array() ) {
		$template = "<!-- wp:{$block_name} -->";
		$processor = new WP_Block_Markup_Processor( $template );
		$processor->next_token();
		$processor->set_block_attributes( $attrs );
		return $processor->get_updated_html();
	}

	/**
	 * Generates a block closer comment.
	 *
	 * @param string $block_name The name of the block.
	 * @return string The block closer.
	 */
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
			$block_markup .= self::block_opener( $block->block_name, $block->attrs );
			$block_markup .= $block->attrs['content'] ?? '';
			$block_markup .= self::convert_blocks_to_markup( $block->inner_blocks );
			$block_markup .= self::block_closer( $block->block_name );
		}

		return $block_markup;
	}

}
