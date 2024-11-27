<?php

use PHPUnit\Framework\TestCase;

/**
 * Tests for the WPTopologicalSorterTests class.
 */
class WPTopologicalSorterTests extends TestCase {

	public function test_import_one_post() {
		$sorter = new WP_Topological_Sorter();

		$this->assertTrue( $sorter->map_post( 0, $this->generate_post( 1 ) ) );
		$this->assertCount( 1, $sorter->posts );
		$this->assertEquals( 1, array_keys( $sorter->posts )[0] );
	}

	public function test_parent_after_child() {
		$sorter = new WP_Topological_Sorter();

		$sorter->map_post( 0, $this->generate_post( 1, 2 ) );
		$sorter->map_post( 1, $this->generate_post( 2, 0 ) );
		$sorter->sort_topologically();

		$this->assertEquals( array( 2, 1 ), array_keys( $sorter->posts ) );
		$this->assertEquals(
			array(
				2 => 1,
				1 => 0,
			),
			$sorter->posts
		);
	}

	public function test_child_before_parent() {
		$sorter = new WP_Topological_Sorter();

		$sorter->map_post( 1, $this->generate_post( 2, 0 ) );
		$sorter->map_post( 0, $this->generate_post( 1, 2 ) );
		$sorter->sort_topologically();

		$this->assertEquals( array( 2, 1 ), array_keys( $sorter->posts ) );
		$this->assertEquals(
			array(
				1 => 0,
				2 => 1,
			),
			$sorter->posts
		);
	}

	private function generate_post( $id, $post_parent = 0, $type = 'post' ) {
		return array(
			'post_id'     => $id,
			'post_parent' => $post_parent,
			'post_type'   => $type,
		);
	}
}
