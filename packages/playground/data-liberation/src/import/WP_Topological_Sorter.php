<?php

/**
 * The topological sorter class.
 *
 * We create a custom table that contains the WXR IDs and the mapped IDs.
 */
class WP_Topological_Sorter {

	/**
	 * The base name of the table.
	 */
	const TABLE_NAME = 'data_liberation_map';

	/**
	 * The option name for the database version.
	 */
	const OPTION_NAME = 'data_liberation_db_version';

	/**
	 * The current database version, to be used with dbDelta.
	 */
	const DB_VERSION = 1;

	/**
	 * The current session ID.
	 */
	protected $current_session = null;

	/**
	 * The current item being processed.
	 */
	protected $current_item = 0;

	const ENTITY_TYPES = array(
		'comment'      => 1,
		'comment_meta' => 2,
		'post'         => 3,
		'post_meta'    => 4,
		'term'         => 5,
	);

	private $mapped_pre_filters = array(
		// Name of the filter, and the number of arguments it accepts.
		'wxr_importer_pre_process_comment' => 2,
		'wxr_importer_pre_process_comment_meta' => 2,
		'wxr_importer_pre_process_post' => 2,
		'wxr_importer_pre_process_post_meta' => 2,
		'wxr_importer_pre_process_term' => 1,
	);

	private $mapped_post_actions = array(
		// Name of the filter, and the number of arguments it accepts.
		'wxr_importer_processed_comment' => 3,
		'wxr_importer_processed_comment_meta' => 3,
		'wxr_importer_processed_post' => 2,
		'wxr_importer_processed_post_meta' => 2,
		'wxr_importer_processed_term' => 2,
	);

	/**
	 * Set the current session ID and add the filters and actions.
	 */
	public function __construct( $options = array() ) {
		if ( array_key_exists( 'session_id', $options ) ) {
			$this->current_session = $options['session_id'];
		}

		// The topological sorter needs to know about the mapped IDs for comments, terms, and posts.
		foreach ( $this->mapped_pre_filters as $name => $accepted_args ) {
			add_filter( $name, array( $this, 'filter_wxr_importer_pre_process' ), 10, $accepted_args );
		}

		foreach ( $this->mapped_post_actions as $name => $accepted_args ) {
			add_action( $name, array( $this, 'action_wxr_importer_processed' ), 10, $accepted_args );
		}
	}

	/**
	 * Remove the filters.
	 */
	public function __destruct() {
		foreach ( $this->mapped_pre_filters as $name => $accepted_args ) {
			remove_filter( $name, array( $this, 'filter_wxr_importer_pre_process' ) );
		}

		foreach ( $this->mapped_post_actions as $name => $accepted_args ) {
			remove_action( $name, array( $this, 'action_wxr_importer_processed' ) );
		}
	}

	/**
	 * Get the name of the table.
	 *
	 * @return string The name of the table.
	 */
	public static function get_table_name() {
		global $wpdb;

		// Default is wp_{TABLE_NAME}
		return $wpdb->prefix . self::TABLE_NAME;
	}

	/**
	 * Run by register_activation_hook. It creates the table if it doesn't exist.
	 */
	public static function activate() {
		global $wpdb;

		// See wp_get_db_schema
		$max_index_length = 191;

		// Create the table if it doesn't exist.
		$sql = $wpdb->prepare(
			'CREATE TABLE IF NOT EXISTS %i (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				session_id bigint(20) unsigned NOT NULL,
				element_type tinyint(1) NOT NULL,
				element_id text NOT NULL,
				mapped_id text DEFAULT NULL,
				parent_id text DEFAULT NULL,
				byte_offset bigint(20) unsigned NOT NULL,
				sort_order int DEFAULT 1,
				PRIMARY KEY  (id),
				KEY session_id (session_id),
				KEY element_id (element_id(%d)),
				KEY parent_id (parent_id(%d)),
				KEY byte_offset (byte_offset)
			) ' . $wpdb->get_charset_collate(),
			self::get_table_name(),
			$max_index_length,
			$max_index_length
		);

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Run by register_deactivation_hook.
	 */
	public static function deactivate() {
		global $wpdb;
		$table_name = self::get_table_name();

		// Drop the table.
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %s', $table_name ) );

		// Delete the option.
		delete_option( self::OPTION_NAME );
	}

	/**
	 * Run by register_uninstall_hook.
	 */
	public function reset() {
		$this->current_session = null;
	}

	/**
	 * Delete all rows for a given session ID.
	 *
	 * @param int $session_id The session ID to delete rows for.
	 * @return int|false The number of rows deleted, or false on error.
	 */
	public function delete_session( $session_id ) {
		global $wpdb;

		return $wpdb->delete(
			self::get_table_name(),
			array( 'session_id' => $session_id ),
			array( '%d' )
		);
	}

	/**
	 * Called by 'wxr_importer_pre_process_*' filters. This populates the entity
	 * object with the mapped IDs.
	 *
	 * @param array $data The data to map.
	 * @param int|null $id The ID of the element.
	 * @param int|null $additional_id The additional ID of the element.
	 */
	public function filter_wxr_importer_pre_process( $data, $id = null, $additional_id = null ) {
		$current_session = $this->current_session;
		$current_filter  = current_filter();
		$types           = array(
			'wxr_importer_pre_process_comment'      => 'comment',
			'wxr_importer_pre_process_comment_meta' => 'comment_meta',
			'wxr_importer_pre_process_post'         => 'post',
			'wxr_importer_pre_process_post_meta'    => 'post_meta',
			'wxr_importer_pre_process_term'         => 'term',
		);

		if ( ! $current_filter || ! array_key_exists( $current_filter, $types ) ) {
			_doing_it_wrong(
				__METHOD__,
				'This method should be called by the wxr_importer_pre_process_* filters.',
				'1.0.0'
			);

			return false;
		}

		return $this->get_mapped_element( $types[ $current_filter ], $data, $id, $additional_id );
	}

	/**
	 * Called by 'wxr_importer_processed_*' actions. This adds the entity to the
	 * sorter table.
	 *
	 * @param int|null $id The ID of the element.
	 * @param array $data The data to map.
	 * @param int|null $additional_id The additional ID of the element.
	 */
	public function action_wxr_importer_processed( $id, $data, $additional_id = null ) {
		$current_filter = current_action();
		$types          = array(
			'wxr_importer_processed_comment'      => 'comment',
			'wxr_importer_processed_comment_meta' => 'comment_meta',
			'wxr_importer_processed_post'         => 'post',
			'wxr_importer_processed_post_meta'    => 'post_meta',
			'wxr_importer_processed_term'         => 'term',
		);

		if ( ! $current_filter || ! array_key_exists( $current_filter, $types ) ) {
			_doing_it_wrong(
				__METHOD__,
				'This method should be called by the wxr_importer_processed_* filters.',
				'1.0.0'
			);

			return false;
		}

		$this->map_element( $types[ $current_filter ], $data, $id, $additional_id );
	}

	/**
	 * Map an element to the index. If $id is provided, it will be used to map the element.
	 *
	 * @param string   $element_type The type of the element.
	 * @param array    $data The data to map.
	 * @param int|null $id The ID of the element.
	 * @param int|null $additional_id The additional ID of the element.
	 */
	public function map_element( $element_type, $data, $id = null, $additional_id = null ) {
		global $wpdb;

		if ( ! array_key_exists( $element_type, self::ENTITY_TYPES ) ) {
			return;
		}

		$new_element = array(
			'session_id'   => $this->current_session,
			'element_type' => self::ENTITY_TYPES[ $element_type ],
			'element_id'   => null,
			'mapped_id'    => is_null( $id ) ? null : (string) $id,
			'parent_id'    => null,
			'byte_offset'  => 0,
			// Items with a parent has at least a sort order of 2.
			'sort_order'   => 1,
		);
		$element_id = null;

		switch ( $element_type ) {
			case 'comment':
				$element_id = (string) $data['comment_id'];
				break;
			case 'comment_meta':
				$element_id = (string) $data['meta_key'];

				if ( array_key_exists( 'comment_id', $data ) ) {
					$new_element['parent_id'] = $data['comment_id'];
				}
				break;
			case 'post':
				if ( 'post' === $data['post_type'] || 'page' === $data['post_type'] ) {
					if ( array_key_exists( 'post_parent', $data ) && '0' !== $data['post_parent'] ) {
						$new_element['parent_id'] = $data['post_parent'];
					}
				}

				$element_id = (string) $data['post_id'];
				break;
			case 'post_meta':
				break;
			case 'term':
				$element_id = (string) $data['term_id'];

				if ( array_key_exists( 'parent', $data ) ) {
					$new_element['parent_id'] = $data['parent'];
				}
				break;
		}

		// The element has been imported, so we can use the ID.
		if ( $id ) {
			$existing_element = $this->get_mapped_ids( $element_id, self::ENTITY_TYPES[ $element_type ] );

			if ( $existing_element && is_null( $existing_element['mapped_id'] ) ) {
				$new_element['mapped_id'] = (string) $id;

				// Update the element if it already exists.
				$wpdb->update(
					self::get_table_name(),
					array( 'mapped_id' => (string) $id ),
					array(
						'element_id'   => (string) $element_id,
						'element_type' => self::ENTITY_TYPES[ $element_type ],
					),
					array( '%s' )
				);
			}
		} else {
			// Insert the element if it doesn't exist.
			$new_element['element_id'] = $element_id;
			$wpdb->insert( self::get_table_name(), $new_element );
		}
	}

	/**
	 * Get a mapped element. Called from 'wxr_importer_pre_process_*' filter.
	 *
	 * @param int $entity The entity to get the mapped ID for.
	 * @param int $id The ID of the element.
	 *
	 * @return mixed|bool The mapped element or false if the post is not found.
	 */
	public function get_mapped_element( $element_type, $element, $id, $additional_id = null ) {
		$current_session = $this->current_session;
		$already_mapped  = false;

		switch ( $element_type ) {
			case 'comment':
				// The ID is the post ID.
				$mapped_ids = $this->get_mapped_ids( $id, self::ENTITY_TYPES['post'] );

				if ( $mapped_ids && ! is_null( $mapped_ids['mapped_id'] ) ) {
					$element['comment_post_ID'] = $mapped_ids['mapped_id'];
				}
				break;
			case 'comment_meta':
				// The ID is the comment ID.
				$mapped_ids = $this->get_mapped_ids( $id, self::ENTITY_TYPES['comment'] );

				if ( $mapped_ids && ! is_null( $mapped_ids['mapped_id'] ) ) {
					$element['comment_id'] = $mapped_ids['mapped_id'];
				}
				break;
			case 'post':
				// The ID is the parent post ID.
				$mapped_ids = $this->get_mapped_ids( $id, self::ENTITY_TYPES['post'] );

				if ( $mapped_ids && ! is_null( $mapped_ids['mapped_id'] ) ) {
					$element['post_parent'] = $mapped_ids['mapped_id'];
				}

				$mapped_ids = $this->get_mapped_ids( $element['post_id'], self::ENTITY_TYPES['post'] );

				if ( $mapped_ids && ! is_null( $mapped_ids['mapped_id'] ) ) {
					$element['post_id'] = $mapped_ids['mapped_id'];
					$already_mapped     = true;
				}
				break;
			case 'post_meta':
				// The ID is the post ID.
				$mapped_ids = $this->get_mapped_ids( $id, self::ENTITY_TYPES['post'] );

				if ( $mapped_ids ) {
					$element['post_id'] = $mapped_ids['mapped_id'];
				}
				break;
			case 'term':
				// Not ID provided.
				break;
		}

		if ( $already_mapped ) {
			// This is used to skip the post if it has already been mapped.
			$element['_already_mapped'] = true;
		}

		return $element;
	}

	/**
	 * Get the mapped ID for an element.
	 *
	 * @param int $id   The ID of the element.
	 * @param int $type The type of the element.
	 *
	 * @return int|false The mapped ID or null if the element is not found.
	 */
	private function get_mapped_ids( $id, $type ) {
		global $wpdb;

		if ( ! $id ) {
			return null;
		}

		$results = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT element_id, mapped_id FROM %i WHERE element_id = %s AND element_type = %d LIMIT 1',
				self::get_table_name(),
				(string) $id,
				$type
			),
			ARRAY_A
		);

		if ( $results && 1 === count( $results ) ) {
			return $results[0];
		}

		return null;
	}
}
