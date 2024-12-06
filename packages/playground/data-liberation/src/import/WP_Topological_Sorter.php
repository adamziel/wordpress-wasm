<?php

/**
 * The topological sorter class.
 *
 * We create an in-memory index that contains offsets and lengths of items in the WXR.
 * The indexer will also topologically sort posts so that the order we iterate over posts
 * ensures we always get parents before their children.
 */
class WP_Topological_Sorter {

	/**
	 * The base name of the table.
	 */
	const TABLE_NAME = 'data_liberation_index';

	/**
	 * The option name for the database version.
	 */
	const OPTION_NAME = 'data_liberation_db_version';

	/**
	 * The current database version, to be used with dbDelta.
	 */
	const DB_VERSION = 1;

	// Element types.
	const ELEMENT_TYPE_POST     = 1;
	const ELEMENT_TYPE_CATEGORY = 2;

	/**
	 * Variable for keeping counts of orphaned posts/attachments, it'll also be assigned as temporarly post ID.
	 * To prevent duplicate post ID, we'll use negative number.
	 *
	 * @var int
	 */
	protected $orphan_post_counter = 0;

	/**
	 * Store the ID of the post ID currently being processed.
	 *
	 * @var int
	 */
	protected $last_post_id = 0;

	/**
	 * Whether the sort has been done.
	 *
	 * @var bool
	 */
	protected $sorted = false;

	public static function get_table_name() {
		global $wpdb;

		// Default is wp_{TABLE_NAME}
		return $wpdb->prefix . self::TABLE_NAME;
	}

	/**
	 * Run by register_activation_hook.
	 */
	public static function activate() {
		global $wpdb;

		$table_name = self::get_table_name();

		// Create the table if it doesn't exist.
		// @TODO: remove this custom SQLite declaration after first phase of unit tests is done.
		if ( self::is_sqlite() ) {
			$sql = $wpdb->prepare(
				'CREATE TABLE IF NOT EXISTS %i (
					id INTEGER PRIMARY KEY AUTOINCREMENT,
					element_type INTEGER NOT NULL default %d,
					element_id TEXT NOT NULL,
					parent_id TEXT DEFAULT NULL,
					parent TEXT NOT NULL default "",
					byte_offset INTEGER NOT NULL,
					hierarchy_level TEXT DEFAULT NULL
				);

				CREATE UNIQUE INDEX IF NOT EXISTS idx_element_id ON %i (element_id);
				CREATE INDEX IF NOT EXISTS idx_parent_id ON %i (parent_id);
				CREATE INDEX IF NOT EXISTS idx_byte_offset ON %i (byte_offset);',
				$table_name,
				self::ELEMENT_TYPE_POST,
				$table_name,
				$table_name,
				$table_name
			);
		} else {
			// See wp_get_db_schema
			$max_index_length = 191;

			// MySQL, MariaDB.
			$sql = $wpdb->prepare(
				'CREATE TABLE IF NOT EXISTS %i (
					id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
					element_type tinyint(1) NOT NULL default %d,
					element_id text NOT NULL,
					parent_id text DEFAULT NULL,
					parent varchar(200) NOT NULL default \'\',
					byte_offset bigint(20) unsigned NOT NULL,
					hierarchy_level text DEFAULT NULL,
					PRIMARY KEY  (id),
					KEY element_id (element_id(%d)),
					KEY parent_id (parent_id(%d)),
					KEY byte_offset (byte_offset)
				) ' . $wpdb->get_charset_collate(),
				self::get_table_name(),
				self::ELEMENT_TYPE_POST,
				$max_index_length,
				$max_index_length
			);
		}

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( self::OPTION_NAME, self::DB_VERSION );
	}

	public static function is_sqlite() {
		return defined( 'DB_ENGINE' ) && 'sqlite' === DB_ENGINE;
	}

	/**
	 * Run in the 'plugins_loaded' action.
	 */
	public static function load() {
		if ( self::DB_VERSION !== (int) get_site_option( self::OPTION_NAME ) ) {
			// Used to update the database with dbDelta, if needed in the future.
			self::activate();
		}
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
		$this->orphan_post_counter = 0;
		$this->last_post_id        = 0;
		$this->sorted              = false;
	}

	public function map_category( $byte_offset, $data ) {
		global $wpdb;

		if ( empty( $data ) ) {
			return false;
		}

		$wpdb->insert(
			self::get_table_name(),
			array(
				'element_type' => self::ELEMENT_TYPE_CATEGORY,
				'element_id'   => (string) $data['term_id'],
				'parent_id'    => array_key_exists( 'parent_id', $data ) ? (string) $data['parent_id'] : null,
				'parent'       => array_key_exists( 'parent', $data ) ? $data['parent'] : '',
				'byte_offset'  => $byte_offset,
			)
		);
	}

	public function map_post( $byte_offset, $data ) {
		global $wpdb;

		if ( empty( $data ) ) {
			return false;
		}

		// No parent, no need to sort.
		if ( ! isset( $data['post_type'] ) ) {
			return false;
		}

		if ( 'post' === $data['post_type'] || 'page' === $data['post_type'] ) {
			if ( ! $data['post_id'] ) {
				$this->last_post_id = $this->orphan_post_counter;
				--$this->orphan_post_counter;
			}

			$wpdb->insert(
				self::get_table_name(),
				array(
					'element_type' => self::ELEMENT_TYPE_POST,
					'element_id'   => (string) $data['post_id'],
					'parent_id'    => array_key_exists( 'parent_id', $data ) ? (string) $data['parent_id'] : null,
					'parent'       => '',
					'byte_offset'  => $byte_offset,
				)
			);
		}

		return true;
	}

	/**
	 * Get the byte offset of an element, and remove it from the list.
	 *
	 * @param int $id The ID of the post to get the byte offset.
	 *
	 * @return int|bool The byte offset of the post, or false if the post is not found.
	 */
	public function get_post_byte_offset( $id ) {
		global $wpdb;

		if ( ! $this->sorted ) {
			return false;
		}

		return $wpdb->get_var(
			$wpdb->prepare(
				'SELECT byte_offset FROM %s WHERE element_id = %d AND element_type = %d',
				self::get_table_name(),
				$id,
				self::ELEMENT_TYPE_POST
			)
		);
	}

	/**
	 * Get the byte offset of an element, and remove it from the list.
	 *
	 * @param string $slug The slug of the category to get the byte offset.
	 *
	 * @return int|bool The byte offset of the category, or false if the category is not found.
	 */
	public function get_category_byte_offset( $slug ) {
		global $wpdb;

		if ( ! $this->sorted ) {
			return false;
		}

		return $wpdb->get_var(
			$wpdb->prepare(
				'SELECT byte_offset FROM %s WHERE element_id = %d AND element_type = %d',
				self::get_table_name(),
				$id,
				self::ELEMENT_TYPE_CATEGORY
			)
		);
	}

	public function is_sorted() {
		return $this->sorted;
	}

	/**
	 * Sort elements topologically.
	 *
	 * Elements should not be processed before their parent has been processed.
	 * This method sorts the elements in the order they should be processed.
	 */
	public function sort_topologically( $free_space = true ) {
		/*foreach ( $this->categories as $slug => $category ) {
			// $this->topological_category_sort( $slug, $category );
		}*/

		$this->sort_elements( self::ELEMENT_TYPE_POST );
		$this->sort_elements( self::ELEMENT_TYPE_CATEGORY );

		// Free some space.
		if ( $free_space ) {
			/*
			 * @TODO: all the elements that have not been moved can be flushed away.
			 *
			foreach ( $this->posts as $id => $element ) {
				// Save only the byte offset.
				$this->posts[ $id ] = $element[1];
			}

			/*
			 * @TODO: all the elements that have not been moved can be flushed away.
			 *
			foreach ( $this->categories as $slug => $element ) {
				// Save only the byte offset.
				$this->categories[ $slug ] = $element[1];
			}*/
		}

		$this->sorted = true;
	}

	/**
	 * Recursive sort elements. Posts with parents will be moved to the correct position.
	 *
	 * @param int $type The type of element to sort.
	 * @return true
	 */
	private function sort_elements( $type ) {
		global $wpdb;
		$table_name = self::get_table_name();

		return $wpdb->query(
			$wpdb->prepare(
				// Perform a topological sort CTE.
				'WITH RECURSIVE recursive_hierarchy AS (
					-- Anchor member: select root nodes (nodes with no parent)
					SELECT
						element_id,
						parent_id,
						element_id AS hierarchy_path
					FROM
						%i
					WHERE
						parent_id IS NULL AND element_type = %d

					UNION ALL

					-- Recursive member: join child nodes to their parents
					SELECT
						child.element_id,
						child.parent_id,
						parent.hierarchy_path || \'.\' || child.element_id AS hierarchy_path
					FROM
						%i child
					JOIN
						recursive_hierarchy parent ON child.parent_id = parent.element_id
					WHERE child.element_type = %d
				)

				-- Update the table with computed hierarchy paths
				UPDATE %i
				SET hierarchy_path = (
					SELECT hierarchy_path
					FROM recursive_hierarchy
					WHERE %i.element_id = recursive_hierarchy.element_id
				);',
				$table_name,
				$type,
				$table_name,
				$type,
				$table_name,
				$table_name
			)
		);
	}

	/**
	 * Recursive categories topological sorting.
	 *
	 * @param int $slug       The slug of the category to sort.
	 * @param array $category The category to sort.
	 *
	 * @todo Check for circular dependencies.
	 */
	private function topological_category_sort( $slug, $category ) {
		if ( isset( $this->categories[ $slug ]['visited'] ) ) {
			return;
		}

		$this->categories[ $slug ]['visited'] = true;

		if ( isset( $this->categories[ $category['parent'] ] ) ) {
			$this->topological_category_sort( $category['parent'], $this->categories[ $category['parent'] ] );
		}

		$this->category_index[] = $category['byte_offset'];
	}
}
