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
	}

	public function test_import_simple_wxr() {
		$import = data_liberation_import( __DIR__ . '/wxr/small-export.xml' );

		$this->assertTrue( $import );
	}

	/**
	 * @wip
	 */
	// public function test_resume_frontloading() {
	// 	$wxr_path = __DIR__ . '/wxr/frontloading-1-attachment.xml';
	// 	$importer = WP_Stream_Importer::create_for_wxr_file( $wxr_path );
	//  $this->skip_to_stage( $importer, WP_Stream_Importer::STAGE_FRONTLOAD_ASSETS );
	// 	$this->assertTrue( $importer->advance_to_next_stage() );
	// 	for($i = 0; $i < 20; ++$i) {
	// 		$progress = $importer->get_frontloading_progress();
	// 		if( count( $progress ) === 0 ) {
	// 			continue;
	// 		}
	// 		var_dump( $importer->get_frontloading_events() );
	// 		var_dump( $importer->next_step() );
	// 	}
	// 	$this->assertFalse( true );
	// }

	/**
	 *
	 */
	public function test_resume_entity_import() {
		$wxr_path = __DIR__ . '/wxr/entities-options-and-posts.xml';
		$importer = WP_Stream_Importer::create_for_wxr_file( $wxr_path );
		$this->skip_to_stage( $importer, WP_Stream_Importer::STAGE_IMPORT_ENTITIES );

		for($i = 0; $i < 11; ++$i) {
			$this->assertTrue( $importer->next_step() );
			$cursor = $importer->get_reentrancy_cursor();
			$importer = WP_Stream_Importer::create_for_wxr_file( $wxr_path, [], $cursor );
			// Rewind back to the entity we were on.
			// Note this means we may attempt to insert it twice. It's
			// the importer's job to detect that and skip the duplicate
			// insertion.
			$this->assertTrue( $importer->next_step() );
		}
		$this->assertFalse( $importer->next_step() );
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
