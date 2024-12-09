<?php

use PHPUnit\Framework\TestCase;

/**
 * Tests for the WPTopologicalSorterTests class.
 */
class WPTopologicalSorterTests extends TestCase {

	protected function setUp(): void {
		parent::setUp();

		if ( ! isset( $_SERVER['SERVER_SOFTWARE'] ) || $_SERVER['SERVER_SOFTWARE'] !== 'PHP.wasm' ) {
			$this->markTestSkipped( 'Test only runs in Playground' );
		}
	}

	public function test_import_one_post() {
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
	 */
	private function multiple_map_posts( $sorter, $parents ) {
		foreach ( $parents as $i => $parent ) {
			$post = $this->generate_post( $i + 1, $parent );
			$sorter->map_post( 10 * $i + 10, $post );
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
