<?php

require_once __DIR__ . '/PlaygroundTestCase.php';

/**
 * Tests for the WPTopologicalSorterTests class.
 */
class WPTopologicalSorterTests extends PlaygroundTestCase {

	protected function setUp(): void {
		parent::setUp();

		global $wpdb;

		// Empty the wp_commentmeta table
		$wpdb->query( "TRUNCATE TABLE {$wpdb->commentmeta}" );

		// Empty the wp_comments table
		$wpdb->query( "TRUNCATE TABLE {$wpdb->comments}" );

		WP_Topological_Sorter::activate();
	}

	protected function tearDown(): void {
		WP_Topological_Sorter::deactivate();
		parent::tearDown();
	}

	/**
	 * This is a WordPress core importer test.
	 *
	 * @see https://github.com/WordPress/wordpress-importer/blob/master/phpunit/tests/comment-meta.php
	 */
	public function test_serialized_comment_meta() {
		$wxr_path = __DIR__ . '/wxr/test-serialized-comment-meta.xml';
		$importer = WP_Stream_Importer::create_for_wxr_file( $wxr_path );

		do {
			while ( $importer->next_step( 1 ) ) {
				// noop
			}
		} while ( $importer->advance_to_next_stage() );

		$expected_string = '¯\_(ツ)_/¯';
		$expected_array  = array( 'key' => '¯\_(ツ)_/¯' );

		$comments_count = wp_count_comments();
		// Note: using assertEquals() as the return type changes across different WP versions - numeric string vs int.
		$this->assertEquals( 1, $comments_count->approved );

		$comments = get_comments();
		$this->assertCount( 1, $comments );

		$comment = $comments[0];
		$this->assertSame( $expected_string, get_comment_meta( $comment->comment_ID, 'string', true ) );
		$this->assertSame( $expected_array, get_comment_meta( $comment->comment_ID, 'array', true ) );

		// Additional check for Data Liberation.
		$this->assertEquals( 'A WordPress Commenter', $comments[0]->comment_author );
		$this->assertEquals( 2, $comments[0]->comment_ID );
		$this->assertEquals( 10, $comments[0]->comment_post_ID );
	}

	/**
	 * This is a WordPress core importer test.
	 *
	 * @see https://github.com/WordPress/wordpress-importer/blob/master/phpunit/tests/postmeta.php
	 */
	public function test_serialized_postmeta_no_cdata() {
		$wxr_path = __DIR__ . '/wxr/test-serialized-postmeta-no-cdata.xml';
		$importer = WP_Stream_Importer::create_for_wxr_file( $wxr_path );

		do {
			while ( $importer->next_step( 1 ) ) {
				// noop
			}
		} while ( $importer->advance_to_next_stage() );

		$expected = array(
			'special_post_title' => 'A special title',
			'is_calendar'        => '',
		);
		// $this->assertSame( $expected, get_post_meta( 122, 'post-options', true ) );
	}

	/*public function test_import_one_post() {
		$sorter = new WP_Topological_Sorter();

		$this->assertTrue( $sorter->map_post( 0, $this->generate_post( 1 ) ) );
		$this->assertEquals( 1, $sorter->get_total_posts() );
		$this->assertEquals( 1, $sorter->next_post()['byte_offset'] );
	}

	public function test_parent_after_child() {
		$sorter = new WP_Topological_Sorter();

		$sorter->map_post( 10, $this->generate_post( 1, 2 ) );
		$sorter->map_post( 20, $this->generate_post( 2, 0 ) );
		$sorter->sort_topologically();

		// $this->assertEquals( array( 2 => 20, 1 => 10 ), $sorter->posts );
		$this->assertEquals( 10, $sorter->next_post()['byte_offset'] );
		$this->assertEquals( 20, $sorter->next_post()['byte_offset'] );
		$this->assertFalse( $sorter->is_sorted() );
	}

	public function test_child_after_parent() {
		$sorter = new WP_Topological_Sorter();

		$sorter->map_post( 10, $this->generate_post( 1, 0 ) );
		$sorter->map_post( 20, $this->generate_post( 2, 1 ) );
		$sorter->map_post( 30, $this->generate_post( 3, 2 ) );
		$sorter->sort_topologically();

		// $this->assertEquals( array( 1 => 10, 2 => 20, 3 => 30 ), $sorter->posts );
		$this->assertEquals( 10, $sorter->next_post()['byte_offset'] );
	}

	public function test_orphaned_post() {
		$sorter = new WP_Topological_Sorter();

		$sorter->map_post( 10, $this->generate_post( 1, 3 ) );
		$sorter->map_post( 20, $this->generate_post( 2, 0 ) );
		$sorter->sort_topologically();

		// $this->assertEquals( array( 1 => 10, 2 => 20 ), $sorter->posts );
		$this->assertEquals( 10, $sorter->next_post()['byte_offset'] );
		$this->assertEquals( 20, $sorter->next_post()['byte_offset'] );
	}

	public function test_chain_parent_child_after() {
		$sorter = new WP_Topological_Sorter();

		$sorter->map_post( 10, $this->generate_post( 1, 2 ) );
		$sorter->map_post( 20, $this->generate_post( 2, 3 ) );
		$sorter->map_post( 30, $this->generate_post( 3, 0 ) );
		$sorter->sort_topologically();

		// $this->assertEquals( array( 3 => 30, 2 => 20, 1 => 10 ), $sorter->posts );
	}

	public function test_reverse_order() {
		$sorter = new WP_Topological_Sorter();

		$this->multiple_map_posts( $sorter, array( 3, 2, 1 ) );
		$sorter->sort_topologically();

		// $this->assertEquals( array( 1 => 10, 2 => 20, 3 => 30 ), $sorter->posts );
	}

	public function test_get_byte_offsets_consume_array() {
		$sorter = new WP_Topological_Sorter();

		$this->multiple_map_posts( $sorter, array( 2, 3, 0 ) );
		$sorter->sort_topologically();

		// $this->assertEquals( array( 3 => 30, 2 => 20, 1 => 10 ), $sorter->posts );

		$this->assertEquals( 10, $sorter->next_post()['byte_offset'] );
		$this->assertEquals( 20, $sorter->next_post()['byte_offset'] );
		$this->assertEquals( 30, $sorter->next_post()['byte_offset'] );
		$this->assertEquals( 0, $sorter->get_total_posts() );
	}

	/**
	 * This map a list of posts [3, 2, 1] of the form:
	 *   post_id: 1, 2, 3
	 *   post_parent: 3, 2, 1
	 *   byte_offset: 10, 20, 30
	 *
	private function multiple_map_posts( $sorter, $parents ) {
		foreach ( $parents as $i => $parent ) {
			$post = $this->generate_post( $i + 1, $parent );
			$sorter->map_post( 10 * $i + 10, $post );
		}
	}*/

	private function generate_post( $id, $post_parent = 0, $type = 'post' ) {
		return array(
			'post_id'     => $id,
			'post_parent' => $post_parent,
			'post_type'   => $type,
		);
	}
}
