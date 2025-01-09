<?php
/**
 * Converts a metadata-annotated block markup into block markup+metadata pair.
 *
 * Example:
 *
 * <meta name="post_title" content="My first post">
 * <!-- wp:paragraph {"className":"my-class"} -->
 * <p class="my-class">Hello world!</p>
 * <!-- /wp:paragraph -->
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
class WP_Annotated_Block_Markup_Consumer implements WP_Data_Format_Consumer {

	/**
	 * @var string
	 */
	private $original_html;

	/**
	 * @var WP_Consumed_Block_Markup
	 */
	private $result;

	public function __construct( $original_html ) {
		$this->original_html = $original_html;
	}

	public function consume() {
		if ( ! $this->result ) {
			$block_markup = '';
			$metadata     = array();
			foreach ( parse_blocks( $this->original_html ) as $block ) {
				if ( $block['blockName'] === null ) {
					$html_converter = new WP_Markup_Processor_Consumer( WP_HTML_Processor::create_fragment( $block['innerHTML'] ) );
					$result         = $html_converter->consume();
					$block_markup  .= $result->get_block_markup() . "\n";
					$metadata       = array_merge( $metadata, $result->get_all_metadata() );
				} else {
					$block_markup .= serialize_block( $block ) . "\n";
				}
			}
			$this->result = new WP_Blocks_With_Metadata(
				$block_markup,
				$metadata
			);
		}

		return $this->result;
	}
}
