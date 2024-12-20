<?php

// @TODO: Move to dedicated file
class WP_Export_Entity {
	// TODO: Is there any reason we need to model specific concepts like
	//       "category" rather than just exporting terms and taxonomy
	//       relationships?
	//       Intuition: Directly representing terms, taxonomies,
	//       and relationships will be more flexible at this level.
	const TYPE_TERM_TAXONOMY     = 'term_taxonomy';
	const TYPE_TERM              = 'term';
	const TYPE_TERM_RELATIONSHIP = 'term_relationship';
	const TYPE_USER              = 'user';
	const TYPE_POST              = 'post';
	const TYPE_POST_META         = 'post_meta';
	const TYPE_COMMENT           = 'comment';
	const TYPE_COMMENT_META      = 'comment_meta';
	const TYPE_OPTION            = 'option';

	private $type;
	private $data;

	public function __construct( $type, $data ) {
		$this->type = $type;
		$this->data = $data;
	}

	public function get_type() {
		return $this->type;
	}

	public function get_data() {
		return $this->data;
	}

	public function set_data( $data ) {
		$this->data = $data;
	}
}

class WP_Taxonomy_Entity_Iterator extends Iterator {
	// @TODO: Implement - Should just list unique taxonomy names
}

// TODO: Maybe this can work for all primary key types
class WP_Entity_Iterator_For_Table_With_Incrementing_IDs extends Iterator {

	public function __construct($table_name, $id_column_name) {

	}

	// @TODO: Implement and use lazy init so there is little cost before use
}	

class WP_Custom_Table_Entity_Iterator extends Iterator {
	// @TODO: Implement
}

class WP_Entity_Export_Iterator extends Iterator {
	protected $entity_export_strategies = null;

	protected $table_iterator = null;
	protected $entity_iterator = null;

	protected $current_table = null;
	protected $last_row_id = null;
	protected $current_entity = null;

	public function __construct() {
		// @TODO: Implement
	}

	#[\ReturnTypeWillChange]
	public function current() {
		// @TODO: Implement
	}

	#[\ReturnTypeWillChange]
	public function next() {
		// @TODO: Implement
	}

	#[\ReturnTypeWillChange]
	public function key() {
		// @TODO: Implement
		// @TODO: Return reentrancy cursor which can also be the cursor for the current item?
	}

	public function valid() {
		// @TODO: Implement
	}

	protected function create_entity_export_strategies() {
		global $wpdb;
		return array(
			'term_taxonomy'     => new WP_Taxonomy_Entity_Iterator(),
			'term'              => new WP_Entity_Iterator_For_Table_With_Incrementing_IDs(
				$wpdb->terms,
				'term_id',
			),
			'term_relationship' => new WP_Entity_Iterator_For_Table_With_Incrementing_IDs(
				$wpdb->term_relationships,
				'term_taxonomy_id',
			),
			'user'              => new WP_Entity_Iterator_For_Table_With_Incrementing_IDs(
				$wpdb->users,
				'ID',
			),
			'post'              => new WP_Entity_Iterator_For_Table_With_Incrementing_IDs(
				$wpdb->posts,
				'ID',
			),
			'post_meta'         => new WP_Entity_Iterator_For_Table_With_Incrementing_IDs(
				$wpdb->postmeta,
				'meta_id',
			),
			'comment'           => new WP_Entity_Iterator_For_Table_With_Incrementing_IDs(
				$wpdb->comments,
				'comment_ID',
			),
			'comment_meta'      => new WP_Entity_Iterator_For_Table_With_Incrementing_IDs(
				$wpdb->commentmeta,
				'meta_id',
			),
			'option'            => new WP_Entity_Iterator_For_Table_With_Incrementing_IDs(
				$wpdb->options,
				'option_id',
			),
			// @TODO: Support export of custom tables, maybe allowing plugins to choose entity iterator via filter
		);
	}
}