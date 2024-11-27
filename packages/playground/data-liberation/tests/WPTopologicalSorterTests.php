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

		$this->assertEquals( array( 2 => 1 ), $sorter->posts );
		$this->assertFalse( $sorter->get_byte_offset( 1 ) );
		$this->assertEquals( 1, $sorter->get_byte_offset( 2 ) );
	}

	public function test_child_after_parent() {
		$sorter = new WP_Topological_Sorter();

		$sorter->map_post( 10, $this->generate_post( 1, 0 ) );
		$sorter->map_post( 20, $this->generate_post( 2, 1 ) );
		$sorter->map_post( 30, $this->generate_post( 3, 2 ) );
		$sorter->sort_topologically();

		$this->assertEquals( array(), $sorter->posts );
		$this->assertFalse( $sorter->get_byte_offset( 1 ) );
	}

	public function test_orphaned_post() {
		$sorter = new WP_Topological_Sorter();

		$sorter->map_post( 10, $this->generate_post( 1, 3 ) );
		$sorter->map_post( 20, $this->generate_post( 2, 0 ) );
		$sorter->sort_topologically();

		$this->assertEquals( array( 2 => 20 ), $sorter->posts );
		$this->assertEquals( 20, $sorter->get_byte_offset( 2 ) );
	}

	public function test_chain_parent_child_after() {
		$sorter = new WP_Topological_Sorter();

		$sorter->map_post( 10, $this->generate_post( 1, 2 ) );
		$sorter->map_post( 20, $this->generate_post( 2, 3 ) );
		$sorter->map_post( 30, $this->generate_post( 3, 0 ) );
		$sorter->sort_topologically();

		$this->assertEquals( array( 3 => 30 ), $sorter->posts );
	}

	public function test_reverse_order() {
		$sorter = new WP_Topological_Sorter();

		$this->multiple_map_posts( $sorter, array( 3, 2, 1 ) );
		$sorter->sort_topologically();

		$this->assertEquals( array(), $sorter->posts );
	}

	/**
	 * This map a list of posts [3, 2, 1] of the form:
	 *   post_id: 1, 2, 3
	 *   post_parent: 3, 2, 1
	 *   byte_offset: 10, 20, 30
	 */
	private function multiple_map_posts( $sorter, $parents ) {
		foreach ( $parents as $i => $parent ) {
			$post = $this->generate_post( $i + 1, $parent );
			$sorter->map_post( 10 * $parent + 10, $post );
		}
	}

	private function generate_post( $id, $post_parent = 0, $type = 'post' ) {
		return array(
			'post_id'     => $id,
			'post_parent' => $post_parent,
			'post_type'   => $type,
		);
	}
}
