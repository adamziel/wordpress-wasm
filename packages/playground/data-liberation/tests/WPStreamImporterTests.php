<?php

require_once __DIR__ . '/PlaygroundTestCase.php';

use PHPUnit\Framework\TestCase;

/**
 * Tests for the WPStreamImporter class.
 */
class WPStreamImporterTests extends PlaygroundTestCase {

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
