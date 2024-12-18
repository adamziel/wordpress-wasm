<?php
/**
 * Based on https://github.com/humanmade/WordPress-Importer
 *
 * @TODO
 * * Handle missing fields. Some WXR files have comments, but no author information.
 *   Some others have posts, but no content. What should the importer do in these
 *   cases?
 * * Performant deduplication by GUID. When a post with a given GUID is found, let's
 *   make a decision:
 *   - Skip importing the new one
 *   - Update the existing one
 *   In both cases, we need to decide what to do with the comments, post_meta,
 *   attachments, etc:
 *   * Should we overwrite the ones in the database?
 *   * Insert the ones from WXR in addition to the existing ones?
 *   * Ignore the ones from WXR?
 * * Don't run any blocking downloads of attachments here. We're only inserting
 *   the data at this point. All the downloads have already been processed by now.
 */

class WP_Entity_Importer {

	/**
	 * Version of WXR we're importing.
	 *
	 * Defaults to 1.0 for compatibility. Typically overridden by a
	 * `<wp:wxr_version>` tag at the start of the file.
	 *
	 * @var string
	 */
	protected $version = '1.0';

	/**
	 * Regular expression for checking if a post references an attachment
	 *
	 * Note: This is a quick, weak check just to exclude text-only posts. More
	 * vigorous checking is done later to verify.
	 *
	 * @TODO: Move to WP_HTML_Processor
	 */
	const REGEX_HAS_ATTACHMENT_REFS = '!
		(
			# Match anything with an image or attachment class
			class=[\'"].*?\b(wp-image-\d+|attachment-[\w\-]+)\b
		|
			# Match anything that looks like an upload URL
			src=[\'"][^\'"]*(
				[0-9]{4}/[0-9]{2}/[^\'"]+\.(jpg|jpeg|png|gif)
			|
				content/uploads[^\'"]+
			)[\'"]
		)!ix';

	// information to import from WXR file
	protected $categories = array();
	protected $tags       = array();
	protected $base_url   = '';

	protected $logger;
	protected $options = array();

	// NEW STYLE
	protected $mapping            = array();
	protected $requires_remapping = array();
	protected $exists             = array();
	protected $user_slug_override = array();

	protected $url_remap       = array();
	protected $featured_images = array();

	/**
	 * @var WP_Topological_Sorter
	 */
	private $topological_sorter;

	/**
	 * Constructor
	 *
	 * @param array $options {
	 *     @var bool $prefill_existing_posts Should we prefill `post_exists` calls? (True prefills and uses more memory, false checks once per imported post and takes longer. Default is true.)
	 *     @var bool $prefill_existing_comments Should we prefill `comment_exists` calls? (True prefills and uses more memory, false checks once per imported comment and takes longer. Default is true.)
	 *     @var bool $prefill_existing_terms Should we prefill `term_exists` calls? (True prefills and uses more memory, false checks once per imported term and takes longer. Default is true.)
	 *     @var bool $update_attachment_guids Should attachment GUIDs be updated to the new URL? (True updates the GUID, which keeps compatibility with v1, false doesn't update, and allows deduplication and reimporting. Default is false.)
	 *     @var bool $fetch_attachments Fetch attachments from the remote server. (True fetches and creates attachment posts, false skips attachments. Default is false.)
	 *     @var int $default_author User ID to use if author is missing or invalid. (Default is null, which leaves posts unassigned.)
	 * }
	 */
	public function __construct( $options = array() ) {
		// Initialize some important variables
		$empty_types = array(
			'post'    => array(),
			'comment' => array(),
			'term'    => array(),
			'user'    => array(),
		);

		$this->mapping              = $empty_types;
		$this->mapping['user_slug'] = array();
		$this->mapping['term_id']   = array();
		$this->requires_remapping   = $empty_types;
		$this->exists               = $empty_types;
		$this->logger               = isset( $options['logger'] ) ? $options['logger'] : new WP_Logger();

		$this->options = wp_parse_args(
			$options,
			array(
				'prefill_existing_posts'    => true,
				'prefill_existing_comments' => true,
				'prefill_existing_terms'    => true,
				'update_attachment_guids'   => false,
				'fetch_attachments'         => false,
				'default_author'            => null,
			)
		);

		WP_Topological_Sorter::activate();
		$this->topological_sorter = new WP_Topological_Sorter( $this->options );
	}

	public function import_entity( WP_Imported_Entity $entity ) {
		$type = $entity->get_type();
		$data = $entity->get_data();
		switch ( $entity->get_type() ) {
			case WP_Imported_Entity::TYPE_POST:
				return $this->import_post( $data );
			case WP_Imported_Entity::TYPE_POST_META:
				return $this->import_post_meta( $data, $data['post_id'] );
			case WP_Imported_Entity::TYPE_COMMENT:
				return $this->import_comment( $data, $data['post_id'] );
			case WP_Imported_Entity::TYPE_COMMENT_META:
				return $this->import_comment_meta( $data, $data['comment_id'] );
			case WP_Imported_Entity::TYPE_TERM:
			case WP_Imported_Entity::TYPE_TAG:
			case WP_Imported_Entity::TYPE_CATEGORY:
				return $this->import_term( $data );
			case WP_Imported_Entity::TYPE_TERM_META:
				return $this->import_term_meta( $data, $data['term_id'] );
			case WP_Imported_Entity::TYPE_USER:
				return $this->import_user( $data );
			case WP_Imported_Entity::TYPE_SITE_OPTION:
				return $this->import_site_option( $data );
			default:
				throw new \InvalidArgumentException( "Unknown entity type: $type" );
		}
	}

	public function import_site_option( $data ) {
		$this->logger->info(
			sprintf(
				/* translators: %s: option name */
				__( 'Imported site option "%s"', 'wordpress-importer' ),
				$data['option_name']
			)
		);

		update_option( $data['option_name'], $data['option_value'] );
	}

	public function import_user( $data ) {
		/**
		 * Pre-process user data.
		 *
		 * @param array $data User data. (Return empty to skip.)
		 */
		$data = apply_filters( 'wp_importer_pre_import_user', $data );
		if ( empty( $data ) ) {
			return false;
		}

		// Have we already handled this user?
		$original_id   = isset( $data['ID'] ) ? $data['ID'] : 0;
		$original_slug = $data['user_login'];

		if ( isset( $this->mapping['user'][ $original_id ] ) ) {
			$existing = $this->mapping['user'][ $original_id ];

			// Note the slug mapping if we need to too
			if ( ! isset( $this->mapping['user_slug'][ $original_slug ] ) ) {
				$this->mapping['user_slug'][ $original_slug ] = $existing;
			}

			return false;
		}

		if ( isset( $this->mapping['user_slug'][ $original_slug ] ) ) {
			$existing = $this->mapping['user_slug'][ $original_slug ];

			// Ensure we note the mapping too
			$this->mapping['user'][ $original_id ] = $existing;

			return false;
		}

		// Allow overriding the user's slug
		$login = $original_slug;
		if ( isset( $this->user_slug_override[ $login ] ) ) {
			$login = $this->user_slug_override[ $login ];
		}

		$userdata = array(
			'user_login'   => sanitize_user( $login, true ),
			'user_pass'    => wp_generate_password(),
		);

		$allowed = array(
			'user_email'   => true,
			'display_name' => true,
			'first_name'   => true,
			'last_name'    => true,
		);
		foreach ( $data as $key => $value ) {
			if ( ! isset( $allowed[ $key ] ) ) {
				continue;
			}

			$userdata[ $key ] = $data[ $key ];
		}

		$user_id = wp_insert_user( wp_slash( $userdata ) );
		if ( is_wp_error( $user_id ) ) {
			$this->logger->error(
				sprintf(
					/* translators: %s: user login */
					__( 'Failed to import user "%s"', 'wordpress-importer' ),
					$userdata['user_login']
				)
			);
			$this->logger->debug( $user_id->get_error_message() );

			/**
			 * User processing failed.
			 *
			 * @param WP_Error $user_id Error object.
			 * @param array $userdata Raw data imported for the user.
			 */
			do_action( 'wxr_importer_process_failed_user', $user_id, $userdata );
			return false;
		}

		if ( $original_id ) {
			$this->mapping['user'][ $original_id ] = $user_id;
		}
		$this->mapping['user_slug'][ $original_slug ] = $user_id;

		$this->logger->info(
			sprintf(
				/* translators: %s: user login */
				__( 'Imported user "%s"', 'wordpress-importer' ),
				$userdata['user_login']
			)
		);
		$this->logger->debug(
			sprintf(
				/* translators: 1: original user ID, 2: new user ID */
				__( 'User %1$d remapped to %2$d', 'wordpress-importer' ),
				$original_id,
				$user_id
			)
		);

		// TODO: Implement meta handling once WXR includes it
		/**
		 * User processing completed.
		 *
		 * @param int $user_id New user ID.
		 * @param array $userdata Raw data imported for the user.
		 */
		do_action( 'wxr_importer_processed_user', $user_id, $userdata );
		// $this->topological_sorter->map_entity( 'user', $userdata, $user_id );
	}

	public function import_term( $data ) {
		/**
		 * Pre-process term data.
		 *
		 * @param array $data Term data. (Return empty to skip.)
		 * @param array $meta Meta data.
		 */
		$data = apply_filters( 'wxr_importer_pre_process_term', $data );
		$data = $this->topological_sorter->get_mapped_entity( 'term', $data );
		if ( empty( $data ) ) {
			return false;
		}

		$original_id = isset( $data['id'] ) ? (int) $data['id'] : 0;
		$parent      = isset( $data['parent'] ) ? $data['parent'] : null;
		$mapping_key = sha1( $data['taxonomy'] . ':' . $data['slug'] );
		$existing    = $this->term_exists( $data );
		if ( $existing ) {

			/**
			 * Term processing already imported.
			 *
			 * @param array $data Raw data imported for the term.
			 */
			do_action( 'wxr_importer_process_already_imported_term', $data );

			$this->mapping['term'][ $mapping_key ]    = $existing;
			$this->mapping['term_id'][ $original_id ] = $existing;
			return false;
		}

		// WP really likes to repeat itself in export files
		if ( isset( $this->mapping['term'][ $mapping_key ] ) ) {
			return false;
		}

		$termdata = array();
		$allowed  = array(
			'description' => true,
			'name'        => true,
			'slug'        => true,
			'parent'      => true,
		);

		// Map the parent term, or mark it as one we need to fix
		if ( $parent ) {
			// TODO: add parent mapping and remapping
			// $requires_remapping = false;
			/*if ( isset( $this->mapping['term'][ $parent_id ] ) ) {
				$data['parent'] = $this->mapping['term'][ $parent_id ];
			} else {
				// Prepare for remapping later
				$meta[] = array( 'key' => '_wxr_import_parent', 'value' => $parent_id );
				$requires_remapping = true;

				// Wipe the parent for now
				$data['parent'] = 0;
			}*/
			$parent_term = term_exists( $parent, $data['taxonomy'] );

			if ( $parent_term ) {
				$data['parent'] = $parent_term['term_id'];
			} else {
				// It can happens that the parent term is not imported yet in manually created WXR files.
				$parent_term = wp_insert_term( $parent, $data['taxonomy'] );

				if ( is_wp_error( $parent_term ) ) {
					$this->logger->error(
						sprintf(
							/* translators: %s: taxonomy name */
							__( 'Failed to import parent term for "%s"', 'wordpress-importer' ),
							$data['taxonomy']
						)
					);
				} else {
					$data['parent'] = $parent_term['term_id'];
				}
			}
		}

		// Filter the term data to only include allowed keys.
		foreach ( $data as $key => $value ) {
			if ( ! isset( $allowed[ $key ] ) ) {
				continue;
			}

			$termdata[ $key ] = $data[ $key ];
		}

		$term   = term_exists( $data['slug'], $data['taxonomy'] );
		$result = null;

		if ( is_array( $term ) ) {
			// Update the existing term.
			$result = wp_update_term( $term['term_id'], $data['taxonomy'], $termdata );
		} else {
			// Create a new term.
			$result = wp_insert_term( $data['name'], $data['taxonomy'], $termdata );
		}

		if ( is_wp_error( $result ) ) {
			$this->logger->warning(
				sprintf(
					/* translators: 1: taxonomy name, 2: term name */
					__( 'Failed to import %1$s %2$s', 'wordpress-importer' ),
					$data['taxonomy'],
					$data['name']
				)
			);
			$this->logger->debug( $result->get_error_message() );
			do_action( 'wp_import_insert_term_failed', $result, $data );

			/**
			 * Term processing failed.
			 *
			 * @param WP_Error $result Error object.
			 * @param array $data Raw data imported for the term.
			 * @param array $meta Meta data supplied for the term.
			 */
			do_action( 'wxr_importer_process_failed_term', $result, $data );
			return false;
		}

		$term_id = $result['term_id'];

		$this->mapping['term'][ $mapping_key ]    = $term_id;
		$this->mapping['term_id'][ $original_id ] = $term_id;

		$this->logger->info(
			sprintf(
			/* translators: 1: term name, 2: taxonomy name */
				__( 'Imported "%1$s" (%2$s)', 'wordpress-importer' ),
				$data['name'],
				$data['taxonomy']
			)
		);
		$this->logger->debug(
			sprintf(
			/* translators: 1: original term ID, 2: new term ID */
				__( 'Term %1$d remapped to %2$d', 'wordpress-importer' ),
				$original_id,
				$term_id
			)
		);

		do_action( 'wp_import_insert_term', $term_id, $data );

		/**
		 * Term processing completed.
		 *
		 * @param int $term_id New term ID.
		 * @param array $data Raw data imported for the term.
		 */
		do_action( 'wxr_importer_processed_term', $term_id, $data );
		$this->topological_sorter->map_entity( 'term', $data, $term_id );
	}

	public function import_term_meta( $meta_item, $term_id ) {
		if ( empty( $meta_item ) ) {
			return true;
		}

		/**
		 * Pre-process term meta data.
		 *
		 * @param array $meta_item Meta data. (Return empty to skip.)
		 * @param int $term_id Term the meta is attached to.
		 */
		$meta_item = apply_filters( 'wxr_importer_pre_process_term_meta', $meta_item, $term_id );
		$meta_item = $this->topological_sorter->get_mapped_entity( 'term_meta', $meta_item, $term_id );
		if ( empty( $meta_item ) ) {
			return false;
		}

		// Have we already processed this?
		if ( isset( $element['_already_mapped'] ) ) {
			$this->logger->debug( 'Skipping term meta, already processed' );
			return;
		}

		if ( ! isset( $meta_item['term_id'] ) ) {
			$meta_item['term_id'] = $term_id;
		}

		$value        = maybe_unserialize( $meta_item['meta_value'] );
		$term_meta_id = add_term_meta( $meta_item['term_id'], wp_slash( $meta_item['meta_key'] ), wp_slash_strings_only( $value ) );

		do_action( 'wxr_importer_processed_term_meta', $term_meta_id, $meta_item, $meta_item['term_id'] );
		$this->topological_sorter->map_entity( 'term_meta', $meta_item, $term_meta_id, $meta_item['term_id'] );
	}

	/**
	 * Prefill existing post data.
	 *
	 * This preloads all GUIDs into memory, allowing us to avoid hitting the
	 * database when we need to check for existence. With larger imports, this
	 * becomes prohibitively slow to perform SELECT queries on each.
	 *
	 * By preloading all this data into memory, it's a constant-time lookup in
	 * PHP instead. However, this does use a lot more memory, so for sites doing
	 * small imports onto a large site, it may be a better tradeoff to use
	 * on-the-fly checking instead.
	 */
	protected function prefill_existing_posts() {
		global $wpdb;
		$posts = $wpdb->get_results( "SELECT ID, guid FROM {$wpdb->posts}" );

		foreach ( $posts as $item ) {
			$this->exists['post'][ $item->guid ] = $item->ID;
		}
	}

	/**
	 * Does the post exist?
	 *
	 * @param array $data Post data to check against.
	 * @return int|bool Existing post ID if it exists, false otherwise.
	 */
	protected function post_exists( $data ) {
		// Constant-time lookup if we prefilled
		$exists_key = $data['guid'] ?? null;

		if ( $this->options['prefill_existing_posts'] ) {
			return isset( $this->exists['post'][ $exists_key ] ) ? $this->exists['post'][ $exists_key ] : false;
		}

		// No prefilling, but might have already handled it
		if ( isset( $this->exists['post'][ $exists_key ] ) ) {
			return $this->exists['post'][ $exists_key ];
		}

		// Still nothing, try post_exists, and cache it
		$exists                              = post_exists( $data['post_title'], $data['post_content'], $data['post_date'] );
		$this->exists['post'][ $exists_key ] = $exists;

		return $exists;
	}

	/**
	 * Create new posts based on import information
	 *
	 * Posts marked as having a parent which doesn't exist will become top level items.
	 * Doesn't create a new post if: the post type doesn't exist, the given post ID
	 * is already noted as imported or a post with the same title and date already exists.
	 * Note that new/updated terms, comments and meta are imported for the last of the above.
	 */
	public function import_post( $data ) {
		$parent_id = isset( $data['post_parent'] ) ? (int) $data['post_parent'] : 0;

		/**
		 * Pre-process post data.
		 *
		 * @param array $data Post data. (Return empty to skip.)
		 * @param array $meta Meta data.
		 * @param array $comments Comments on the post.
		 * @param array $terms Terms on the post.
		 */
		$data = apply_filters( 'wxr_importer_pre_process_post', $data, $parent_id );
		$data = $this->topological_sorter->get_mapped_entity( 'post', $data, $parent_id );
		if ( empty( $data ) ) {
			$this->logger->debug( 'Skipping post, empty data' );
			return false;
		}

		$original_id = isset( $data['post_id'] ) ? (int) $data['post_id'] : 0;

		// Have we already processed this?
		if ( isset( $element['_already_mapped'] ) ) {
			$this->logger->debug( 'Skipping post, already processed' );
			return;
		}

		$post_type        = $data['post_type'] ?? 'post';
		$post_type_object = get_post_type_object( $post_type );

		// Is this type even valid?
		if ( ! $post_type_object ) {
			$this->logger->warning(
				sprintf(
					/* translators: 1: post title, 2: post type */
					__( 'Failed to import "%1$s": Invalid post type %2$s', 'wordpress-importer' ),
					$data['post_title'],
					$post_type
				)
			);
			return false;
		}

		$post_exists = $this->post_exists( $data );
		if ( $post_exists ) {
			$this->logger->info(
				sprintf(
					/* translators: 1: post type name, 2: post title */
					__( '%1$s "%2$s" already exists.', 'wordpress-importer' ),
					$post_type_object->labels->singular_name,
					$data['post_title']
				)
			);

			/**
			 * Post processing already imported.
			 *
			 * @param array $data Raw data imported for the post.
			 */
			do_action( 'wxr_importer_process_already_imported_post', $data );

			return false;
		}

		// Map the parent post, or mark it as one we need to fix
		if ( isset( $data['post_parent'] ) ) {
			$data['post_parent'] = $this->map_post_id( (int) $data['post_parent'] );
		}
		$requires_remapping = false;

		// Map the author, or mark it as one we need to fix
		$author = sanitize_user( $data['post_author'] ?? '', true );
		if ( empty( $author ) ) {
			// Missing or invalid author, use default if available.
			$data['post_author'] = $this->options['default_author'];
		} elseif ( isset( $this->mapping['user_slug'][ $author ] ) ) {
			$data['post_author'] = $this->mapping['user_slug'][ $author ];
		} else {
			$meta[]             = array(
				'key' => '_wxr_import_user_slug',
				'value' => $author,
			);
			$requires_remapping = true;

			$data['post_author'] = (int) get_current_user_id();
		}

		// Whitelist to just the keys we allow
		$postdata = array(
			'import_id' => $data['post_id'] ?? null,
		);
		$allowed  = array(
			'post_author'    => true,
			'post_date'      => true,
			'post_date_gmt'  => true,
			'post_content'   => true,
			'post_excerpt'   => true,
			'post_title'     => true,
			'post_status'    => true,
			'post_name'      => true,
			'comment_status' => true,
			'ping_status'    => true,
			'guid'           => true,
			'post_parent'    => true,
			'menu_order'     => true,
			'post_type'      => true,
			'post_password'  => true,
		);
		foreach ( $data as $key => $value ) {
			if ( ! isset( $allowed[ $key ] ) ) {
				continue;
			}

			$postdata[ $key ] = $data[ $key ];
		}

		$postdata = apply_filters( 'wp_import_post_data_processed', $postdata, $data );

		if ( isset( $postdata['post_type'] ) && 'attachment' === $postdata['post_type'] ) {
			// @TODO: Do not download any attachments here. We're just inserting the data
			//        at this point. All the downloads have already been processed by now.
			if ( ! $this->options['fetch_attachments'] ) {
				$this->logger->notice(
					sprintf(
						/* translators: %s: post title */
						__( 'Skipping attachment "%s", fetching attachments disabled' ),
						$data['post_title']
					)
				);
				/**
				 * Post processing skipped.
				 *
				 * @param array $data Raw data imported for the post.
				 * @param array $meta Raw meta data, already processed by {@see process_post_meta}.
				 */
				do_action( 'wxr_importer_process_skipped_post', $data );
				return false;
			}
			$remote_url = ! empty( $data['attachment_url'] ) ? $data['attachment_url'] : $data['guid'];
			$post_id    = $this->process_attachment( $postdata, $meta, $remote_url );
		} else {
			$post_id = wp_insert_post( $postdata, true );
			do_action( 'wp_import_insert_post', $post_id, $original_id, $postdata, $data );
		}

		if ( is_wp_error( $post_id ) ) {
			$this->logger->error(
				sprintf(
					/* translators: 1: post title, 2: post type name */
					__( 'Failed to import "%1$s" (%2$s)', 'wordpress-importer' ),
					$data['post_title'],
					$post_type_object->labels->singular_name
				)
			);
			$this->logger->debug( $post_id->get_error_message() );

			/**
			 * Post processing failed.
			 *
			 * @param WP_Error $post_id Error object.
			 * @param array $data Raw data imported for the post.
			 * @param array $meta Raw meta data, already processed by {@see process_post_meta}.
			 * @param array $comments Raw comment data, already processed by {@see process_comments}.
			 * @param array $terms Raw term data, already processed.
			 */
			do_action( 'wxr_importer_process_failed_post', $post_id, $data, $meta, $comments, $terms );
			return false;
		}

		// Ensure stickiness is handled correctly too
		$is_sticky = $data['is_sticky'] ?? '0';
		if ( $is_sticky === '1' ) {
			stick_post( $post_id );
		}

		// map pre-import ID to local ID
		$this->mapping['post'][ $original_id ] = (int) $post_id;
		if ( $requires_remapping ) {
			$this->requires_remapping['post'][ $post_id ] = true;
		}
		$this->mark_post_exists( $data, $post_id );

		// Add terms to the post
		if ( ! empty( $data['terms'] ) ) {
			$terms_to_set = array();

			foreach ( $data['terms'] as $term ) {
				// Back compat with WXR 1.0 map 'tag' to 'post_tag'
				$taxonomy    = ( 'tag' === $term['taxonomy'] ) ? 'post_tag' : $term['taxonomy'];
				$term_exists = term_exists( $term['slug'], $taxonomy );
				$term_id     = is_array( $term_exists ) ? $term_exists['term_id'] : $term_exists;

				if ( ! $term_id ) {
					// @TODO: Add a unit test with a WXR with one post and X tags without root declated tags.
					$new_term = wp_insert_term( $term['slug'], $taxonomy, $term );

					if ( ! is_wp_error( $new_term ) ) {
						$term_id = $new_term['term_id'];

						$this->topological_sorter->map_entity( 'term', $new_term, $term_id );
					} else {
						continue;
					}
				}
				$terms_to_set[ $taxonomy ][] = intval( $term_id );
			}

			foreach ( $terms_to_set as $tax => $ids ) {
				// Add the post terms to the post
				wp_set_post_terms( $post_id, $ids, $tax );
			}
		}

		$this->logger->info(
			sprintf(
				/* translators: 1: post title, 2: post type name */
				__( 'Imported "%1$s" (%2$s)', 'wordpress-importer' ),
				$data['post_title'] ?? '',
				$post_type_object->labels->singular_name
			)
		);
		$this->logger->debug(
			sprintf(
				/* translators: 1: original post ID, 2: new post ID */
				__( 'Post %1$d remapped to %2$d', 'wordpress-importer' ),
				$original_id,
				$post_id
			)
		);

		/**
		 * Post processing completed.
		 *
		 * @param int $post_id New post ID.
		 * @param array $data Raw data imported for the post.
		 * @param array $meta Raw meta data, already processed by {@see process_post_meta}.
		 * @param array $comments Raw comment data, already processed by {@see process_comments}.
		 * @param array $terms Raw term data, already processed.
		 */
		do_action( 'wxr_importer_processed_post', $post_id, $data );
		$this->topological_sorter->map_entity( 'post', $data, $post_id );

		return $post_id;
	}

	/**
	 * Given an ID suggested by the WXR file, return the ID that should be used
	 * in the WordPress database on the new site. Just because the original site
	 * used, say, 173 as an ID, doesn't mean that ID is available on the new site.
	 *
	 * @TODO: Consider what type of remapping should we do here?
	 *        Add 1,000,000,000 to the largest ID in the database without
	 *        changing the autoincrement value? Using negative IDs and then
	 *        mapping them back to positive IDs?
	 *
	 *        Or perhaps relying on IDs is a wrong approach entirely and relying
	 *        on GUIDs would be more useful? But then we'd need a ton of
	 *        GUID => ID lookups that would slow down large imports.
	 */
	private function map_post_id( $id ) {
		return $id;
	}

	// @TOOD handle terms
	// $terms = apply_filters( 'wp_import_post_terms', $terms, $post_id, $data );

	// if ( ! empty( $terms ) ) {
	//  $term_ids = array();
	//  foreach ( $terms as $term ) {
	//      $taxonomy = $term['taxonomy'];
	//      $key = sha1( $taxonomy . ':' . $term['slug'] );

	//      if ( isset( $this->mapping['term'][ $key ] ) ) {
	//          $term_ids[ $taxonomy ][] = (int) $this->mapping['term'][ $key ];
	//      } else {
	//          $meta[] = array( 'key' => '_wxr_import_term', 'value' => $term );
	//          $requires_remapping = true;
	//      }
	//  }

	//  foreach ( $term_ids as $tax => $ids ) {
	//      $tt_ids = wp_set_post_terms( $post_id, $ids, $tax );
	//      do_action( 'wp_import_set_post_terms', $tt_ids, $ids, $tax, $post_id, $data );
	//  }
	// }

	/**
	 * Attempt to create a new menu item from import data
	 *
	 * Fails for draft, orphaned menu items and those without an associated nav_menu
	 * or an invalid nav_menu term. If the post type or term object which the menu item
	 * represents doesn't exist then the menu item will not be imported (waits until the
	 * end of the import to retry again before discarding).
	 *
	 * @param array $item Menu item details from WXR file
	 */
	protected function process_menu_item_meta( $post_id, $data, $meta ) {
		$item_type          = get_post_meta( $post_id, '_menu_item_type', true );
		$original_object_id = get_post_meta( $post_id, '_menu_item_object_id', true );
		$object_id          = null;

		$this->logger->debug( sprintf( 'Processing menu item %s', $item_type ) );

		$requires_remapping = false;
		switch ( $item_type ) {
			case 'taxonomy':
				if ( isset( $this->mapping['term_id'][ $original_object_id ] ) ) {
					$object_id = $this->mapping['term_id'][ $original_object_id ];
				} else {
					add_post_meta( $post_id, '_wxr_import_menu_item', wp_slash( $original_object_id ) );
					$requires_remapping = true;
				}
				break;

			case 'post_type':
				if ( isset( $this->mapping['post'][ $original_object_id ] ) ) {
					$object_id = $this->mapping['post'][ $original_object_id ];
				} else {
					add_post_meta( $post_id, '_wxr_import_menu_item', wp_slash( $original_object_id ) );
					$requires_remapping = true;
				}
				break;

			case 'custom':
				// Custom refers to itself, wonderfully easy.
				$object_id = $post_id;
				break;

			default:
				// associated object is missing or not imported yet, we'll retry later
				$this->missing_menu_items[] = $item;
				$this->logger->debug( 'Unknown menu item type' );
				break;
		}

		if ( $requires_remapping ) {
			$this->requires_remapping['post'][ $post_id ] = true;
		}

		if ( empty( $object_id ) ) {
			// Nothing needed here.
			return;
		}

		$this->logger->debug( sprintf( 'Menu item %d mapped to %d', $original_object_id, $object_id ) );
		update_post_meta( $post_id, '_menu_item_object_id', wp_slash( $object_id ) );
	}

	/**
	 * If fetching attachments is enabled then attempt to create a new attachment
	 *
	 * @param array $post Attachment post details from WXR
	 * @param string $url URL to fetch attachment from
	 * @return int|WP_Error Post ID on success, WP_Error otherwise
	 */
	protected function process_attachment( $post, $meta, $remote_url ) {
		// try to use _wp_attached file for upload folder placement to ensure the same location as the export site
		// e.g. location is 2003/05/image.jpg but the attachment post_date is 2010/09, see media_handle_upload()
		$post['upload_date'] = $post['post_date'];
		foreach ( $meta as $meta_item ) {
			if ( $meta_item['key'] !== '_wp_attached_file' ) {
				continue;
			}

			if ( preg_match( '%^[0-9]{4}/[0-9]{2}%', $meta_item['value'], $matches ) ) {
				$post['upload_date'] = $matches[0];
			}
			break;
		}

		// if the URL is absolute, but does not contain address, then upload it assuming base_site_url
		if ( preg_match( '|^/[\w\W]+$|', $remote_url ) ) {
			$remote_url = rtrim( $this->base_url, '/' ) . $remote_url;
		}

		$upload = $this->fetch_remote_file( $remote_url, $post );
		if ( is_wp_error( $upload ) ) {
			return $upload;
		}

		$info = wp_check_filetype( $upload['file'] );
		if ( ! $info ) {
			return new WP_Error( 'attachment_processing_error', __( 'Invalid file type', 'wordpress-importer' ) );
		}

		$post['post_mime_type'] = $info['type'];

		// WP really likes using the GUID for display. Allow updating it.
		// See https://core.trac.wordpress.org/ticket/33386
		if ( $this->options['update_attachment_guids'] ) {
			$post['guid'] = $upload['url'];
		}

		// as per wp-admin/includes/upload.php
		$post_id = wp_insert_attachment( $post, $upload['file'] );
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$attachment_metadata = wp_generate_attachment_metadata( $post_id, $upload['file'] );
		wp_update_attachment_metadata( $post_id, $attachment_metadata );

		// Map this image URL later if we need to
		$this->url_remap[ $remote_url ] = $upload['url'];

		// If we have a HTTPS URL, ensure the HTTP URL gets replaced too
		if ( substr( $remote_url, 0, 8 ) === 'https://' ) {
			$insecure_url                     = 'http' . substr( $remote_url, 5 );
			$this->url_remap[ $insecure_url ] = $upload['url'];
		}

		return $post_id;
	}

	/**
	 * Import attachments.
	 * @TODO: Explore other interfaces for attachment import.
	 */
	public function import_attachment( $filepath, $post_id ) {
		$filename = basename( $filepath );
		// Check if attachment with this guid already exists
		$existing_attachment = get_posts(
			array(
				'post_type' => 'attachment',
				'posts_per_page' => 1,
				'guid' => $filepath,
				'fields' => 'ids',
			)
		);

		if ( empty( $existing_attachment ) ) {
			$filetype   = wp_check_filetype( $filename );
			$attachment = array(
				'guid' => $filepath,
				'post_mime_type' => $filetype['type'],
				'post_title' => preg_replace( '/\.[^.]+$/', '', $filename ),
				'post_content' => '',
				'post_status' => 'inherit',
			);
			$attach_id  = wp_insert_attachment( $attachment, $filepath, $post_id );
		} else {
			$attach_id = $existing_attachment[0];
		}
		// @TODO: Check for attachment creation errors
		// @TODO: Make it work with Asyncify
		// Generate and update attachment metadata
		// if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
		//     include( ABSPATH . 'wp-admin/includes/image.php' );
		// }
		// $attach_data = wp_generate_attachment_metadata($attach_id, $filepath);
		// wp_update_attachment_metadata($attach_id, $attach_data);
		return $attach_id;
	}

	/**
	 * Process and import post meta items.
	 *
	 * @param array $meta List of meta data arrays
	 * @param int $post_id Post to associate with
	 * @param array $post Post data
	 * @return int|WP_Error Number of meta items imported on success, error otherwise.
	 */
	public function import_post_meta( $meta_item, $post_id ) {
		if ( empty( $meta_item ) ) {
			return true;
		}

		/**
		 * Pre-process post meta data.
		 *
		 * @param array $meta_item Meta data. (Return empty to skip.)
		 * @param int $post_id Post the meta is attached to.
		 */
		$meta_item = apply_filters( 'wxr_importer_pre_process_post_meta', $meta_item, $post_id );
		$meta_item = $this->topological_sorter->get_mapped_entity( 'post_meta', $meta_item, $post_id );
		if ( empty( $meta_item ) ) {
			return false;
		}

		$key   = apply_filters( 'import_post_meta_key', $meta_item['meta_key'], $post_id );
		$value = false;

		if ( '_edit_last' === $key ) {
			$value = intval( $value );
			if ( ! isset( $this->mapping['user'][ $meta_item['meta_value'] ] ) ) {
				// Skip!
				_doing_it_wrong( __METHOD__, 'User ID not found in mapping', '4.7' );
				return false;
			}

			$value = $this->mapping['user'][ $value ];
		}

		if ( $key ) {
			// export gets meta straight from the DB so could have a serialized string
			if ( ! $value ) {
				$value = maybe_unserialize( $meta_item['meta_value'] );
			}

			add_post_meta( $post_id, wp_slash( $key ), wp_slash_strings_only( $value ) );
			do_action( 'import_post_meta', $post_id, $key, $value );

			// if the post has a featured image, take note of this in case of remap
			if ( '_thumbnail_id' === $key ) {
				$this->featured_images[ $post_id ] = (int) $value;
			}
		}

		do_action( 'wxr_importer_processed_post_meta', $post_id, $meta_item );
		$this->topological_sorter->map_entity( 'post_meta', $meta_item, $key );

		return true;
	}

	/**
	 * Process and import comment data.
	 *
	 * @param array $comments List of comment data arrays.
	 * @param int $post_id Post to associate with.
	 * @param array $post Post data.
	 * @return int|WP_Error Number of comments imported on success, error otherwise.
	 */
	public function import_comment( $comment, $post_id, $post_just_imported = false ) {

		$comments = apply_filters( 'wp_import_post_comments', $comment, $post_id );
		if ( empty( $comments ) ) {
			return 0;
		}

		$num_comments = 0;

		// Sort by ID to avoid excessive remapping later
		usort( $comments, array( $this, 'sort_comments_by_id' ) );
		$parent_id = isset( $comment['comment_parent'] ) ? (int) $comment['comment_parent'] : null;

		/**
		 * Pre-process comment data
		 *
		 * @param array $comment Comment data. (Return empty to skip.)
		 * @param int $post_id Post the comment is attached to.
		 */
		$comment = apply_filters( 'wxr_importer_pre_process_comment', $comment, $post_id, $parent_id );
		$comment = $this->topological_sorter->get_mapped_entity( 'comment', $comment, $post_id, $parent_id );
		if ( empty( $comment ) ) {
			return false;
		}

		$original_id = isset( $comment['comment_id'] ) ? (int) $comment['comment_id'] : 0;
		$author_id   = isset( $comment['comment_user_id'] ) ? (int) $comment['comment_user_id'] : 0;

		// if this is a new post we can skip the comment_exists() check
		// TODO: Check comment_exists for performance
		if ( ! $post_just_imported ) {
			$existing = $this->comment_exists( $comment );
			if ( $existing ) {

				/**
				 * Comment processing already imported.
				 *
				 * @param array $comment Raw data imported for the comment.
				 */
				do_action( 'wxr_importer_process_already_imported_comment', $comment );

				$this->mapping['comment'][ $original_id ] = $existing;
				return;
			}
		}

		// Map the parent comment, or mark it as one we need to fix
		$requires_remapping = false;
		if ( $parent_id ) {
			if ( isset( $this->mapping['comment'][ $parent_id ] ) ) {
				$comment['comment_parent'] = $this->mapping['comment'][ $parent_id ];
			} else {
				// Prepare for remapping later
				$meta[]             = array(
					'key' => '_wxr_import_parent',
					'value' => $parent_id,
				);
				$requires_remapping = true;

				// Wipe the parent for now
				$comment['comment_parent'] = 0;
			}
		}

		// Map the author, or mark it as one we need to fix
		if ( $author_id ) {
			if ( isset( $this->mapping['user'][ $author_id ] ) ) {
				$comment['user_id'] = $this->mapping['user'][ $author_id ];
			} else {
				// Prepare for remapping later
				$meta[]             = array(
					'key' => '_wxr_import_user',
					'value' => $author_id,
				);
				$requires_remapping = true;

				// Wipe the user for now
				$comment['user_id'] = 0;
			}
		}

		// Run standard core filters
		if ( ! $comment['comment_post_ID'] ) {
			$comment['comment_post_ID'] = $post_id;
		}

		// @TODO: How to handle missing fields? Use sensible defaults? What defaults?
		if ( ! isset( $comment['comment_author_IP'] ) ) {
			$comment['comment_author_IP'] = '';
		}
		if ( ! isset( $comment['comment_author_url'] ) ) {
			$comment['comment_author_url'] = '';
		}
		if ( ! isset( $comment['comment_author_email'] ) ) {
			$comment['comment_author_email'] = '';
		}
		if ( ! isset( $comment['comment_date'] ) ) {
			$comment['comment_date'] = gmdate( 'Y-m-d H:i:s' );
		}

		$comment = wp_filter_comment( $comment );
		// wp_insert_comment expects slashed data
		$comment_id                               = wp_insert_comment( wp_slash( $comment ) );
		$this->mapping['comment'][ $original_id ] = $comment_id;
		if ( $requires_remapping ) {
			$this->requires_remapping['comment'][ $comment_id ] = true;
		}
		$this->mark_comment_exists( $comment, $comment_id );

		/**
		 * Comment has been imported.
		 *
		 * @param int $comment_id New comment ID
		 * @param array $comment Comment inserted (`comment_id` item refers to the original ID)
		 * @param int $post_id Post parent of the comment
		 * @param array $post Post data
		 */
		do_action( 'wp_import_insert_comment', $comment_id, $comment, $post_id );

		/**
		 * Post processing completed.
		 *
		 * @param int $comment_id New comment ID.
		 * @param array $comment Raw data imported for the comment.
		 * @param array $post_id Parent post ID.
		 */
		do_action( 'wxr_importer_processed_comment', $comment_id, $comment, $post_id );
		$this->topological_sorter->map_entity( 'comment', $comment, $comment_id, $post_id );
	}

	public function import_comment_meta( $meta_item, $comment_id ) {
		$meta_item = apply_filters( 'wxr_importer_pre_process_comment_meta', $meta_item, $comment_id );
		$meta_item = $this->topological_sorter->get_mapped_entity( 'comment_meta', $meta_item, $comment_id );
		if ( empty( $meta_item ) ) {
			return false;
		}

		if ( ! isset( $meta_item['comment_id'] ) ) {
			$meta_item['comment_id'] = $comment_id;
		}

		// @TODO: Check if wp_slash is correct and not wp_slash_strings_only
		$value           = maybe_unserialize( $meta_item['meta_value'] );
		$comment_meta_id = add_comment_meta( $meta_item['comment_id'], wp_slash( $meta_item['meta_key'] ), wp_slash( $value ) );

		do_action( 'wxr_importer_processed_comment_meta', $comment_meta_id, $meta_item, $meta_item['comment_id'] );
		$this->topological_sorter->map_entity( 'comment_meta', $meta_item, $comment_meta_id, $meta_item['comment_id'] );
	}

	/**
	 * Mark the post as existing.
	 *
	 * @param array $data Post data to mark as existing.
	 * @param int $post_id Post ID.
	 */
	protected function mark_post_exists( $data, $post_id ) {
		$exists_key                          = $data['guid'] ?? false;
		$this->exists['post'][ $exists_key ] = $post_id;
	}

	/**
	 * Prefill existing comment data.
	 *
	 * @see self::prefill_existing_posts() for justification of why this exists.
	 */
	protected function prefill_existing_comments() {
		global $wpdb;
		$posts = $wpdb->get_results( "SELECT comment_ID, comment_author, comment_date FROM {$wpdb->comments}" );

		foreach ( $posts as $item ) {
			$exists_key                             = sha1( $item->comment_author . ':' . $item->comment_date );
			$this->exists['comment'][ $exists_key ] = $item->comment_ID;
		}
	}

	/**
	 * Does the comment exist?
	 *
	 * @param array $data Comment data to check against.
	 * @return int|bool Existing comment ID if it exists, false otherwise.
	 */
	protected function comment_exists( $data ) {
		$comment_date = $data['comment_date'] ?? gmdate( 'Y-m-d H:i:s' );
		$exists_key   = sha1( $data['comment_author'] . ':' . $comment_date );

		// Constant-time lookup if we prefilled
		if ( $this->options['prefill_existing_comments'] ) {
			return isset( $this->exists['comment'][ $exists_key ] ) ? $this->exists['comment'][ $exists_key ] : false;
		}

		// No prefilling, but might have already handled it
		if ( isset( $this->exists['comment'][ $exists_key ] ) ) {
			return $this->exists['comment'][ $exists_key ];
		}

		// Still nothing, try comment_exists, and cache it
		$exists                                 = comment_exists( $data['comment_author'], $comment_date );
		$this->exists['comment'][ $exists_key ] = $exists;

		return $exists;
	}

	/**
	 * Mark the comment as existing.
	 *
	 * @param array $data Comment data to mark as existing.
	 * @param int $comment_id Comment ID.
	 */
	protected function mark_comment_exists( $data, $comment_id ) {
		$exists_key                             = sha1( $data['comment_author'] . ':' . $data['comment_date'] );
		$this->exists['comment'][ $exists_key ] = $comment_id;
	}

	/**
	 * Prefill existing term data.
	 *
	 * @see self::prefill_existing_posts() for justification of why this exists.
	 */
	protected function prefill_existing_terms() {
		global $wpdb;
		$query  = "SELECT t_term_id, tt.taxonomy, t.slug FROM {$wpdb->terms} AS t";
		$query .= " JOIN {$wpdb->term_taxonomy} AS tt ON t_term_id = tt_term_id";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$terms = $wpdb->get_results( $query );

		foreach ( $terms as $item ) {
			$exists_key                          = sha1( $item->taxonomy . ':' . $item->slug );
			$this->exists['term'][ $exists_key ] = $item->term_id;
		}
	}

	/**
	 * Does the term exist?
	 *
	 * @param array $data Term data to check against.
	 * @return int|bool Existing term ID if it exists, false otherwise.
	 */
	protected function term_exists( $data ) {
		$exists_key = sha1( $data['taxonomy'] . ':' . $data['slug'] );

		// Constant-time lookup if we prefilled
		if ( $this->options['prefill_existing_terms'] ) {
			return isset( $this->exists['term'][ $exists_key ] ) ? $this->exists['term'][ $exists_key ] : false;
		}

		// No prefilling, but might have already handled it
		if ( isset( $this->exists['term'][ $exists_key ] ) ) {
			return $this->exists['term'][ $exists_key ];
		}

		// Still nothing, try comment_exists, and cache it
		$exists = term_exists( $data['slug'], $data['taxonomy'] );
		if ( is_array( $exists ) ) {
			$exists = $exists['term_id'];
		}

		$this->exists['term'][ $exists_key ] = $exists;

		return $exists;
	}

	/**
	 * Mark the term as existing.
	 *
	 * @param array $data Term data to mark as existing.
	 * @param int $term_id Term ID.
	 */
	protected function mark_term_exists( $data, $term_id ) {
		$exists_key                          = sha1( $data['taxonomy'] . ':' . $data['slug'] );
		$this->exists['term'][ $exists_key ] = $term_id;
	}

	/**
	 * Callback for `usort` to sort comments by ID
	 *
	 * @param array $a Comment data for the first comment
	 * @param array $b Comment data for the second comment
	 * @return int
	 */
	public static function sort_comments_by_id( $a, $b ) {
		if ( empty( $a['comment_id'] ) ) {
			return 1;
		}

		if ( empty( $b['comment_id'] ) ) {
			return -1;
		}

		return $a['comment_id'] - $b['comment_id'];
	}
}
