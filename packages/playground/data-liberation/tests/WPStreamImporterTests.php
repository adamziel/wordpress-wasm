<?php

use PHPUnit\Framework\TestCase;

/**
 * Tests for the WPStreamImporter class.
 */
class WPStreamImporterTests extends TestCase {

	protected function setUp(): void {
		parent::setUp();

		if ( ! isset( $_SERVER['SERVER_SOFTWARE'] ) || $_SERVER['SERVER_SOFTWARE'] !== 'PHP.wasm' ) {
			$this->markTestSkipped( 'Test only runs in Playground' );
		}

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
	 * @before
	 *
	 * TODO: Run each test in a fresh Playground instance instead of sharing the global
	 * state like this.
	 */
	public function clean_up_uploads(): void {
		$files = glob( '/wordpress/wp-content/uploads/*' );
		foreach ( $files as $file ) {
			if ( is_dir( $file ) ) {
				array_map( 'unlink', glob( "$file/*.*" ) );
				rmdir( $file );
			} else {
				unlink( $file );
			}
		}
	}

	public function test_frontloading() {
		$wxr_path = __DIR__ . '/wxr/frontloading-1-attachment.xml';
		$importer = WP_Stream_Importer::create_for_wxr_file( $wxr_path );
		$this->skip_to_stage( $importer, WP_Stream_Importer::STAGE_FRONTLOAD_ASSETS );
		while ( $importer->next_step() ) {
			// noop
		}
		$files = glob( '/wordpress/wp-content/uploads/*' );
		$this->assertCount( 1, $files );
		$this->assertStringEndsWith( '.jpg', $files[0] );
	}

	public function test_resume_frontloading() {
		$wxr_path = __DIR__ . '/wxr/frontloading-1-attachment.xml';
		$importer = WP_Stream_Importer::create_for_wxr_file( $wxr_path );
		$this->skip_to_stage( $importer, WP_Stream_Importer::STAGE_FRONTLOAD_ASSETS );

		$progress_url   = null;
		$progress_value = null;
		for ( $i = 0; $i < 20; ++$i ) {
			$importer->next_step();
			$progress = $importer->get_frontloading_progress();
			if ( count( $progress ) === 0 ) {
				continue;
			}
			$progress_url   = array_keys( $progress )[0];
			$progress_value = array_values( $progress )[0];
			if ( null === $progress_value['received'] ) {
				continue;
			}
			break;
		}

		$this->assertIsArray( $progress_value );
		$this->assertIsInt( $progress_value['received'] );
		$this->assertEquals( 'https://wpthemetestdata.files.wordpress.com/2008/06/canola2.jpg', $progress_url );
		$this->assertGreaterThan( 0, $progress_value['total'] );

		$cursor   = $importer->get_reentrancy_cursor();
		$importer = WP_Stream_Importer::create_for_wxr_file( $wxr_path, array(), $cursor );
		// Rewind back to the entity we were on.
		$this->assertTrue( $importer->next_step() );

		// Restart the download of the same entity - from scratch.
		$progress_value = array();
		for ( $i = 0; $i < 20; ++$i ) {
			$importer->next_step();
			$progress = $importer->get_frontloading_progress();
			if ( count( $progress ) === 0 ) {
				continue;
			}
			$progress_url   = array_keys( $progress )[0];
			$progress_value = array_values( $progress )[0];
			if ( null === $progress_value['received'] ) {
				continue;
			}
			break;
		}

		$this->assertIsInt( $progress_value['received'] );
		$this->assertEquals( 'https://wpthemetestdata.files.wordpress.com/2008/06/canola2.jpg', $progress_url );
		$this->assertGreaterThan( 0, $progress_value['total'] );
	}

	/**
	 * Test resume entity import.
	 */
	public function test_resume_entity_import() {
		$wxr_path = __DIR__ . '/wxr/entities-options-and-posts.xml';
		$importer = WP_Stream_Importer::create_for_wxr_file( $wxr_path );
		$this->skip_to_stage( $importer, WP_Stream_Importer::STAGE_IMPORT_ENTITIES );

		for ( $i = 0; $i < 11; ++$i ) {
			$this->assertTrue( $importer->next_step() );
			$cursor   = $importer->get_reentrancy_cursor();
			$importer = WP_Stream_Importer::create_for_wxr_file( $wxr_path, array(), $cursor );
			// Rewind back to the entity we were on.
			// Note this means we may attempt to insert it twice. It's
			// the importer's job to detect that and skip the duplicate
			// insertion.
			$this->assertTrue( $importer->next_step() );
		}
		$this->assertFalse( $importer->next_step() );
	}

	public function test_sort_categories() {
		$wxr_path = __DIR__ . '/wxr/mixed-categories.xml';
		$importer = WP_Stream_Importer::create_for_wxr_file( $wxr_path );
		$this->skip_to_stage( $importer, WP_Stream_Importer::STAGE_TOPOLOGICAL_SORT );

		while ( $importer->next_step() ) {
			if ( $importer->get_next_stage() === WP_Stream_Importer::STAGE_FRONTLOAD_ASSETS ) {
				break;
			}
		}
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
		/*$this->_import_wp( DIR_TESTDATA_WP_IMPORTER . '/test-serialized-postmeta-no-cdata.xml', array( 'johncoswell' => 'john' ) );
		$expected['special_post_title'] = 'A special title';
		$expected['is_calendar']        = '';
		$this->assertSame( $expected, get_post_meta( 122, 'post-options', true ) );*/
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
		$this->assertSame( $expected, get_post_meta( 122, 'post-options', true ) );
	}

	private function skip_to_stage( WP_Stream_Importer $importer, string $stage ) {
		do {
			while ( $importer->next_step() ) {
				// noop
			}
			if ( $importer->get_next_stage() === $stage ) {
				break;
			}
		} while ( $importer->advance_to_next_stage() );
		$this->assertEquals( $stage, $importer->get_next_stage() );
		$this->assertTrue( $importer->advance_to_next_stage() );
	}
}
