<?php
/**
 * Idea:
 * * Stream-process the WXR file.
 * * Frontload all the assets before processing the posts – in an idempotent
 *   and re-entrant way.
 * * Import the posts, rewrite the URLs and IDs before inserting anything.
 * * Never do any post-processing at the database level after inserting. That's
 *   too slow for large datasets.
 *
 * @TODO:
 * * Re-entrant import via storing state on error, pausing, and resuming.
 * * Idempotent import.
 * * Error out if `source_site_url` is not set by the time we're processing
 *   the first encountered URL.
 * * Disable anything remotely related to KSES during the import. KSES
 *   modifies and often corrupts the content, and it also slows down the
 *   import. If we don't trust the imported content, we have larger problems
 *   than some escaping.
 * * Research which other filters are also worth disabling during the import.
 *   What would be a downside of disabling ALL the filters except the ones
 *   registered by WordPress Core? The upside would be preventing plugins from
 *   messing with the imported content. The downside would be the same. What else?
 *   Perhaps that could be a choice and left up to the API consumer?
 */
class WP_Stream_Importer {

	/**
	 * Populated from the WXR file's <wp:base_blog_url> tag.
	 */
	protected $source_site_url;
	private $entity_iterator_factory;
	/**
	 * @param array|string|null $query {
	 *     @type string      $uploads_path  The directory to download the media attachments to.
	 *                                      E.g. WP_CONTENT_DIR . '/uploads'
	 *     @type string      $uploads_url   The URL where the media attachments will be accessible
	 *                                      after the import. E.g. http://127.0.0.1:9400/wp-content/uploads/
	 * }
	 */
	protected $options;
	protected $downloader;

	public static function create(
		$entity_iterator_factory,
		$options = array()
	) {
		$options = static::parse_options( $options );
		return new WP_Stream_Importer( $entity_iterator_factory, $options );
	}

	protected static function parse_options( $options ) {
		if ( ! isset( $options['new_site_url'] ) ) {
			$options['new_site_url'] = get_site_url();
		}

		if ( ! isset( $options['uploads_path'] ) ) {
			$options['uploads_path'] = WP_CONTENT_DIR . '/uploads';
		}
		// Remove the trailing slash to make concatenation easier later.
		$options['uploads_path'] = rtrim( $options['uploads_path'], '/' );

		if ( ! isset( $options['uploads_url'] ) ) {
			$options['uploads_url'] = $options['new_site_url'] . '/wp-content/uploads';
		}
		// Remove the trailing slash to make concatenation easier later.
		$options['uploads_url'] = rtrim( $options['uploads_url'], '/' );

		return $options;
	}

	protected function __construct(
		$entity_iterator_factory,
		$options = array()
	) {
		$this->entity_iterator_factory = $entity_iterator_factory;
		$this->options                 = $options;
		if ( isset( $options['source_site_url'] ) ) {
			$this->source_site_url = $options['source_site_url'];
		}
	}

	/**
	 * Downloads all the assets referenced in the imported entities.
	 *
	 * This method is idempotent, re-entrant, and should be called
	 * before import_posts() so that every inserted post already has
	 * all its attachments downloaded.
	 */
	public function frontload_assets() {
		$factory          = $this->entity_iterator_factory;
		$entities         = $factory();
		$this->downloader = new WP_Attachment_Downloader( $this->options['uploads_path'] );
		foreach ( $entities as $entity ) {
			if ( $this->downloader->queue_full() ) {
				$this->downloader->poll();
				continue;
			}

			$data = $entity->get_data();
			if ( 'site_option' === $entity->get_type() && $data['option_name'] === 'home' ) {
				$this->source_site_url = $data['option_value'];
			} elseif ( 'post' === $entity->get_type() ) {
				if ( isset( $data['post_type'] ) && $data['post_type'] === 'attachment' ) {
					// Download media attachment entities.
					$this->enqueue_attachment_download(
						$data['attachment_url']
					);
				} elseif ( isset( $data['post_content'] ) ) {
					$this->enqueue_attachments_referenced_in_post(
						$data
					);
				}
			}
		}

		while ( $this->downloader->poll() ) {
			// Twiddle our thumbs as the downloader processes the requests...
			/**
			 * @TODO:
			 * * Consider inlining the downloader code into this class.
			 * * Process and store failures.
			 *   E.g. what if the attachment is not found? Error out? Ignore? In a UI-based
			 *   importer scenario, this is the time to log a failure to let the user
			 *   fix it later on. In a CLI-based Blueprint step importer scenario, we
			 *   might want to provide an "image not found" placeholder OR ignore the
			 *   failure.
			 */
		}
	}

	/**
	 * @TODO: Explore a way of making this idempotent. Maybe
	 *        use GUIDs to detect whether a post or an attachment
	 *        has already been imported? That would be slow on
	 *        large datasets, but maybe it could be a choice for
	 *        the API consumer?
	 */
	public function import_posts() {
		$importer = new WP_Entity_Importer();
		$factory  = $this->entity_iterator_factory;
		$entities = $factory();
		foreach ( $entities as $entity ) {
			$attachments = array();
			// Rewrite the URLs in the post.
			switch ( $entity->get_type() ) {
				case 'post':
					$data = $entity->get_data();
					foreach ( array( 'guid', 'post_content', 'post_excerpt' ) as $key ) {
						if ( ! isset( $data[ $key ] ) ) {
							continue;
						}
						$p = new WP_Block_Markup_Url_Processor( $data[ $key ], $this->source_site_url );
						while ( $p->next_url() ) {
							if ( $this->url_processor_matched_asset_url( $p ) ) {
								$filename      = $this->new_asset_filename( $p->get_raw_url() );
								$new_asset_url = $this->options['uploads_url'] . '/' . $filename;
								$p->replace_base_url( WP_URL::parse( $new_asset_url ) );
								$attachments[] = $new_asset_url;
								/**
								 * @TODO: How would we know a specific image block refers to a specific
								 *        attachment? We need to cross-correlate that to rewrite the URL.
								 *        The image block could have query parameters, too, but presumably the
								 *        path would be the same at least? What if the same file is referred
								 *        to by two different URLs? e.g. assets.site.com and site.com/assets/ ?
								 *        A few ideas: GUID, block attributes, fuzzy matching. Maybe a configurable
								 *        strategy? And the API consumer would make the decision?
								 */
							} elseif ( $this->source_site_url &&
								$p->get_parsed_url() &&
								url_matches( $p->get_parsed_url(), $this->source_site_url )
							) {
								$p->replace_base_url( WP_URL::parse( $this->options['new_site_url'] ) );
							} else {
								// Ignore other URLs.
							}
						}
						$data[ $key ] = $p->get_updated_html();
					}
					$entity->set_data( $data );
					break;
			}
			$post_id = $importer->import_entity( $entity );
			foreach ( $attachments as $filepath ) {
				$importer->import_attachment( $filepath, $post_id );
			}
		}
	}

	/**
	 * The downloaded file name is based on the URL hash.
	 *
	 * Download the asset to a new path.
	 *
	 * Note the path here is different than on the original site.
	 * There isn't an easy way to preserve the original assets paths on
	 * the new site.
	 *
	 * * The assets may come from multiple domains
	 * * The paths may be outside of `/wp-content/uploads/`
	 * * The same path on multiple domains may point to different files
	 *
	 * Even if we tried to preserve the paths starting with `/wp-content/uploads/`,
	 * we would run into race conditions where, in case of overlapping paths,
	 * the first downloaded asset would win.
	 *
	 * The assets downloader is meant to be idempotent, deterministic, and re-entrant.
	 *
	 * Therefore, instead of trying to preserve the original paths, we'll just
	 * compute an idempotent and deterministic new path for each asset.
	 *
	 * While using a content hash is tempting, it has two downsides:
	 * * We'd need to download the asset before computing the hash.
	 * * It would de-duplicate the imported assets even if they have
	 *   different URLs. This would cause subtle issues in the new sites.
	 *   Imagine two users uploading the same image. Each user has
	 *   different permissions. Just because Bob deletes his copy, doesn't
	 *   mean we should delete Alice's copy.
	 */
	private function new_asset_filename( string $raw_asset_url ) {
		$filename   = md5( $raw_asset_url );
		$parsed_url = WP_URL::parse( $raw_asset_url );
		if ( false !== $parsed_url ) {
			$pathname = $parsed_url->pathname;
		} else {
			// Assume $raw_asset_url is a relative path when it cannot be
			// parsed as an absolute URL.
			$pathname = $raw_asset_url;
		}
		$extension = pathinfo( $pathname, PATHINFO_EXTENSION );
		if ( ! empty( $extension ) ) {
			$filename .= '.' . $extension;
		}
		return $filename;
	}

	/**
	 * Infers and enqueues the attachments URLs from the post content.
	 *
	 * Why not just emit the attachment URLs from WP_Markdown_Directory_Tree_Reader
	 * as other entities?
	 *
	 * Whether it's Markdown, static HTML, or another static file format,
	 * we'll need to recover the attachment URLs from the We can either
	 * have a separate pipeline step for that, or burden every format
	 * reader with reimplementing the same logic. So let's just keep it
	 * separated.
	 */
	protected function enqueue_attachments_referenced_in_post( $post ) {
		$p = new WP_Block_Markup_Url_Processor( $post['post_content'], $this->source_site_url );
		while ( $p->next_url() ) {
			if ( ! $this->url_processor_matched_asset_url( $p ) ) {
				continue;
			}

			$enqueued = $this->enqueue_attachment_download(
				$p->get_raw_url(),
				$post['source_path'] ?? $post['slug'] ?? null
			);
			if ( false === $enqueued ) {
				continue;
			}
		}
	}

	protected function enqueue_attachment_download( string $raw_url, $context_path = null ) {
		$new_filename     = $this->new_asset_filename( $raw_url );
		$downloadable_url = $this->rewrite_attachment_url( $raw_url, $context_path );
		$success          = $this->downloader->enqueue_if_not_exists(
			$downloadable_url,
			$new_filename
		);
		if ( false === $success ) {
			// @TODO: Save the failure info somewhere so the user can review it later
			//        and either retry or provide their own asset.
			// Meanwhile, we may either halt the content import, or provide a placeholder
			// asset.
			_doing_it_wrong( __METHOD__, "Failed to fetch attachment '$raw_url' from '$downloadable_url'", '__WP_VERSION__' );
		}
		return $success;
	}

	protected function rewrite_attachment_url( string $raw_url, $context_path = null ) {
		if ( WP_URL::canParse( $raw_url ) ) {
			// Absolute URL, nothing to do.
			return $raw_url;
		}
		$base_url = $this->source_site_url;
		if ( null !== $base_url && null !== $context_path ) {
			$base_url = $base_url . '/' . ltrim( $context_path, '/' );
		}
		$parsed_url = WP_URL::parse( $raw_url, $base_url );
		if ( false === $parsed_url ) {
			return false;
		}
		return $parsed_url->toString();
	}

	/**
	 * By default, we want to download all the assets referenced in the
	 * posts that are hosted on the source site.
	 *
	 * @TODO: How can we process the videos?
	 * @TODO: What other asset types are there?
	 */
	protected function url_processor_matched_asset_url( WP_Block_Markup_Url_Processor $p ) {
		return (
			$p->get_tag() === 'IMG' &&
			$p->get_inspected_attribute_name() === 'src' &&
			( ! $this->source_site_url || url_matches( $p->get_parsed_url(), $this->source_site_url ) )
		);
	}
}
