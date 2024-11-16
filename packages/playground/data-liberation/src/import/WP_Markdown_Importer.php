<?php

/**
 * Assume markdown importer is not fully re-entrant. We're unlikely to see 100GB of
 * markdown files so let's specialize in managable data amounts for now. The only
 * re-entrant part would be pre-fetching the static assets. If the asset already exists,
 * there's no need to re-fetch it.
 */
class WP_Markdown_Importer extends WP_Stream_Importer {

	public static function create(
		$entity_iterator_factory,
		$options = array()
	) {
		$options = static::parse_options( $options );
		return new WP_Markdown_Importer( $entity_iterator_factory, $options );
	}

	protected static function parse_options( $options ) {
		if ( ! isset( $options['source_site_url'] ) ) {
			_doing_it_wrong( __METHOD__, 'The source_site_url option is required.', '__WP_VERSION__' );
			return false;
		}
		if ( ! isset( $options['local_markdown_assets_root'] ) ) {
			_doing_it_wrong( __METHOD__, 'The markdown_assets_root option is required.', '__WP_VERSION__' );
			return false;
		}
		if ( ! is_dir( $options['local_markdown_assets_root'] ) ) {
			_doing_it_wrong( __METHOD__, 'The markdown_assets_root option must point to a directory.', '__WP_VERSION__' );
			return false;
		}
		$options['local_markdown_assets_root'] = rtrim( $options['local_markdown_assets_root'], '/' );
		if ( ! isset( $options['local_markdown_assets_url_prefix'] ) ) {
			_doing_it_wrong( __METHOD__, 'The local_markdown_assets_url_prefix option is required.', '__WP_VERSION__' );
			return null;
		}

		return parent::parse_options( $options );
	}

	protected function rewrite_attachment_url( string $raw_url, $context_path = null ) {
		/**
		 * For Docusaurus docs, URLs starting with `@site` are referring
		 * to local files. Let's convert them to file:// URLs.
		 *
		 * Also, we cannot just compare the host to `@site` because, after parsing,
		 * the host is actually just "site". The "@" symbol denotes an empty
		 * username and is present in the URL string.
		 */
		if ( str_starts_with( $raw_url, $this->options['local_markdown_assets_url_prefix'] ) ) {
			// @TODO: Source the file from the current input stream if we can.
			//        This would allow stream-importing zipped Markdown and WXR directory
			//        structures.
			//        Maybe for v1 we could just support importing them from ZIP files
			//        that are already downloaded and available in a local directory just
			//        to avoid additional data transfer and the hurdle with implementing
			//        multiple range requests.
			$relative_asset_path = substr( $raw_url, strlen( $this->options['local_markdown_assets_url_prefix'] ) );
			$relative_asset_path = '/' . ltrim( $relative_asset_path, '/' );
			$raw_url             = implode(
				'',
				array(
					'file://',
					$this->options['local_markdown_assets_root'],
					$relative_asset_path,
				)
			);
		}

		return parent::rewrite_attachment_url( $raw_url );
	}

	/**
	 * When processing Markdown, we want to download all the images
	 * referenced in the image tags.
	 *
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
