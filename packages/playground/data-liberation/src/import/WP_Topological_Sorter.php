<?php

/**
 * The topological sorter class.
 *
 * We create an in-memory index that contains offsets and lengths of items in the WXR.
 * The indexer will also topologically sort posts so that the order we iterate over posts
 * ensures we always get parents before their children.
 */
class WP_Topological_Sorter {

	public $posts          = array();
	public $categories     = array();
	public $category_index = array();

	/**
	 * Variable for keeping counts of orphaned posts/attachments, it'll also be assigned as temporarly post ID.
	 * To prevent duplicate post ID, we'll use negative number.
	 *
	 * @var int
	 */
	protected $orphan_post_counter = 0;

	/**
	 * Store the ID of the post ID currently being processed.
	 *
	 * @var int
	 */
	protected $last_post_id = 0;

	public function reset() {
		$this->posts               = array();
		$this->categories          = array();
		$this->category_index      = array();
		$this->orphan_post_counter = 0;
		$this->last_post_id        = 0;
	}

	public function map_category( $byte_offset, $data ) {
		if ( empty( $data ) ) {
			return false;
		}

		$this->categories[ $data['slug'] ] = array(
			'byte_offset' => $byte_offset,
			'parent'      => $data['parent'],
			'visited'     => false,
		);
	}

	public function map_post( $byte_offset, $data ) {
		if ( empty( $data ) ) {
			return false;
		}

		// No parent, no need to sort.
		if ( ! isset( $data['post_type'] ) ) {
			return false;
		}

		if ( 'post' === $data['post_type'] || 'page' === $data['post_type'] ) {
			if ( ! $data['post_id'] ) {
				$this->last_post_id = $this->orphan_post_counter;
				--$this->orphan_post_counter;
			}

			// This is an array saved as: [ parent, byte_offset ], to save space and not using an associative one.
			$this->posts[ $data['post_id'] ] = array(
				$data['post_parent'],
				$byte_offset,
			);
		}

		return true;
	}

	/**
	 * Sort posts topologically.
	 *
	 * Children posts should not be processed before their parent has been processed.
	 * This method sorts the posts in the order they should be processed.
	 *
	 * Sorted posts will be stored as attachments and posts/pages separately.
	 */
	public function sort_topologically() {
		foreach ( $this->categories as $slug => $category ) {
			$this->topological_category_sort( $slug, $category );
		}

		$this->sort_parent_child( $this->posts );

		// Empty some memory.
		foreach ( $this->posts as $id => $element ) {
			// Save only the byte offset.
			$this->posts[ $id ] = $element[1];
		}
	}

	/**
	 * Recursive topological sorting.
	 * @todo Check for circular dependencies.
	 *
	 * @param array $elements The elements to sort.
	 *
	 * @return void
	 */
	private function sort_parent_child( &$elements ) {
		// Sort the array in-place.
		$position = 0;

		foreach ( $elements as $id => $element ) {
			if ( empty( $element[0] ) ) {
				$this->move_element( $elements, $id, $position );
			}
		}
	}

	/**
	 * Move an element to a new position.
	 *
	 * @param array $elements The elements to sort.
	 * @param int $id The ID of the element to move.
	 * @param int $position The new position of the element.
	 *
	 * @return void
	 */
	private function move_element( &$elements, $id, &$position ) {
		if ( ! isset( $elements[ $id ] ) ) {
			return;
		}

		$element = $elements[ $id ];

		if ( $id < $position ) {
			// Already in the correct position.
			return;
		}

		// Move the element to the current position.
		unset( $elements[ $id ] );

		// Generate the new array.
		$elements = array_slice( $elements, 0, $position, true ) +
			array( $id => $element ) +
			array_slice( $elements, $position, null, true );

		++$position;

		// Move children.
		foreach ( $elements as $child_id => $child_element ) {
			if ( $id === $child_element[0] ) {
				$this->move_element( $elements, $child_id, $position );
			}
		}
	}

	/**
	 * Recursive categories topological sorting.
	 *
	 * @param int $slug       The slug of the category to sort.
	 * @param array $category The category to sort.
	 *
	 * @todo Check for circular dependencies.
	 */
	private function topological_category_sort( $slug, $category ) {
		if ( isset( $this->categories[ $slug ]['visited'] ) ) {
			return;
		}

		$this->categories[ $slug ]['visited'] = true;

		if ( isset( $this->categories[ $category['parent'] ] ) ) {
			$this->topological_category_sort( $category['parent'], $this->categories[ $category['parent'] ] );
		}

		$this->category_index[] = $category['byte_offset'];
	}
}
