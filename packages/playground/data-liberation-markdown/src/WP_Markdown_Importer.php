<?php

use WordPress\Filesystem\WP_Filesystem;

class WP_Markdown_Importer extends WP_Stream_Importer {

	public static function create_for_markdown_directory( $markdown_directory, $options = array(), $cursor = null ) {
		return WP_Markdown_Importer::create(
			function ( $cursor = null ) use ( $markdown_directory ) {
				// @TODO: Handle $cursor
				return new WP_Directory_Tree_Entity_Reader( 
					new WP_Filesystem(),
					array (
						'root_dir' => $markdown_directory,
						'first_post_id' => 1,
						'allowed_extensions' => array( 'md' ),
						'index_file_patterns' => array( '#^index\.md$#' ),
						'markup_converter_factory' => function( $content ) {
							return new WP_Markdown_To_Blocks( $content );
						},
					)
				);
			},
			$options,
			$cursor
		);
	}

	protected static function parse_options( $options ) {
		if ( ! isset( $options['source_site_url'] ) ) {
			_doing_it_wrong( __METHOD__, 'The source_site_url option is required.', '__WP_VERSION__' );
			return false;
		}
		$options['default_source_site_url'] = $options['source_site_url'];

		if ( ! isset( $options['local_markdown_assets_root'] ) ) {
			_doing_it_wrong( __METHOD__, 'The markdown_assets_root option is required.', '__WP_VERSION__' );
			return false;
		}
		if ( ! is_dir( $options['local_markdown_assets_root'] ) ) {
			_doing_it_wrong( __METHOD__, 'The markdown_assets_root option must point to a directory.', '__WP_VERSION__' );
			return false;
		}
		$options['local_markdown_assets_root'] = rtrim( $options['local_markdown_assets_root'], '/' );
		
		return parent::parse_options( $options );
	}

	protected function rewrite_attachment_url( string $raw_url, $context_path = null ) {
		/**
		 * For Docusaurus docs, URLs starting with `@site` are referring
		 * to local files. Let's convert them to file:// URLs.
		 */
		if (
			isset( $this->options['local_markdown_assets_url_prefix'] ) &&
			str_starts_with( $raw_url, $this->options['local_markdown_assets_url_prefix'] )
		) {
			// @TODO: Source the file from the current input stream if we can.
			//        This would allow stream-importing zipped Markdown and WXR directory
			//        structures.
			//        Maybe for v1 we could just support importing them from ZIP files
			//        that are already downloaded and available in a local directory just
			//        to avoid additional data transfer and the hurdle with implementing
			//        multiple range requests.
			$relative_asset_path = substr( $raw_url, strlen( $this->options['local_markdown_assets_url_prefix'] ) );
			$relative_asset_path = '/' . ltrim( $relative_asset_path, '/' );
			$raw_url             = (
				'file://' .
				$this->options['local_markdown_assets_root'] .
				$relative_asset_path
			);
		}

		return parent::rewrite_attachment_url( $raw_url, $context_path );
	}

	/**
	 * When processing Markdown, we'll download all the images
	 * referenced in the image tags.
	 *
	 * @TODO: Actually, should we?
	 * @TODO: How can we process the videos?
	 * @TODO: What other asset types are there?
	 */
	protected function url_processor_matched_asset_url( WP_Block_Markup_Url_Processor $p ) {
		return (
			$p->get_tag() === 'IMG' &&
			$p->get_inspected_attribute_name() === 'src'
		);
	}
}
