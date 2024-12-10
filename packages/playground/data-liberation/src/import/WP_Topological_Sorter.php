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

	/**
	 * The current session ID.
	 */
	protected $current_session = null;

	/**
	 * The total number of categories.
	 */
	protected $total_categories = 0;

	/**
	 * The total number of posts.
	 */
	protected $total_posts = 0;

	/**
	 * The current item being processed.
	 */
	protected $current_item = 0;

	public function __construct( $options = array() ) {
		if ( array_key_exists( 'session_id', $options ) ) {
			$this->current_session = $options['session_id'];
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
					session_id INTEGER NOT NULL,
					element_type INTEGER NOT NULL default %d,
					element_id TEXT NOT NULL,
					parent_id TEXT DEFAULT NULL,
					byte_offset INTEGER NOT NULL,
					sort_order int DEFAULT 1
				);

				CREATE UNIQUE INDEX IF NOT EXISTS idx_element_id ON %i (element_id);
				CREATE INDEX IF NOT EXISTS idx_session_id ON %i (session_id);
				CREATE INDEX IF NOT EXISTS idx_parent_id ON %i (parent_id);
				CREATE INDEX IF NOT EXISTS idx_byte_offset ON %i (byte_offset);',
				$table_name,
				self::ELEMENT_TYPE_POST,
				$table_name,
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
					session_id bigint(20) unsigned NOT NULL,
					element_type tinyint(1) NOT NULL default %d,
					element_id text NOT NULL,
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
		$this->current_session     = null;
		$this->total_categories    = 0;
		$this->total_posts         = 0;
		$this->current_item        = 0;
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
	 * Map a category to the index.
	 *
	 * @param int $byte_offset The byte offset of the category.
	 * @param array $data The category data.
	 */
	public function map_category( $byte_offset, $data ) {
		global $wpdb;

		if ( empty( $data ) ) {
			return false;
		}

		$category_parent = null;

		if ( array_key_exists( 'parent', $data ) && '' !== $data['parent'] ) {
			$category_parent = $data['parent'];
		}

		$wpdb->insert(
			self::get_table_name(),
			array(
				'session_id'   => $this->current_session,
				'element_type' => self::ELEMENT_TYPE_CATEGORY,
				'element_id'   => (string) $data['term_id'],
				'parent_id'    => $category_parent,
				'byte_offset'  => $byte_offset,
				// Items with a parent has at least a sort order of 2.
				'sort_order'   => $category_parent ? 2 : 1,
			)
		);

		++$this->total_categories;
	}

	/**
	 * Map a post to the index.
	 *
	 * @param int $byte_offset The byte offset of the post.
	 * @param array $data The post data.
	 */
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

			$post_parent = null;

			if ( array_key_exists( 'post_parent', $data ) && '0' !== $data['post_parent'] ) {
				$post_parent = $data['post_parent'];
			}

			$wpdb->insert(
				self::get_table_name(),
				array(
					'session_id'   => $this->current_session,
					'element_type' => self::ELEMENT_TYPE_POST,
					'element_id'   => (string) $data['post_id'],
					'parent_id'    => $post_parent,
					'byte_offset'  => $byte_offset,
					'sort_order'   => $post_parent ? 2 : 1,
				)
			);

			++$this->total_posts;
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
	public function get_post_byte_offset( $session_id, $id ) {
		global $wpdb;

		if ( ! $this->sorted ) {
			return false;
		}

		return $wpdb->get_var(
			$wpdb->prepare(
				'SELECT byte_offset FROM %i WHERE element_id = %s AND element_type = %d AND session_id = %d LIMIT 1',
				self::get_table_name(),
				(string) $id,
				self::ELEMENT_TYPE_POST,
				(string) $session_id
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
	public function get_category_byte_offset( $session_id, $slug ) {
		global $wpdb;

		if ( ! $this->sorted ) {
			return false;
		}

		return $wpdb->get_var(
			$wpdb->prepare(
				'SELECT byte_offset FROM %i WHERE element_id = %s AND element_type = %d AND session_id = %d LIMIT 1',
				self::get_table_name(),
				(string) $slug,
				self::ELEMENT_TYPE_CATEGORY,
				(string) $session_id
			)
		);
	}

	/**
	 * Get the next item to process.
	 *
	 * @param int $session_id The session ID to get the next item from.
	 *
	 * @return array|bool The next item to process, or false if there are no more items.
	 */
	public function next_item( $element_type, $session_id = null ) {
		global $wpdb;

		if ( ! $this->sorted || ( 0 === $this->total_posts && 0 === $this->total_categories ) ) {
			return false;
		}

		if ( null === $session_id ) {
			$session_id = $this->current_session;
		}

		$next_item = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE element_type = %d ORDER BY sort_order ASC LIMIT 1 OFFSET %d',
				self::get_table_name(),
				$element_type,
				$this->current_item
			),
			ARRAY_A
		);

		if ( ! $next_item ) {
			return null;
		}

		return $next_item;
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
	public function sort_topologically() {
		// $this->sort_elements( self::ELEMENT_TYPE_POST );
		// $this->sort_elements( self::ELEMENT_TYPE_CATEGORY );

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

		if ( self::is_sqlite() ) {
			// SQLite recursive CTE query to perform topological sort
			return $wpdb->query(
				$wpdb->prepare(
					'WITH RECURSIVE sorted_elements AS (
						SELECT element_id, parent_id, ROW_NUMBER() OVER () AS sort_order
						FROM %i
						WHERE parent_id IS NULL AND element_type = %d
						UNION ALL
						SELECT e.element_id, e.parent_id, se.sort_order + 1
						FROM %i e
						INNER JOIN sorted_elements se
						ON e.parent_id = se.element_id AND e.element_type = %d
					)
					UPDATE %i SET sort_order = (
						SELECT sort_order 
						FROM sorted_elements s 
						WHERE s.element_id = %i.element_id
					)
					WHERE element_type = %d;',
					$table_name,
					$type,
					$table_name,
					$type,
					$table_name,
					$table_name,
					$type
				)
			);
		}

		// MySQL version - update sort_order using a subquery
		return $wpdb->query(
			$wpdb->prepare(
				'UPDATE %i t1
				JOIN (
					SELECT element_id, 
						   @sort := @sort + 1 AS new_sort_order
					FROM %i
					CROSS JOIN (SELECT @sort := 0) AS sort_var 
					WHERE element_type = %d
					ORDER BY COALESCE(parent_id, "0"), element_id
				) t2 ON t1.element_id = t2.element_id
				SET t1.sort_order = t2.new_sort_order
				WHERE t1.element_type = %d',
				$table_name,
				$table_name,
				$type,
				$type
			)
		);
	}
}
