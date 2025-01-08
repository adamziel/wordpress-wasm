<?php

// @TODO: This is just a hack for easy manual testing. Remove it before merge.
add_action('plugins_loaded', function() {
	if (!str_starts_with($_SERVER['REQUEST_URI'], '/_test_export_entities')) {
		return;
	}

	header('Content-Type: text/plain');
	$export_iterator = new WP_Entity_Export_Iterator();
	foreach ($export_iterator as $key => $entity) {
		echo "Key: $key\n";
		var_dump($entity);
	}
	die();
});

// @TODO: Move to dedicated file
class WP_Export_Entity {
	// @TODO: If we want to naively stream entities, perhaps sites should be able
	//        run a process that cleans up cruft like orphaned term relationships.
	//        Then that process could be run before attempting export.
	// @TODO: Is there any reason we need to model specific concepts like
	//       "category" rather than just exporting terms and taxonomy
	//       relationships?
	//       Intuition: Directly representing terms, taxonomies,
	//       and relationships will be more flexible at this level.
	const TYPE_TERM              = 'term';
	// @TODO: Counts likely will need regenerated after import
	const TYPE_TERM_TAXONOMY     = 'term_taxonomy';
	const TYPE_USER              = 'user';
	const TYPE_POST              = 'post';
	const TYPE_POST_META         = 'post_meta';
	const TYPE_TERM_RELATIONSHIP = 'term_relationship';
	const TYPE_COMMENT           = 'comment';
	const TYPE_COMMENT_META      = 'comment_meta';
	const TYPE_OPTION            = 'option';

	private $type;
	private $data;

	public function __construct($type, $data) {
		$this->type = $type;
		$this->data = $data;
	}

	public function get_type() {
		return $this->type;
	}

	public function get_data() {
		return $this->data;
	}

	public function set_data($data) {
		$this->data = $data;
	}
}

// TODO: Maybe this can work for all primary key types
class WP_Entity_Iterator_For_Table_With_Incrementing_IDs implements Iterator {
	protected $entity_type;
	protected $table_name;
	protected $primary_key;
	protected $current_entity;
	protected $current_id;

	public function __construct($entity_type, $table_name, $primary_key) {
		$this->entity_type = $entity_type;
		$this->table_name = $table_name;
		$this->primary_key = $primary_key;
		$this->current_id = 0;
		$this->current_entity = null;
	}

	#[\ReturnTypeWillChange]
	public function rewind() {
		$this->current_id = 0;
		$this->next();
	}

	#[\ReturnTypeWillChange]
	public function current() {
		return $this->current_entity;
	}

	#[\ReturnTypeWillChange]
	public function key() {
		return $this->current_id;
	}

	#[\ReturnTypeWillChange]
	public function next() {
		global $wpdb;
		$current_row = $wpdb->get_row(
			$wpdb->prepare(
				// @TODO: Consider selecting more than 1 row at a time for possibly-better performance.
				"SELECT * FROM {$this->table_name} WHERE {$this->primary_key} > %d ORDER BY {$this->primary_key} ASC LIMIT 1",
				$this->current_id
			),
			ARRAY_A
		);
		// @TODO: How to handle errors?
		
		$this->current_entity = null;
		if ($current_row) {
			$this->current_id = $current_row[$this->primary_key];
			$this->current_entity = new WP_Export_Entity($this->entity_type, $current_row);
		}
	}
	
	#[\ReturnTypeWillChange]
	public function valid() {
		return $this->current_entity !== null;
	}
}

// @TODO: Maybe this is the same as incrementing ID iterator, but maybe should should handle other kinds of tables as well.
// class WP_Custom_Table_Entity_Iterator implements Iterator {
// 	// @TODO: Implement
// }

class WP_Entity_Export_Iterator implements Iterator {
	protected $entity_iterator_iterator = null;

	#[\ReturnTypeWillChange]
	public function rewind() {
		$entity_export_strategies = $this->create_entity_export_strategies();
		$this->entity_iterator_iterator = new ArrayIterator($entity_export_strategies);
		$this->next();
	}

	#[\ReturnTypeWillChange]
	public function current() {
		return $this->entity_iterator_iterator->current()->current();
	}

	#[\ReturnTypeWillChange]
	public function next() {
		if (!$this->entity_iterator_iterator->valid()) {
			return;
		}

		$this->entity_iterator_iterator->current()->next();
		while (
			! $this->entity_iterator_iterator->current()->valid() &&
			$this->entity_iterator_iterator->valid()
		) {
			$this->entity_iterator_iterator->next();
			$this->entity_iterator_iterator->current()->rewind();
		}
	}

	#[\ReturnTypeWillChange]
	public function key() {

		if (!$this->entity_iterator_iterator->valid()) {
			return null;
		}

		if (!$this->entity_iterator_iterator->current()->valid()) {
			return null;
		}

		$entity_iterator_iterator_key = $this->entity_iterator_iterator->key();
		$entity_iterator_key = $this->entity_iterator_iterator->current()->key();

		// @TODO: Return reentrancy cursor which can also be the cursor for the current item?
		// NOTE: For reentrancy, should we prefer including table name instead of table iterator index?
		return implode(
			',',
			array(
				$entity_iterator_iterator_key,
				$entity_iterator_key,
			)
		);
	}

	#[\ReturnTypeWillChange]
	public function valid() {
		return $this->entity_iterator_iterator->valid();
	}

	// TODO: Maybe simplify this if we stick with just iterating over rows for all tables.
	protected function create_entity_export_strategies() {
		global $wpdb;
		return array(
			new WP_Entity_Iterator_For_Table_With_Incrementing_IDs(
				'term',
				$wpdb->terms,
				'term_id'
			),
			new WP_Entity_Iterator_For_Table_With_Incrementing_IDs(
				'termmeta',
				$wpdb->terms,
				'term_id'
			),
			new WP_Entity_Iterator_For_Table_With_Incrementing_IDs(
				'term_taxonomy',
				$wpdb->term_taxonomy,
				'term_id'
			),
			new WP_Entity_Iterator_For_Table_With_Incrementing_IDs(
				'term_relationship',
				$wpdb->term_relationships,
				'term_taxonomy_id'
			),
			new WP_Entity_Iterator_For_Table_With_Incrementing_IDs(
				'user',
				$wpdb->users,
				'ID'
			),
			new WP_Entity_Iterator_For_Table_With_Incrementing_IDs(
				'post',
				$wpdb->posts,
				'ID'
			),
			new WP_Entity_Iterator_For_Table_With_Incrementing_IDs(
				'post_meta',
				$wpdb->postmeta,
				'meta_id'
			),
			new WP_Entity_Iterator_For_Table_With_Incrementing_IDs(
				'comment',
				$wpdb->comments,
				'comment_ID'
			),
			new WP_Entity_Iterator_For_Table_With_Incrementing_IDs(
				'comment_meta',
				$wpdb->commentmeta,
				'meta_id'
			),
			new WP_Entity_Iterator_For_Table_With_Incrementing_IDs(
				'option',
				$wpdb->options,
				'option_id'
			),
			// @TODO: Support export of custom tables, maybe allowing plugins to choose entity iterator via filter
		);
	}
}
