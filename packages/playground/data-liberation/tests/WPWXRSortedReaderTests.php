<?php

require_once __DIR__ . '/PlaygroundTestCase.php';

/**
 * Tests for the WP_WXR_Sorted_Reader class.
 */
class WPWXRSortedReaderTests extends PlaygroundTestCase {

	protected function setUp(): void {
		parent::setUp();

		$this->delete_all_data();
		wp_cache_flush();
		WP_WXR_Sorted_Reader::create_or_update_db();
	}

	protected function tearDown(): void {
		WP_WXR_Sorted_Reader::delete_db();

		parent::tearDown();
	}

	public function test_count_entities_of_small_import() {
		global $wpdb;

		$file_path = __DIR__ . '/wxr/small-export.xml';
		$importer  = $this->import_wxr_file( $file_path );

		$this->skip_to_stage( $importer, WP_Stream_Importer::STAGE_TOPOLOGICAL_SORT );

		while ( $importer->next_step() ) {
			// noop
		}

		$count = $wpdb->get_var(
			$wpdb->prepare( 'SELECT COUNT(*) FROM %i', WP_WXR_Sorted_Reader::get_table_name() )
		);

		$this->assertEquals( 41, (int) $count );
		$types = $this->small_import_counts();

		foreach ( $types as $entity_type => $expected_count ) {
			$count = $wpdb->get_var(
				$wpdb->prepare(
					'SELECT COUNT(*) FROM %i WHERE entity_type = %d',
					WP_WXR_Sorted_Reader::get_table_name(),
					$entity_type
				)
			);

			$this->assertEquals( $expected_count, (int) $count );
		}
	}

	public function test_small_import() {
		global $wpdb;

		$file_path = __DIR__ . '/wxr/small-export.xml';
		$importer  = $this->import_wxr_file( $file_path );
		$map_id    = function ( $post ) {
			return $post->ID;
		};
		$query     = array(
			'order'       => 'ASC',
			'orderby'     => 'ID',
			'numberposts' => -1,
		);

		do {
			echo 'Stage: ' . $importer->get_stage() . "\n";
			while ( $importer->next_step() ) {
				// noop
			}
		} while ( $importer->advance_to_next_stage() );

		$expected_posts = array( 1, 15, 17, 19, 22 );
		$public_posts   = get_posts( $query );

		$this->assertEquals( $expected_posts, array_map( $map_id, $public_posts ) );

		$query['post_type'] = 'page';
		$expected_pages     = array( 2, 4, 6, 11 );
		$public_pages       = get_posts( $query );

		$this->assertEquals( $expected_pages, array_map( $map_id, $public_pages ) );

		$count = $wpdb->get_var(
			$wpdb->prepare( 'SELECT COUNT(*) FROM %i', WP_WXR_Sorted_Reader::get_table_name() )
		);

		// All elements should be deleted.
		$this->assertEquals( 0, (int) $count );
	}

	public function test_small_import_right_order_of_import() {
		global $wpdb;

		$file_path    = __DIR__ . '/wxr/small-export.xml';
		$importer     = $this->import_wxr_file( $file_path );
		$count        = 0;
		$imported_ids = array(
			'category' => array(),
			'post'     => array(),
			'post_tag' => array(),
			'unknown'  => array(),
		);
		$expected_ids = array(
			'category' => array(
				'alpha',
				'bar',
				'beta',
				'chi',
				'delta',
				'epsilon',
				'eta',
				'foo',
				'foo-bar',
				'gamma',
				'iota',
				'kappa',
				'lambda',
				'mu',
				'nu',
				'omega',
				'omicron',
				'phi',
				'pi',
				'psi',
				'rho',
				'sigma',
				'tau',
				'theta',
				'uncategorized',
				'unused-category',
				'upsilon',
				'xi',
				'zeta',
				'eternity',
			),
			'post' => array(
				'http://127.0.0.1:9400/?p=1',
				'http://127.0.0.1:9400/?page_id=2',
				'http://127.0.0.1:9400/?page_id=4',
				'http://127.0.0.1:9400/?page_id=6',
				'http://127.0.0.1:9400/?page_id=9',
				'http://127.0.0.1:9400/?page_id=11',
				'http://127.0.0.1:9400/?p=13',
				'http://127.0.0.1:9400/?p=15',
				'http://127.0.0.1:9400/?p=17',
				'http://127.0.0.1:9400/?p=19',
				'http://127.0.0.1:9400/?p=22',
			),
			'post_tag' => array(
				'tag1',
				'tag2',
				'tag3',
			),
			'unknown' => array(),
		);

		$import_fn = function ( $data, $id = null ) use ( &$imported_ids, &$count ) {
			if ( array_key_exists( 'post_id', $data ) ) {
				$imported_ids['post'][] = $data['guid'];
			} elseif ( array_key_exists( 'taxonomy', $data ) ) {
				$imported_ids[ $data['taxonomy'] ][] = $data['slug'];
			} else {
				$imported_ids['unknown'][] = $data;
			}

			++$count;

			return $data;
		};

		add_filter( 'wxr_importer_pre_process_post', $import_fn, 10, 2 );
		add_filter( 'wxr_importer_pre_process_term', $import_fn );

		do {
			while ( $importer->next_step() ) {
				// noop
			}
		} while ( $importer->advance_to_next_stage() );

		$this->assertEquals( $expected_ids, $imported_ids );

		$categories = get_terms(
			array(
				'taxonomy'   => 'category',
				'hide_empty' => false,
			)
		);

		$this->assertEquals( $expected_ids['category'], $imported_ids['category'] );
		// $this->assertEquals( 1, 2 );

		remove_filter( 'wxr_importer_pre_process_post', $import_fn );
		remove_filter( 'wxr_importer_pre_process_term', $import_fn );

		$this->assertEquals( 44, $count );
	}

	public function test_unsorted_categories() {
		$file_path = __DIR__ . '/wxr/unsorted-categories.xml';
		$importer  = $this->import_wxr_file( $file_path );
		$import_fn = function ( $data ) {
			// print_r( $data );

			return $data;
		};

		add_filter( 'wxr_importer_pre_process_term', $import_fn );

		do {
			while ( $importer->next_step() ) {
				// noop
			}
		} while ( $importer->advance_to_next_stage() );

		$categories = get_terms(
			array(
				'taxonomy'   => 'category',
				'hide_empty' => false,
			)
		);

		remove_filter( 'wxr_importer_pre_process_term', $import_fn );

		$this->assertEquals( 1, 2 );
	}

	private function small_import_counts() {
		$types = WP_WXR_Sorted_Reader::ENTITY_TYPES;

		return array(
			$types['category'] => 33,
			$types['post']     => 13,
			$types['term']     => 0,
		);
	}

	/**
	 * Import a WXR file.
	 */
	private function import_wxr_file( string $file_path ) {
		$args = array(
			'data_source' => 'wxr_file',
			'file_name'   => $file_path,
		);

		$import_session = WP_Import_Session::create( $args );

		// Pass the session ID.
		$options = array( 'post_id' => $import_session->get_id() );

		return WP_Stream_Importer::create_for_wxr_file( $file_path, $options );
	}
}
