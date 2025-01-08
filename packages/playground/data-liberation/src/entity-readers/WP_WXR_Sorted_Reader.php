<?php

use WordPress\ByteReader\WP_Byte_Reader;

/**
 * Data Liberation API: WP_WXR_Sorted_Reader class
 *
 * The topological sorted WXR reader class. This is an extension of the
 * WP_WXR_Reader class that emits entities sorted topologically so that the
 * parents are always emitted before the children.
 *
 * ## Implementation
 *
 * We create a custom table that contains the IDs and the new IDs created in the
 * target system sorted in the parent-child order.
 *
 * This class extends the WP_WXR_Reader class and overrides the read_next_entity
 *
 * List of entities      Sort order
 * entity 1              entity 1          3
 * entity 2, parent 1    └─ entity 2       2
 * entity 3, parent 2       └─ entity 3    1
 * entity 4, parent 2       └─ entity 4    1
 *
 * List of entities      Sort order
 * entity 4, parent 2    entity 1          3
 * entity 3, parent 2    └─ entity 2       2
 * entity 2, parent 1       └─ entity 3    1
 * entity 1                 └─ entity 4    1
 *
 * List of entities      Sort order
 * entity 1              entity 1          3
 * entity 3, parent 2    └─ entity 2       2
 * entity 2, parent 1       └─ entity 3    1
 *
 * List of entities      Sort order
 * entity 1              entity 1          1
 * entity 2              entity 2          1
 * entity 3              entity 3          1
 *
 * @since WP_VERSION
 */
class WP_WXR_Sorted_Reader extends WP_WXR_Reader {

	/**
	 * The base name of the table used to store the IDs, the new IDs and the
	 * sort order.
	 */
	const TABLE_NAME = 'data_liberation_map';

	/**
	 * The current database version, to be used with dbDelta.
	 */
	const DB_VERSION = 1;

	/**
	 * The current session ID.
	 */
	protected $current_session = null;

	/**
	 * Se to true if the cursors should be read from the map.
	 */
	public $emit_cursor = false;

	/**
	 * The entity types saved in the database.
	 */
	const ENTITY_TYPES = array(
		'category'     => 1,
		'post'         => 2,
		'site_option'  => 3,
		'user'         => 4,
		'term'         => 5,
	);

	/**
	 * The name of the field where the ID is saved.
	 */
	const ENTITY_TYPES_ID = array(
		'category'     => 'slug',
		'post'         => 'post_id',
		'site_option'  => 'option_name',
		'user'         => 'user_login',
		'term'         => 'term_id',
	);

	/**
	 * Create the reader.
	 *
	 * @param WP_Byte_Reader $upstream The upstream reader.
	 * @param mixed         $cursor The cursor.
	 * @param array         $options The options.
	 *
	 * @return WP_WXR_Sorted_Reader The reader.
	 */
	public static function create( WP_Byte_Reader $upstream = null, $cursor = null, $options = array() ) {
		// Initialize WP_WXR_Reader.
		$reader = parent::create( $upstream, $cursor, $options );

		if ( array_key_exists( 'post_id', $options ) ) {
			// Get the session ID from the post ID.
			$reader->current_session = $options['post_id'];
		}

		return $reader;
	}

	/**
	 * Advances to the next entity in the WXR file.
	 *
	 * @since WP_VERSION
	 *
	 * @return bool Whether another entity was found.
	 */
	protected function read_next_entity() {
		if ( ! $this->emit_cursor ) {
			return parent::read_next_entity();
		}

		$next_cursor = $this->get_next_cursor();

		if ( ! empty( $next_cursor ) ) {
			$next_cursor = json_decode( $next_cursor, true );

			/*if ( ! empty( $next_cursor ) ) {
				$this->last_post_id    = $next_cursor['last_post_id'];
				$this->last_comment_id = $next_cursor['last_comment_id'];
				$this->last_term_id    = $next_cursor['last_term_id'];
				$this->upstream->seek( $next_cursor['upstream'] );

				// Reset the XML processor to the cursor.
				// $this->xml->reset_to( $next_cursor['xml'] );
				$this->xml = WP_XML_Processor::create_for_streaming( '', $next_cursor['xml'] );
				echo "Reset to {$next_cursor['xml']}\n";
			}*/
		}

		return parent::read_next_entity();
	}

	/**
	 * Get the name of the SQL table.
	 *
	 * @return string The name of the table.
	 */
	public static function get_table_name() {
		global $wpdb;

		// Default is wp_{TABLE_NAME}
		return $wpdb->prefix . self::TABLE_NAME;
	}

	/**
	 * Run during the register_activation_hook or similar actions. It creates
	 * the table if it doesn't exist.
	 */
	public static function create_or_update_db() {
		global $wpdb;

		// See wp_get_db_schema.
		$max_index_length = 191;

		/**
		 * This is a table used to map the IDs of the imported entities. It is
		 * used to map all the IDs of the entities.
		 *
		 * @param int $id The ID of the entity.
		 * @param int $session_id The current session ID.
		 * @param int $entity_type The type of the entity, comment, etc.
		 * @param string $entity_id The ID of the entity before the import.
		 * @param string $mapped_id The mapped ID of the entity after import.
		 * @param string $parent_id The parent ID of the entity.
		 * @param string $cursor_id The cursor ID of the entity.
		 * @param int $sort_order The sort order of the entity.
		 */
		$sql = $wpdb->prepare(
			'CREATE TABLE IF NOT EXISTS %i (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				session_id bigint(20) unsigned,
				entity_type tinyint(1) NOT NULL,
				entity_id text NOT NULL,
				mapped_id text DEFAULT NULL,
				parent_id text DEFAULT NULL,
				cursor_id text DEFAULT NULL,
				sort_order int DEFAULT 1,
				PRIMARY KEY  (id),
				KEY session_id (session_id),
				KEY entity_id (entity_id(%d)),
				KEY parent_id (parent_id(%d)),
				KEY cursor_id (cursor_id(%d))
			) ' . $wpdb->get_charset_collate(),
			self::get_table_name(),
			$max_index_length,
			$max_index_length,
			$max_index_length
		);

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		// dbDelta is a function that creates the table.
		dbDelta( $sql );
	}

	/**
	 * Run by register_deactivation_hook or similar. It drops the table and
	 * deletes the option.
	 */
	public static function delete_db() {
		global $wpdb;

		// Drop the table.
		$wpdb->query(
			$wpdb->prepare( 'DROP TABLE IF EXISTS %i', self::get_table_name() )
		);
	}

	/**
	 * Set the emit cursor flag. If true, the reader will emit the cursor ID
	 * for each entity.
	 *
	 * @param bool $emit_cursor The emit cursor flag.
	 */
	public function set_emit_cursor( $emit_cursor ) {
		$this->emit_cursor = $emit_cursor;
	}

	/**
	 * Reset the class.
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
	public function delete_session( $session_id = null ) {
		global $wpdb;

		return $wpdb->delete(
			self::get_table_name(),
			array( 'session_id' => $session_id ?? $this->current_session ),
			array( '%d' )
		);
	}

	/**
	 * Add the next entity to the sorting table.
	 *
	 * @param string $entity_type The type of the entity.
	 * @param array  $data The data to map.
	 * @param mixed  $cursor_id The stream cursor ID.
	 */
	public function add_next_entity( $entity = null ) {
		global $wpdb;

		// We're done if all the entities are processed
		if ( ! $this->valid() ) {
			return false;
		}

		$entity      = $entity ?? $this->current();
		$data        = $entity->get_data();
		$entity_type = $entity->get_type();

		// Do not need to be mapped, skip it.
		if ( ! array_key_exists( $entity_type, self::ENTITY_TYPES ) ) {
			// Advance to next entity.
			$this->next();

			return true;
		}

		// Default sort order is 1.
		$sort_order = 1;
		$cursor_id  = $this->get_reentrancy_cursor();

		// The new entity to be added to the table.
		$new_entity = array(
			'session_id'  => $this->current_session,
			'entity_type' => self::ENTITY_TYPES[ $entity_type ],
			'entity_id'   => null,
			'mapped_id'   => null,
			'parent_id'   => null,
			'cursor_id'   => $cursor_id,
			'sort_order'  => 1,
		);

		// Get the ID of the entity.
		$entity_id      = (string) $data[ self::ENTITY_TYPES_ID[ $entity_type ] ];
		$parent_id_type = null;
		$check_existing = true;

		// Map the parent ID if the entity has one.
		switch ( $entity_type ) {
			case 'category':
				if ( array_key_exists( 'parent', $data ) && ! empty( $data['parent'] ) ) {
					$new_entity['parent_id'] = $data['parent'];
					$parent_id_type          = self::ENTITY_TYPES['category'];
				}

				// Categories have at least a sort order of 2. Because they must
				// be declated before the <item></item> array. But in malformed WXR files,
				// categories can potentially be declared after it.
				$sort_order = 2;
				break;
			case 'post':
				if (
					array_key_exists( 'post_type', $data ) &&
					( 'post' === $data['post_type'] || 'page' === $data['post_type'] )
				) {
					// If the post has a parent, we need to map it.
					if ( array_key_exists( 'post_parent', $data ) && 0 !== (int) $data['post_parent'] ) {
						$new_entity['parent_id'] = (string) $data['post_parent'];
						$parent_id_type          = self::ENTITY_TYPES['post'];
					}
				}
				break;
			case 'site_option':
				// This supports up to a hierarchy depth of 1 million categories and posts.
				$sort_order = 1000001;
				// Site options have no parent.
				$check_existing = false;
				break;
			case 'user':
				// This supports up to a hierarchy depth of 1 million categories and posts.
				$sort_order = 1000000;
				// Users have no parent.
				$check_existing = false;
				break;
			case 'term':
				if ( array_key_exists( 'parent', $data ) && ! empty( $data['parent'] ) ) {
					// If the term has a parent, we need to map it.
					$new_entity['parent_id'] = $data['parent'];
					$parent_id_type          = self::ENTITY_TYPES['term'];
				}

				// Terms, like categories have at least a sort order of 2 for
				// the same reason as categories.
				$sort_order = 2;
				break;
		}

		$new_entity['sort_order'] = $sort_order;

		// Get the existing entity, if any.
		$existing_entity = $check_existing ? $this->get_mapped_ids( $entity_id, self::ENTITY_TYPES[ $entity_type ] ) : null;

		if ( ! empty( $existing_entity ) ) {
			// If the entity exists, we need to get its sort order.
			$sort_order = $existing_entity['sort_order'];
		}

		// If the entity has a parent, we need to check it.
		if ( ! empty( $parent_id_type ) ) {
			// Check if the parent exists.
			$existing_parent = $this->get_mapped_ids( $new_entity['parent_id'], $parent_id_type );

			if ( empty( $existing_parent ) ) {
				// If the parent doesn't exist, we need to add it to the table.
				// This happens when the child is declared before the parent.
				$new_parent = array(
					'session_id'  => $this->current_session,
					'entity_type' => $parent_id_type,
					'entity_id'   => $new_entity['parent_id'],
					'mapped_id'   => null,
					'parent_id'   => null,
					'cursor_id'   => null,
					// The parent has at least a sort order of + 1 than the child.
					'sort_order'  => $sort_order + 1,
				);

				// Add it to the table.
				$wpdb->insert( self::get_table_name(), $new_parent );
			}
		}

		if ( empty( $existing_entity ) ) {
			$new_entity['entity_id'] = $entity_id;

			// Insert the entity if it doesn't exist and advance to next entity.
			$wpdb->insert( self::get_table_name(), $new_entity );
			$this->next();

			return true;
		}

		// The entity exists, so we need to update the sort order if needed.
		// These are arrays used in the SQL update. We do not update the entity by default.
		$update_entity = array();
		$update_types  = array();

		if ( empty( $existing_entity['cursor_id'] ) ) {
			// This can happen when the entity is not already mapped.
			$update_entity['cursor_id'] = $cursor_id;
			$update_types[]             = '%s';
		}

		// The entity exists, so we need to update the sort order. Check if it has a child.
		$first_child = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT entity_id, mapped_id, sort_order
				FROM %i
				WHERE parent_id = %s AND entity_type = %d AND session_id = %d
				LIMIT 1',
				self::get_table_name(),
				(string) $new_entity['parent_id'],
				$parent_id_type,
				$this->current_session
			),
			ARRAY_A
		);

		// We found a child, so we need to update the sort order with a new sort order.
		if ( $first_child && 1 === count( $first_child ) ) {
			// The sort order is the sort order of the first child found, plus one.
			$new_sort_order = $first_child[0]['sort_order'] + 1;

			// Update the sort order only if it's greater than the existing sort
			// order. This optimizes the number of SQL queries.
			if ( $new_sort_order > $sort_order ) {
				$update_entity['sort_order'] = $new_sort_order;
				$update_types[]              = '%d';
			}
		}

		// If there are updates to be made, do them.
		if ( count( $update_entity ) ) {
			$wpdb->update(
				self::get_table_name(),
				$update_entity,
				array(
					'entity_id'   => (string) $entity_id,
					'entity_type' => self::ENTITY_TYPES[ $entity_type ],
					'session_id'  => $this->current_session,
					// 'cursor_id'   => $cursor_id,
				),
				$update_types
			);
		}

		// Advance to next entity.
		$this->next();

		return true;
	}

	/**
	 * A new entity has been imported, so we need to update the mapped ID to be
	 * reused later in the WP_WXR_Sorted_Reader::get_entity() calls. New entities
	 * imported need to refer to the existing parent entities and their newly
	 * generated IDs.
	 *
	 * @param object $entity The entity to update.
	 * @param string $new_id The new ID of the entity.
	 */
	public function update_mapped_id( $entity, $new_id ) {
		global $wpdb;

		if ( is_null( $new_id ) ) {
			return;
		}

		$entity_type = $entity->get_type();

		if ( ! array_key_exists( $entity_type, self::ENTITY_TYPES ) ) {
			return;
		}

		$data = $entity->get_data();

		// Update the mapped ID.
		$wpdb->update(
			self::get_table_name(),
			array(
				'cursor_id' => null,
				'mapped_id' => (string) $new_id,
			),
			array(
				'entity_id'   => (string) $data[ self::ENTITY_TYPES_ID[ $entity_type ] ],
				'entity_type' => self::ENTITY_TYPES[ $entity_type ],
				'session_id'  => $this->current_session,
				'mapped_id'   => null,
			),
			array( '%s' )
		);
	}

	/**
	 * Get the next cursor ID from the table. If the cursor ID is found, we
	 * delete the row and return the cursor ID.
	 *
	 * @return string|null The next cursor.
	 */
	private function get_next_cursor() {
		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				// We need to order by `sort_order DESC, id ASC` to get the
				// last cursor IDs. In SQL, if multiple rows have the same value
				// in that column, the order of those rows is undefined unless
				// you explicitly specify additional sorting criteria.
				'SELECT id, cursor_id
				FROM %i
				WHERE session_id = %d
				AND cursor_id IS NOT NULL
				ORDER BY sort_order DESC, id ASC
				LIMIT 1',
				self::get_table_name(),
				$this->current_session
			),
			ARRAY_A
		);

		if ( $results && 1 === count( $results ) ) {
			// Delete the row we just retrieved.
			/*$wpdb->delete(
				self::get_table_name(),
				array( 'id' => $results[0]['id'] ),
				array( '%d' )
			);*/

			return $results[0]['cursor_id'];
		}

		return null;
	}

	/**
	 * Gets the data for the current entity. Parents are overridden with the ID
	 * generated in the new blog.
	 *
	 * @since WP_VERSION
	 *
	 * @return WP_Imported_Entity The entity.
	 */
	public function get_entity(): WP_Imported_Entity {
		//  $entity_type, $entity, $id = null, $additional_id = null
		// $already_mapped = false;
		$entity = parent::get_entity();

		if ( ! $this->emit_cursor ) {
			return $entity;
		}

		// $mapped_entity = null;
		$entity_type = $entity->get_type();

		if ( ! array_key_exists( $entity_type, self::ENTITY_TYPES ) ) {
			// This entity type is not mapped.
			return $entity;
		}

		// Get the mapped IDs of the entity.
		$entity_data = $entity->get_data();

		// Get entity parents.
		switch ( $entity_type ) {
			case 'category':
				// The ID is the parent category ID.
				$mapped_ids = $this->get_mapped_ids( $entity_data['parent'], self::ENTITY_TYPES['category'] );

				if ( $mapped_ids && ! is_null( $mapped_ids['mapped_id'] ) ) {
					// Save the mapped ID of category parent.
					$entity_data['parent'] = $mapped_ids['mapped_id'];
				}
				break;
			case 'comment':
				// The ID is the post ID.
				$mapped_ids = $this->get_mapped_ids( $entity_data['post_id'], self::ENTITY_TYPES['post'] );

				if ( $mapped_ids && ! is_null( $mapped_ids['mapped_id'] ) ) {
					// Save the mapped ID of comment parent post.
					$entity_data['comment_post_ID'] = $mapped_ids['mapped_id'];
				}
				break;
			case 'comment_meta':
				// The ID is the comment ID.
				$mapped_ids = $this->get_mapped_ids( $entity_data['comment_id'], self::ENTITY_TYPES['comment'] );

				if ( $mapped_ids && ! is_null( $mapped_ids['mapped_id'] ) ) {
					// Save the mapped ID of comment meta parent comment.
					$entity_data['comment_id'] = $mapped_ids['mapped_id'];
				}
				break;
			case 'post':
				// The ID is the parent post ID.
				$mapped_ids = $this->get_mapped_ids( $entity_data['post_parent'], self::ENTITY_TYPES['post'] );

				if ( $mapped_ids && ! is_null( $mapped_ids['mapped_id'] ) ) {
					// Save the mapped ID of post parent.
					$entity_data['post_parent'] = $mapped_ids['mapped_id'];
				}
				break;
			case 'post_meta':
				// The ID is the post ID.
				$mapped_ids = $this->get_mapped_ids( $entity_data['post_id'], self::ENTITY_TYPES['post'] );

				if ( $mapped_ids ) {
					// Save the mapped ID of post meta parent post.
					$entity_data['post_id'] = $mapped_ids['mapped_id'];
				}
				break;
			// TODO: add term meta mapping. See https://github.com/WordPress/wordpress-playground/pull/2105
			/*case 'term_meta':
				// The ID is the term ID.
				$mapped_ids = $this->get_mapped_ids( $entity_data['term_id'], self::ENTITY_TYPES['term'] );

				if ( $mapped_ids && ! is_null( $mapped_ids['mapped_id'] ) ) {
					// Save the mapped ID of term meta parent term.
					$entity_data['term_id'] = $mapped_ids['mapped_id'];
				}*/
		}

		$entity->set_data( $entity_data );

		return $entity;
	}

	/**
	 * Get the mapped ID for an entity.
	 *
	 * @param int $id   The ID of the entity.
	 * @param int $type The type of the entity.
	 *
	 * @return int|false The mapped ID or null if the entity is not found.
	 */
	private function get_mapped_ids( $id, $type ) {
		global $wpdb;

		if ( ! $id ) {
			return null;
		}

		if ( is_null( $this->current_session ) ) {
			$results = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT entity_id, mapped_id, sort_order FROM %i WHERE entity_id = %s AND entity_type = %d AND session_id IS NULL LIMIT 1',
					self::get_table_name(),
					(string) $id,
					$type
				),
				ARRAY_A
			);
		} else {
			$results = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT entity_id, mapped_id, sort_order FROM %i WHERE entity_id = %s AND entity_type = %d AND session_id = %d LIMIT 1',
					self::get_table_name(),
					(string) $id,
					$type,
					$this->current_session
				),
				ARRAY_A
			);
		}

		if ( $results && 1 === count( $results ) ) {
			return $results[0];
		}

		return null;
	}
}
