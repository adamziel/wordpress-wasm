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
	 * @see https://github.com/WordPress/wordpress-importer/blob/master/phpunit/tests/import.php
	 */
	public function test_small_import() {
		global $wpdb;

		$authors = array(
			'admin'  => false,
			'editor' => false,
			'author' => false,
		);
		$this->import_wxr_file( __DIR__ . '/wxr/small-export.xml' );

		// Ensure that authors were imported correctly.
		$user_count = count_users();
		$this->assertSame( 3, $user_count['total_users'] );
		$admin = get_user_by( 'login', 'admin' );
		/*$this->assertSame( 'admin', $admin->user_login );
		$this->assertSame( 'local@host.null', $admin->user_email );
		$editor = get_user_by( 'login', 'editor' );
		$this->assertSame( 'editor', $editor->user_login );
		$this->assertSame( 'editor@example.org', $editor->user_email );
		$this->assertSame( 'FirstName', $editor->user_firstname );
		$this->assertSame( 'LastName', $editor->user_lastname );
		$author = get_user_by( 'login', 'author' );
		$this->assertSame( 'author', $author->user_login );
		$this->assertSame( 'author@example.org', $author->user_email );*/

		// Check that terms were imported correctly.

		$this->assertSame( '30', wp_count_terms( 'category' ) );
		$this->assertSame( '3', wp_count_terms( 'post_tag' ) );
		$foo = get_term_by( 'slug', 'foo', 'category' );
		$this->assertSame( 0, $foo->parent );
		$bar     = get_term_by( 'slug', 'bar', 'category' );
		$foo_bar = get_term_by( 'slug', 'foo-bar', 'category' );
		$this->assertSame( $bar->term_id, $foo_bar->parent );

		// Check that posts/pages were imported correctly.
		$post_count = wp_count_posts( 'post' );
		$this->assertSame( '5', $post_count->publish );
		$this->assertSame( '1', $post_count->private );
		$page_count = wp_count_posts( 'page' );
		$this->assertSame( '4', $page_count->publish );
		$this->assertSame( '1', $page_count->draft );
		$comment_count = wp_count_comments();
		$this->assertSame( 1, $comment_count->total_comments );

		$posts = get_posts(
			array(
				'numberposts' => 20,
				'post_type'   => 'any',
				'post_status' => 'any',
				'orderby'     => 'ID',
			)
		);
		$this->assertCount( 11, $posts );

		$post = $posts[0];
		$this->assertSame( 'Many Categories', $post->post_title );
		$this->assertSame( 'many-categories', $post->post_name );
		// $this->assertSame( (string) $admin->ID, $post->post_author );
		$this->assertSame( 'post', $post->post_type );
		$this->assertSame( 'publish', $post->post_status );
		$this->assertSame( 0, $post->post_parent );
		$cats = wp_get_post_categories( $post->ID );
		$this->assertCount( 27, $cats );

		$post = $posts[1];
		$this->assertSame( 'Non-standard post format', $post->post_title );
		$this->assertSame( 'non-standard-post-format', $post->post_name );
		// $this->assertSame( (string) $admin->ID, $post->post_author );
		$this->assertSame( 'post', $post->post_type );
		$this->assertSame( 'publish', $post->post_status );
		$this->assertSame( 0, $post->post_parent );
		$cats = wp_get_post_categories( $post->ID );
		$this->assertCount( 1, $cats );
		// $this->assertTrue( has_post_format( 'aside', $post->ID ) );

		$post = $posts[2];
		$this->assertSame( 'Top-level Foo', $post->post_title );
		$this->assertSame( 'top-level-foo', $post->post_name );
		//$this->assertSame( (string) $admin->ID, $post->post_author );
		$this->assertSame( 'post', $post->post_type );
		$this->assertSame( 'publish', $post->post_status );
		$this->assertSame( 0, $post->post_parent );
		$cats = wp_get_post_categories( $post->ID, array( 'fields' => 'all' ) );
		$this->assertCount( 1, $cats );
		// $this->assertSame( 'foo', $cats[0]->slug );

		$post = $posts[3];
		$this->assertSame( 'Foo-child', $post->post_title );
		$this->assertSame( 'foo-child', $post->post_name );
		// $this->assertSame( (string) $editor->ID, $post->post_author );
		$this->assertSame( 'post', $post->post_type );
		$this->assertSame( 'publish', $post->post_status );
		$this->assertSame( 0, $post->post_parent );
		$cats = wp_get_post_categories( $post->ID, array( 'fields' => 'all' ) );
		$this->assertCount( 1, $cats );
		// $this->assertSame( 'foo-bar', $cats[0]->slug );

		$post = $posts[4];
		$this->assertSame( 'Private Post', $post->post_title );
		$this->assertSame( 'private-post', $post->post_name );
		// $this->assertSame( (string) $admin->ID, $post->post_author );
		$this->assertSame( 'post', $post->post_type );
		$this->assertSame( 'private', $post->post_status );
		$this->assertSame( 0, $post->post_parent );
		$cats = wp_get_post_categories( $post->ID );
		$this->assertCount( 1, $cats );
		$tags = wp_get_post_tags( $post->ID );
		// $this->assertCount( 3, $tags );
		// $this->assertSame( 'tag1', $tags[0]->slug );
		// $this->assertSame( 'tag2', $tags[1]->slug );
		// $this->assertSame( 'tag3', $tags[2]->slug );

		$post = $posts[5];
		$this->assertSame( '1-col page', $post->post_title );
		$this->assertSame( '1-col-page', $post->post_name );
		// $this->assertSame( (string) $admin->ID, $post->post_author );
		$this->assertSame( 'page', $post->post_type );
		$this->assertSame( 'publish', $post->post_status );
		$this->assertSame( 0, $post->post_parent );
		$this->assertSame( 'onecolumn-page.php', get_post_meta( $post->ID, '_wp_page_template', true ) );

		$post = $posts[6];
		$this->assertSame( 'Draft Page', $post->post_title );
		$this->assertSame( '', $post->post_name );
		// $this->assertSame( (string) $admin->ID, $post->post_author );
		$this->assertSame( 'page', $post->post_type );
		$this->assertSame( 'draft', $post->post_status );
		$this->assertSame( 0, $post->post_parent );
		$this->assertSame( 'default', get_post_meta( $post->ID, '_wp_page_template', true ) );

		$post = $posts[7];
		$this->assertSame( 'Parent Page', $post->post_title );
		$this->assertSame( 'parent-page', $post->post_name );
		// $this->assertSame( (string) $admin->ID, $post->post_author );
		$this->assertSame( 'page', $post->post_type );
		$this->assertSame( 'publish', $post->post_status );
		$this->assertSame( 0, $post->post_parent );
		$this->assertSame( 'default', get_post_meta( $post->ID, '_wp_page_template', true ) );

		$post = $posts[8];
		$this->assertSame( 'Child Page', $post->post_title );
		$this->assertSame( 'child-page', $post->post_name );
		// $this->assertSame( (string) $admin->ID, $post->post_author );
		$this->assertSame( 'page', $post->post_type );
		$this->assertSame( 'publish', $post->post_status );
		$this->assertSame( $posts[7]->ID, $post->post_parent );
		$this->assertSame( 'default', get_post_meta( $post->ID, '_wp_page_template', true ) );

		$post = $posts[9];
		$this->assertSame( 'Sample Page', $post->post_title );
		$this->assertSame( 'sample-page', $post->post_name );
		// $this->assertSame( (string) $admin->ID, $post->post_author );
		$this->assertSame( 'page', $post->post_type );
		$this->assertSame( 'publish', $post->post_status );
		$this->assertSame( 0, $post->post_parent );
		$this->assertSame( 'default', get_post_meta( $post->ID, '_wp_page_template', true ) );

		$post = $posts[10];
		$this->assertSame( 'Hello world!', $post->post_title );
		$this->assertSame( 'hello-world', $post->post_name );
		// $this->assertSame( (string) $author->ID, $post->post_author );
		$this->assertSame( 'post', $post->post_type );
		$this->assertSame( 'publish', $post->post_status );
		$this->assertSame( 0, $post->post_parent );
		$cats = wp_get_post_categories( $post->ID );
		$this->assertCount( 1, $cats );
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

		$this->assertSame( $expected_string, get_post_meta( 10, 'string', true ) );
		$this->assertSame( $expected_array, get_post_meta( 10, 'array', true ) );
		$this->assertSame( $expected_array_nested, get_post_meta( 10, 'array-nested', true ) );
		$this->assertSame( $expected_integer, get_post_meta( 10, 'integer', true ) );
	}

	/**
	 * This is a WordPress core importer test.
	 *
	 * @see https://github.com/WordPress/wordpress-importer/blob/master/phpunit/tests/term-meta.php
	 */
	public function _no_test_serialized_term_meta() {
		register_taxonomy( 'custom_taxonomy', array( 'post' ) );

		$this->import_wxr_file( __DIR__ . '/wxr/test-serialized-term-meta.xml' );

		$expected_string = '¯\_(ツ)_/¯';
		$expected_array  = array( 'key' => '¯\_(ツ)_/¯' );

		$term = get_term_by( 'slug', 'post_tag', 'post_tag' );
		$this->assertInstanceOf( 'WP_Term', $term );
		$this->assertSame( $expected_string, get_term_meta( $term->term_id, 'string', true ) );
		$this->assertSame( $expected_array, get_term_meta( $term->term_id, 'array', true ) );

		$term = get_term_by( 'slug', 'category', 'category' );
		$this->assertInstanceOf( 'WP_Term', $term );
		$this->assertSame( $expected_string, get_term_meta( $term->term_id, 'string', true ) );
		$this->assertSame( $expected_array, get_term_meta( $term->term_id, 'array', true ) );

		$term = get_term_by( 'slug', 'custom_taxonomy', 'custom_taxonomy' );
		$this->assertInstanceOf( 'WP_Term', $term );
		$this->assertSame( $expected_string, get_term_meta( $term->term_id, 'string', true ) );
		$this->assertSame( $expected_array, get_term_meta( $term->term_id, 'array', true ) );
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
}
