<?php

/**
 * The topological sorter class. We create a custom table that contains the WXR
 * IDs and the mapped IDs. Everytime an entity is processed, we add it to the
 * table. The first time we process an entity, it is mapped to the original ID
 * and no mapped ID. From the second time, it is mapped to the mapped ID.
 *
 * When the WP_Entity_Importer or similar class read raw data from the source
 * stream that is used to map the original IDs to the mapped IDs.
 *
 * The first STAGE_TOPOLOGICAL_SORT stage do save all the entities with no
 * mapped IDs. So during the STAGE_IMPORT_ENTITIES step the WP_Entity_Importer
 * or similar class read already inserted data and save them. From that moment
 * all the entities have the IDs created using wp_insert_post(),
 * wp_insert_comment(), wp_insert_term(), wp_insert_comment_meta(),
 * wp_insert_post_meta() and wp_insert_term_meta() calls.
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
		'term_meta'    => 6,
	);

	/**
	 * Set the current session ID.
	 */
	public function __construct( $options = array() ) {
		if ( array_key_exists( 'session_id', $options ) ) {
			$this->set_session( $options['session_id'] );
		} else {
			$active_session = WP_Import_Session::get_active();

			if ( $active_session ) {
				$this->set_session( $active_session->get_id() );
			}
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
		 * @param string $mapped_id The mapped ID of the entity after the import.
		 * @param string $parent_id The parent ID of the entity.
		 * @param string $additional_id The additional ID of the entity. Used for comments and terms. Comments have a comment_parent, and the post.
		 * @param int $byte_offset The byte offset of the entity inside the WXR file. Not used now.
		 * @param int $sort_order The sort order of the entity. Not used now.
		 */
		$sql = $wpdb->prepare(
			'CREATE TABLE IF NOT EXISTS %i (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				session_id bigint(20) unsigned,
				entity_type tinyint(1) NOT NULL,
				entity_id text NOT NULL,
				mapped_id text DEFAULT NULL,
				parent_id text DEFAULT NULL,
				additional_id text DEFAULT NULL,
				byte_offset bigint(20) unsigned NOT NULL,
				sort_order int DEFAULT 1,
				PRIMARY KEY  (id),
				KEY session_id (session_id),
				KEY entity_id (entity_id(%d)),
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
	 * Run by register_deactivation_hook. It drops the table and deletes the
	 * option.
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
	 * Reset the class.
	 */
	public function reset() {
		$this->set_session( null );
	}

	/**
	 * Set the current session ID.
	 *
	 * @param int|null $session_id The session ID.
	 */
	public function set_session( $session_id ) {
		$this->current_session = $session_id;
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
	 * Map an entity to the index. If $id is provided, it will be used to map the entity.
	 *
	 * @param string   $entity_type The type of the entity.
	 * @param array    $data The data to map.
	 * @param int|null $id The ID of the entity.
	 * @param int|null $additional_id The additional ID of the entity.
	 */
	public function map_entity( $entity_type, $data, $id = null, $additional_id = null ) {
		global $wpdb;

		if ( ! array_key_exists( $entity_type, self::ENTITY_TYPES ) ) {
			return;
		}

		$new_entity = array(
			'session_id'   => $this->current_session,
			'entity_type' => self::ENTITY_TYPES[ $entity_type ],
			'entity_id'   => null,
			'mapped_id'    => is_null( $id ) ? null : (string) $id,
			'parent_id'    => null,
			'byte_offset'  => 0,
			// Items with a parent has at least a sort order of 2.
			'sort_order'   => 1,
		);
		$entity_id  = null;

		switch ( $entity_type ) {
			case 'comment':
				$entity_id = (string) $data['comment_id'];
				break;
			case 'comment_meta':
				$entity_id = (string) $data['meta_key'];

				if ( array_key_exists( 'comment_id', $data ) ) {
					$new_entity['parent_id'] = $data['comment_id'];
				}
				break;
			case 'post':
				if ( 'post' === $data['post_type'] || 'page' === $data['post_type'] ) {
					if ( array_key_exists( 'post_parent', $data ) && '0' !== $data['post_parent'] ) {
						$new_entity['parent_id'] = $data['post_parent'];
					}
				}

				$entity_id = (string) $data['post_id'];
				break;
			case 'post_meta':
				$entity_id = (string) $data['meta_key'];

				if ( array_key_exists( 'post_id', $data ) ) {
					$new_entity['parent_id'] = $data['post_id'];
				}
				break;
			case 'term_meta':
				$entity_id = (string) $data['meta_key'];

				if ( array_key_exists( 'term_id', $data ) ) {
					$new_entity['parent_id'] = $data['term_id'];
				}
				break;
			case 'term':
				$entity_id = (string) $data['term_id'];

				if ( array_key_exists( 'parent', $data ) ) {
					$new_entity['parent_id'] = $data['parent'];
				}
				break;
		}

		// The entity has been imported, so we can use the ID.
		if ( $id ) {
			$existing_entity = $this->get_mapped_ids( $entity_id, self::ENTITY_TYPES[ $entity_type ] );

			if ( $existing_entity && is_null( $existing_entity['mapped_id'] ) ) {
				$new_entity['mapped_id'] = (string) $id;

				// Update the entity if it already exists.
				$wpdb->update(
					self::get_table_name(),
					array( 'mapped_id' => (string) $id ),
					array(
						'entity_id'   => (string) $entity_id,
						'entity_type' => self::ENTITY_TYPES[ $entity_type ],
					),
					array( '%s' )
				);
			}
		} else {
			// Insert the entity if it doesn't exist.
			$new_entity['entity_id'] = $entity_id;
			$wpdb->insert( self::get_table_name(), $new_entity );
		}
	}

	/**
	 * Get a mapped entity.
	 *
	 * @param int $entity The entity to get the mapped ID for.
	 * @param int $id The ID of the entity.
	 *
	 * @return mixed|bool The mapped entity or false if the post is not found.
	 */
	public function get_mapped_entity( $entity_type, $entity, $id = null, $additional_id = null ) {
		$current_session = $this->current_session;
		$already_mapped  = false;

		switch ( $entity_type ) {
			case 'comment':
				// The ID is the post ID.
				$mapped_ids = $this->get_mapped_ids( $id, self::ENTITY_TYPES['post'] );

				if ( $mapped_ids && ! is_null( $mapped_ids['mapped_id'] ) ) {
					$entity['comment_post_ID'] = $mapped_ids['mapped_id'];
				}
				break;
			case 'comment_meta':
				// The ID is the comment ID.
				$mapped_ids = $this->get_mapped_ids( $id, self::ENTITY_TYPES['comment'] );

				if ( $mapped_ids && ! is_null( $mapped_ids['mapped_id'] ) ) {
					$entity['comment_id'] = $mapped_ids['mapped_id'];
				}
				break;
			case 'post':
				// The ID is the parent post ID.
				$mapped_ids = $this->get_mapped_ids( $id, self::ENTITY_TYPES['post'] );

				if ( $mapped_ids && ! is_null( $mapped_ids['mapped_id'] ) ) {
					$entity['post_parent'] = $mapped_ids['mapped_id'];
				}

				$mapped_ids = $this->get_mapped_ids( $entity['post_id'], self::ENTITY_TYPES['post'] );

				if ( $mapped_ids && ! is_null( $mapped_ids['mapped_id'] ) ) {
					$entity['post_id'] = $mapped_ids['mapped_id'];
					$already_mapped     = true;
				}
				break;
			case 'post_meta':
				// The ID is the post ID.
				$mapped_ids = $this->get_mapped_ids( $id, self::ENTITY_TYPES['post'] );

				if ( $mapped_ids ) {
					$entity['post_id'] = $mapped_ids['mapped_id'];
				}
				break;
			case 'term':
				// No ID provided.
				break;
			case 'term_meta':
				// The ID is the term ID.
				$mapped_ids = $this->get_mapped_ids( $id, self::ENTITY_TYPES['term'] );

				if ( $mapped_ids && ! is_null( $mapped_ids['mapped_id'] ) ) {
					$entity['term_id'] = $mapped_ids['mapped_id'];
				}
				break;
		}

		if ( $already_mapped ) {
			// This is used to skip the post if it has already been mapped.
			$entity['_already_mapped'] = true;
		}

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

		$results = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT entity_id, mapped_id FROM %i WHERE entity_id = %s AND entity_type = %d LIMIT 1',
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
