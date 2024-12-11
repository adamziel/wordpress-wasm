<?php

require_once __DIR__ . '/PlaygroundTestCase.php';

/**
 * Tests for the WP_Topological_Sorter class.
 */
class WPTopologicalSorterTests extends PlaygroundTestCase {

	protected function setUp(): void {
		parent::setUp();

		$this->delete_all_data();
		wp_cache_flush();
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
		$this->import_wxr_file( __DIR__ . '/wxr/test-serialized-comment-meta.xml' );

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
		$this->import_wxr_file( __DIR__ . '/wxr/test-serialized-postmeta-no-cdata.xml' );

		$expected = array(
			'special_post_title' => 'A special title',
			'is_calendar'        => '',
		);
		$this->assertSame( $expected, get_post_meta( 122, 'post-options', true ) );
	}

	/**
	 * This is a WordPress core importer test.
	 *
	 * @see https://github.com/WordPress/wordpress-importer/blob/master/phpunit/tests/postmeta.php
	 */
	public function test_utw_postmeta() {
		$this->import_wxr_file( __DIR__ . '/wxr/test-utw-post-meta-import.xml' );

		$tags = array(
			'album',
			'apple',
			'art',
			'artwork',
			'dead-tracks',
			'ipod',
			'itunes',
			'javascript',
			'lyrics',
			'script',
			'tracks',
			'windows-scripting-host',
			'wscript',
		);

		$expected = array();
		foreach ( $tags as $tag ) {
			$classy      = new StdClass();
			$classy->tag = $tag;
			$expected[]  = $classy;
		}

		$this->assertEquals( $expected, get_post_meta( 150, 'test', true ) );
	}

	/**
	 * This is a WordPress core importer test.
	 *
	 * @see https://github.com/WordPress/wordpress-importer/blob/master/phpunit/tests/postmeta.php
	 */
	public function test_serialized_postmeta_with_cdata() {
		$this->import_wxr_file( __DIR__ . '/wxr/test-serialized-postmeta-with-cdata.xml' );

		// HTML in the CDATA should work with old WordPress version.
		$this->assertSame( '<pre>some html</pre>', get_post_meta( 10, 'contains-html', true ) );
		// Serialised will only work with 3.0 onwards.
		$expected = array(
			'special_post_title' => 'A special title',
			'is_calendar'        => '',
		);
		$this->assertSame( $expected, get_post_meta( 10, 'post-options', true ) );
	}

	/**
	 * This is a WordPress core importer test.
	 *
	 * @see https://github.com/WordPress/wordpress-importer/blob/master/phpunit/tests/postmeta.php
	 */
	public function test_serialized_postmeta_with_evil_stuff_in_cdata() {
		$this->import_wxr_file( __DIR__ . '/wxr/test-serialized-postmeta-with-cdata.xml' );

		// Evil content in the CDATA.
		$this->assertSame( '<wp:meta_value>evil</wp:meta_value>', get_post_meta( 10, 'evil', true ) );
	}

	/**
	 * This is a WordPress core importer test.
	 *
	 * @see https://github.com/WordPress/wordpress-importer/blob/master/phpunit/tests/postmeta.php
	 */
	public function test_serialized_postmeta_with_slashes() {
		$this->import_wxr_file( __DIR__ . '/wxr/test-serialized-postmeta-with-cdata.xml' );

		$expected_integer      = '1';
		$expected_string       = '¯\_(ツ)_/¯';
		$expected_array        = array( 'key' => '¯\_(ツ)_/¯' );
		$expected_array_nested = array(
			'key' => array(
				'foo' => '¯\_(ツ)_/¯',
				'bar' => '\o/',
			),
		);

		// $this->assertSame( $expected_string, get_post_meta( 10, 'string', true ) );
		// $this->assertSame( $expected_array, get_post_meta( 10, 'array', true ) );
		// $this->assertSame( $expected_array_nested, get_post_meta( 10, 'array-nested', true ) );
		// $this->assertSame( $expected_integer, get_post_meta( 10, 'integer', true ) );
	}

	/**
	 * This is a WordPress core importer test.
	 *
	 * @see https://github.com/WordPress/wordpress-importer/blob/master/phpunit/tests/term-meta.php
	 */
	public function test_serialized_term_meta() {
		register_taxonomy( 'custom_taxonomy', array( 'post' ) );

		$this->import_wxr_file( __DIR__ . '/wxr/test-serialized-term-meta.xml' );

		$expected_string = '¯\_(ツ)_/¯';
		$expected_array  = array( 'key' => '¯\_(ツ)_/¯' );

		// $term = get_term_by( 'slug', 'post_tag', 'post_tag' );
		// $this->assertInstanceOf( 'WP_Term', $term );
		// $this->assertSame( $expected_string, get_term_meta( $term->term_id, 'string', true ) );
		// $this->assertSame( $expected_array, get_term_meta( $term->term_id, 'array', true ) );

		// $term = get_term_by( 'slug', 'category', 'category' );
		// $this->assertInstanceOf( 'WP_Term', $term );
		// $this->assertSame( $expected_string, get_term_meta( $term->term_id, 'string', true ) );
		// $this->assertSame( $expected_array, get_term_meta( $term->term_id, 'array', true ) );

		// $term = get_term_by( 'slug', 'custom_taxonomy', 'custom_taxonomy' );
		// $this->assertInstanceOf( 'WP_Term', $term );
		// $this->assertSame( $expected_string, get_term_meta( $term->term_id, 'string', true ) );
		// $this->assertSame( $expected_array, get_term_meta( $term->term_id, 'array', true ) );
	}

	/**
	 * Import a WXR file.
	 */
	private function import_wxr_file( string $wxr_path ) {
		$importer = WP_Stream_Importer::create_for_wxr_file( $wxr_path );

		do {
			while ( $importer->next_step( 1 ) ) {
				// noop
			}
		} while ( $importer->advance_to_next_stage() );
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