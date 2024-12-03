<?php
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

class WP_Retry_Frontloading_Iterator implements Iterator {
	private $import_post_id;
	private $last_id_on_page = null;
	private $placeholders    = array();
	private $current;
	private $rewound = true;

	public function __construct( $import_post_id ) {
		$this->import_post_id = $import_post_id;
	}

	public function current(): mixed {
		if ( ! $this->current ) {
			return null;
		}
		return new WP_Imported_Entity(
			'asset_retry',
			array(
				'current_url' => $this->current->meta['current_url'],
				'original_url' => $this->current->meta['original_url'],
			)
		);
	}

	public function next(): void {
		if ( empty( $this->placeholders ) ) {
			$this->query_next_page();
		}
		$this->current = array_shift( $this->placeholders );
		$this->rewound = false;
	}

	public function key(): mixed {
		if ( ! $this->current ) {
			return null;
		}
		return $this->current->ID;
	}

	public function valid(): bool {
		if ( $this->rewound ) {
			$this->next();
		}
		return null !== $this->current;
	}

	public function rewind(): void {
		$this->placeholders    = array();
		$this->last_id_on_page = null;
		$this->rewound         = true;
	}

	private function query_next_page() {
		global $wpdb;

		$where_clauses = array(
			$wpdb->prepare( 'post_type = %s', 'frontloading_placeholder' ),
			$wpdb->prepare( 'post_parent = %d', $this->import_post_id ),
			$wpdb->prepare( 'post_status = %s', WP_Import_Session::FRONTLOAD_STATUS_ERROR ),
			"pm.meta_key = 'attempts'",
			'pm.meta_value < 3',
		);

		if ( null !== $this->last_id_on_page ) {
			$where_clauses[] = $wpdb->prepare( 'p.ID > %d', $this->last_id_on_page );
		}

		$where              = implode( ' AND ', $where_clauses );
		$this->placeholders = $wpdb->get_results(
			"SELECT p.* FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE {$where}
            ORDER BY p.ID ASC 
            LIMIT 100"
		);
		$last_placeholder   = end( $this->placeholders );
		if ( $last_placeholder ) {
			$this->last_id_on_page = $last_placeholder->ID;
		}

		update_meta_cache(
			'post',
			array_map(
				function ( $placeholder ) {
					return $placeholder->ID;
				},
				$this->placeholders
			)
		);

		foreach ( $this->placeholders as $placeholder ) {
			$placeholder->meta = get_all_post_meta_flat( $placeholder->ID );
		}
	}
}
