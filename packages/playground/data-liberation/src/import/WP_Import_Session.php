<?php

/**
 * Manages import session data in the WordPress database.
 *
 * Each import session is stored as a post of type 'import_session'.
 * Progress, stage, and other metadata are stored as post meta.
 */
class WP_Import_Session {
	const POST_TYPE = 'import_session';
	/**
	 * @TODO: Make it extendable
	 * @TODO: Reuse the same entities list as WP_Stream_Importer
	 */
	const PROGRESS_ENTITIES = array(
		'site_option',
		'user',
		'category',
		'tag',
		'term',
		'post',
		'post_meta',
		'comment',
		'comment_meta',
		'download',
	);
	const FRONTLOAD_STATUS_AWAITING_DOWNLOAD = 'awaiting_download';
	const FRONTLOAD_STATUS_ERROR = 'error';
	const FRONTLOAD_STATUS_SUCCEEDED = 'succeeded';
	private $post_id;
	private $cached_stage;

	/**
	 * Creates a new import session.
	 *
	 * @param array $args {
	 *     @type string $data_source     The data source (e.g. 'wxr_file', 'wxr_url', 'markdown_zip')
	 *     @type string $source_url      Optional. URL of the source file for remote imports
	 *     @type int    $attachment_id   Optional. ID of the uploaded file attachment
	 *     @type string $file_name       Optional. Original name of the uploaded file
	 * }
	 * @return WP_Import_Model|WP_Error The import model instance or error if creation failed
	 */
	public static function create( $args ) {
		// Validate the required arguments for each data source.
		// @TODO: Leave it up to filters to make it extendable.
		switch ( $args['data_source'] ) {
			case 'wxr_file':
				if ( empty( $args['file_name'] ) ) {
					_doing_it_wrong(
						__METHOD__,
						'File name is required for WXR file imports',
						'1.0.0'
					);
					return false;
				}
				break;
			case 'wxr_url':
				if ( empty( $args['source_url'] ) ) {
					_doing_it_wrong(
						__METHOD__,
						'Source URL is required for remote imports',
						'1.0.0'
					);
					return false;
				}
				break;
			case 'markdown_zip':
				if ( empty( $args['file_name'] ) ) {
					_doing_it_wrong(
						__METHOD__,
						'File name is required for Markdown ZIP imports',
						'1.0.0'
					);
					return false;
				}
				break;
		}

		$post_id = wp_insert_post(
			array(
				'post_type' => self::POST_TYPE,
				'post_status' => 'publish',
				'post_title' => sprintf(
					'Import from %s - %s',
					$args['data_source'],
					$args['file_name'] ?? $args['source_url'] ?? 'Unknown source'
				),
				'meta_input' => array(
					'data_source' => $args['data_source'],
					'started_at' => time(),
					'file_name' => $args['file_name'] ?? null,
					'source_url' => $args['source_url'] ?? null,
					'attachment_id' => $args['attachment_id'] ?? null,
				),
			),
			true
		);
		if ( is_wp_error( $post_id ) ) {
			_doing_it_wrong(
				__METHOD__,
				'Error creating an import session: ' . $post_id->get_error_message(),
				'1.0.0'
			);
			return false;
		}

		if ( ! empty( $args['attachment_id'] ) ) {
			wp_update_post(
				array(
					'ID' => $post_id,
					'post_parent' => $args['attachment_id'],
				)
			);
		}

		return new self( $post_id );
	}

	/**
	 * Gets an existing import session by ID.
	 *
	 * @param int $post_id The import session post ID
	 * @return WP_Import_Model|null The import model instance or null if not found
	 */
	public static function by_id( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post || $post->post_type !== self::POST_TYPE ) {
			return false;
		}
		return new self( $post_id );
	}

	/**
	 * Gets the most recent active import session.
	 *
	 * @return WP_Import_Session|null The most recent import or null if none found
	 */
	public static function get_active() {
		$posts = get_posts(
			array(
				'post_type' => self::POST_TYPE,
				'post_status' => array( 'publish' ),
				'posts_per_page' => 1,
				'orderby' => 'date',
				'order' => 'DESC',
				'meta_query' => array(
					// @TODO: This somehow makes $post empty.
					// array(
					//     'key' => 'current_stage',
					//     'value' => WP_Stream_Importer::STAGE_FINISHED,
					//     'compare' => '!='
					// )
				),
			)
		);

		if ( empty( $posts ) ) {
			return false;
		}

		return new self( $posts[0]->ID );
	}

	public function __construct( $post_id ) {
		$this->post_id = $post_id;
	}

	/**
	 * Gets the import session ID.
	 *
	 * @return int The post ID
	 */
	public function get_id() {
		return $this->post_id;
	}

	public function get_metadata() {
		$cursor = $this->get_reentrancy_cursor();
		return array(
			'cursor' => $cursor ? $cursor : null,
			'data_source' => get_post_meta( $this->post_id, 'data_source', true ),
			'source_url' => get_post_meta( $this->post_id, 'source_url', true ),
			'attachment_id' => get_post_meta( $this->post_id, 'attachment_id', true ),
		);
	}

	public function get_data_source() {
		return get_post_meta( $this->post_id, 'data_source', true );
	}

	public function get_human_readable_file_reference() {
		switch ( $this->get_data_source() ) {
			case 'wxr_file':
			case 'markdown_zip':
				return get_post_meta( $this->post_id, 'file_name', true );
			case 'wxr_url':
				return get_post_meta( $this->post_id, 'source_url', true );
		}
		return '';
	}

	public function archive() {
		wp_update_post(
			array(
				'ID' => $this->post_id,
				'post_status' => 'archived',
			)
		);
	}

	/**
	 * Gets the current progress information.
	 *
	 * @return array The progress data
	 */
	public function count_imported_entities() {
		$progress = array();
		foreach ( self::PROGRESS_ENTITIES as $entity ) {
			$progress[ $entity ] = (int) get_post_meta( $this->post_id, 'imported_' . $entity, true );
		}
		return $progress;
	}
	/**
	 * Cache of imported entity counts to avoid repeated database queries
	 * @var array
	 */
	private $cached_imported_counts = array();

	/**
	 * Updates the progress information.
	 *
	 * @param array $newly_imported_entities The new progress data with keys: posts, comments, terms, attachments, users
	 */
	public function bump_imported_entities_counts( $newly_imported_entities ) {
		foreach ( $newly_imported_entities as $field => $count ) {
			if ( ! in_array( $field, static::PROGRESS_ENTITIES, true ) ) {
				_doing_it_wrong(
					__METHOD__,
					'Cannot bump imported entities count for unknown entity type: ' . $field,
					'1.0.0'
				);
				continue;
			}

			// Get current count from cache or database
			if ( ! isset( $this->cached_imported_counts[ $field ] ) ) {
				$this->cached_imported_counts[ $field ] = (int) get_post_meta( $this->post_id, 'imported_' . $field, true );
			}

			// Add new count to total
			$new_count = $this->cached_imported_counts[ $field ] + $count;

			// Update database and cache
			update_post_meta( $this->post_id, 'imported_' . $field, $new_count );
			$this->cached_imported_counts[ $field ] = $new_count;
			/*
			@TODO run an atomic query instead:
			$sql = $wpdb->prepare(
				"INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value)
				VALUES (%d, %s, %d)
				ON DUPLICATE KEY UPDATE meta_value = meta_value + %d",
				$this->post_id,
				'imported_' . $field,
				$count,
				$count
			);
			$wpdb->query($sql);
			*/
		}
	}

	public function set_frontloading_status( $url, $status, $error = null ) {
		// @TODO: What if the placeholder is not found?
		$placeholder = $this->get_frontloading_placeholder( $url );
		if ( ! $placeholder ) {
			return false;
		}
		return wp_update_post(array(
			'ID' => $placeholder->ID,
			'post_status' => $status,
			// Abuse the menu_order field to store the number of retries.
			// This avoids additional database queries.
			'menu_order' => $placeholder->menu_order + 1,
			'post_content' => $error,
		));
	}

	public function get_frontloading_placeholders( $options = array() ) {
		$query = new WP_Query(array(
			'post_type' => 'frontloading_placeholder',
			'post_parent' => $this->post_id,
			'posts_per_page' => $options['per_page'] ?? 25,
			'paged' => $options['page'] ?? 1,
			'post_status' => self::FRONTLOAD_STATUS_ERROR,
			'orderby' => 'post_status',
			'order' => 'ASC',
		));

		if (!$query->have_posts()) {
			return array();
		}

		return $query->posts;
	}

	public function get_total_number_of_entities() {
		$totals = array();
		foreach ( static::PROGRESS_ENTITIES as $field ) {
			$totals[ $field ] = (int) get_post_meta( $this->post_id, 'total_' . $field, true );
		}
		$totals['download'] = $this->get_total_number_of_assets();
		return $totals;
	}

	public function get_total_number_of_assets() {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM $wpdb->posts 
			WHERE post_type = 'frontloading_placeholder' 
			AND post_parent = %d",
			$this->post_id
		) );
	}

	public function get_frontloading_placeholder( $url ) {
		global $wpdb;
		return get_post( $wpdb->get_var( $wpdb->prepare(
			"SELECT ID FROM $wpdb->posts WHERE post_type = 'frontloading_placeholder' AND post_parent = %d AND guid = %s LIMIT 1",
			$this->post_id, $url
		) ) );
	}

	/**
	 * Creates placeholder attachments for the assets to be downloaded in the
	 * frontloading stage.
	 */
	public function store_indexed_assets_urls( $urls ) {
		global $wpdb;

		foreach ( $urls as $url => $_ ) {
			/**
			 * Check if placeholder with this URL already exists
			 * There's a race condition here – another insert may happen
			 * between the check and the insert.
			 * @TODO: Explore solutions. A custom table with a UNIQUE constraint
			 * may or may not be an option, depending on the performance impact
			 * on 100GB+ VIP databases.
			 */
			$exists = $wpdb->get_var( $wpdb->prepare(
				"SELECT ID FROM $wpdb->posts 
				WHERE post_type = 'frontloading_placeholder' 
				AND post_parent = %d
				AND guid = %s
				LIMIT 1",
				$this->post_id,
				$url
			));

			if ( $exists ) {
				continue;
			}

			$post_data = array(
				'post_type' => 'frontloading_placeholder',
				'post_parent' => $this->post_id,
				'post_title' => basename( $url ),
				'post_status' => self::FRONTLOAD_STATUS_AWAITING_DOWNLOAD,
				'guid' => $url
			);
			if ( is_wp_error( wp_insert_post( $post_data ) ) ) {
				// @TODO: How to handle an insertion failure?
				return false;
			}
		}
	}

	/**
	 * Sets the total number of entities to import for each type.
	 *
	 * @param array $totals The total number of entities for each type
	 */
	private $cached_totals = array();

	public function bump_total_number_of_entities( $newly_indexed_entities ) {
		foreach ( $newly_indexed_entities as $field => $count ) {
			if ( ! in_array( $field, static::PROGRESS_ENTITIES, true ) ) {
				_doing_it_wrong(
					__METHOD__,
					'Cannot set total number of entities for unknown entity type: ' . $field,
					'1.0.0'
				);
				continue;
			}

			// Get current total from cache or database
			if ( ! isset( $this->cached_totals[ $field ] ) ) {
				$this->cached_totals[ $field ] = (int) get_post_meta( $this->post_id, 'total_' . $field, true );
			}

			// Add new count to total
			$new_total = $this->cached_totals[ $field ] + $count;

			// Update database and cache
			update_post_meta( $this->post_id, 'total_' . $field, $new_total );
			$this->cached_totals[ $field ] = $new_total;
		}
	}

	/**
	 * Saves an array of [$url => ['received' => $downloaded_bytes, 'total' => $total_bytes | null]]
	 * of the currently fetched files. The list is ephemeral and changes as we stream the data. There
	 * will never be more than $concurrency_limit files in the list at any given time.
	 */
	public function bump_frontloading_progress( $frontloading_progress, $events = array() ) {
		update_post_meta( $this->post_id, 'frontloading_progress', $frontloading_progress );

		$successes = 0;
		foreach ( $events as $event ) {
			switch ( $event->type ) {
				case WP_Attachment_Downloader_Event::SUCCESS:
					++$successes;
					$this->set_frontloading_status($event->resource_id, self::FRONTLOAD_STATUS_SUCCEEDED);
					break;
				case WP_Attachment_Downloader_Event::FAILURE:
					$this->set_frontloading_status($event->resource_id, self::FRONTLOAD_STATUS_ERROR, $event->error);
					break;
			}
		}
		if ( $successes > 0 ) {
			// @TODO: Consider not treating files as a special case.
			$this->bump_imported_entities_counts(
				array(
					'download' => $successes,
				)
			);
		}
	}

	public function get_frontloading_progress() {
		return get_post_meta( $this->post_id, 'frontloading_progress', true ) ?? array();
	}

	public function is_stage_completed( $stage ) {
		$current_stage       = $this->get_stage();
		$stage_index         = array_search( $stage, WP_Stream_Importer::STAGES_IN_ORDER, true );
		$current_stage_index = array_search( $current_stage, WP_Stream_Importer::STAGES_IN_ORDER, true );
		return $current_stage_index > $stage_index;
	}

	/**
	 * Gets the current import stage.
	 *
	 * @return string The current stage
	 */
	public function get_stage() {
		if ( ! isset( $this->cached_stage ) ) {
			$meta               = get_post_meta( $this->post_id, 'current_stage', true );
			$this->cached_stage = $meta ? $meta : WP_Stream_Importer::STAGE_INITIAL;
		}
		return $this->cached_stage;
	}

	/**
	 * Updates the current import stage.
	 *
	 * @param string $stage The new stage
	 */
	public function set_stage( $stage ) {
		if ( $stage === $this->get_stage() ) {
			return;
		}
		if ( WP_Stream_Importer::STAGE_FINISHED === $stage ) {
			update_post_meta( $this->post_id, 'finished_at', time() );
		}
		update_post_meta( $this->post_id, 'current_stage', $stage );
		$this->cached_stage = $stage;
	}

	public function get_started_at() {
		return get_post_meta( $this->post_id, 'started_at', true );
	}

	public function get_finished_at() {
		return get_post_meta( $this->post_id, 'finished_at', true );
	}

	public function is_finished() {
		return ! empty( get_post_meta( $this->post_id, 'finished_at', true ) );
	}

	/**
	 * Gets the importer cursor for resuming imports.
	 *
	 * @return string|null The cursor data
	 */
	public function get_reentrancy_cursor() {
		return get_post_meta( $this->post_id, 'importer_cursor', true );
	}

	/**
	 * Updates the importer cursor.
	 *
	 * @param string $cursor The new cursor data
	 */
	public function set_reentrancy_cursor( $cursor ) {
		// WordPress, sadly, removes single slashes from the meta value and
		// requires an addslashes() call to preserve them.
		update_post_meta( $this->post_id, 'importer_cursor', addslashes( $cursor ) );
	}
}
