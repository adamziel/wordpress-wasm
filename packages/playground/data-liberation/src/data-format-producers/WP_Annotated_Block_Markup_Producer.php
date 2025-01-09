<?php

/**
 * Turns Block Markup + Metadata into a metadata-annotated Block Markup.
 *
 * Example:
 *
 * The following block markup:
 *
 * <!-- wp:paragraph -->
 * <p>Hello <b>world</b>!</p>
 * <!-- /wp:paragraph -->
 *
 * And metadata:
 *
 * array(
 *     'post_title' => array( 'My first post' ),
 * )
 *
 * Becomes:
 *
 * <meta name="post_title" content="My first post">
 * <!-- wp:paragraph -->
 * <p>Hello <b>world</b>!</p>
 * <!-- /wp:paragraph -->
 */
class WP_Annotated_Block_Markup_Producer {

	/**
	 * @var WP_Blocks_With_Metadata
	 */
	private $blocks_with_meta;

	/**
	 * @var string
	 */
	private $result;

	public function __construct( WP_Blocks_With_Metadata $blocks_with_meta ) {
		$this->blocks_with_meta = $blocks_with_meta;
	}

	public function produce() {
		if ( null === $this->result ) {
			$this->result = '';
			foreach ( $this->blocks_with_meta->get_all_metadata() as $key => $value ) {
				$p = new WP_HTML_Tag_Processor( '<meta>' );
				$p->next_tag();
				$p->set_attribute( 'name', $key );
				if ( is_array( $value ) || is_object( $value ) ) {
					$value = json_encode( $value );
				}
				$p->set_attribute( 'content', $value );
				$this->result .= $p->get_updated_html() . "\n";
			}
			$this->result .= $this->blocks_with_meta->get_block_markup();
		}
		return $this->result;
	}
}
