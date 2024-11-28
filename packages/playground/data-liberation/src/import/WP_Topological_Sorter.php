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

	/**
	 * Whether the sort has been done.
	 *
	 * @var bool
	 */
	protected $sorted = false;

	public function reset() {
		$this->posts               = array();
		$this->categories          = array();
		$this->category_index      = array();
		$this->orphan_post_counter = 0;
		$this->last_post_id        = 0;
		$this->sorted              = false;
	}

	public function map_category( $byte_offset, $data ) {
		if ( empty( $data ) ) {
			return false;
		}

		$this->categories[ $data['slug'] ] = array(
			'parent'      => $data['parent'],
			'byte_offset' => $byte_offset,
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

			// This is an array saved as: [ parent, byte_offset ], to save
			// space and not using an associative one.
			$this->posts[ $data['post_id'] ] = array(
				$data['post_parent'],
				$byte_offset,
			);
		}

		return true;
	}

	/**
	 * Get the byte offset of an element, and remove it from the list.
	 */
	public function get_byte_offset( $id ) {
		if ( ! $this->sorted ) {
			return false;
		}

		if ( isset( $this->posts[ $id ] ) ) {
			$ret = $this->posts[ $id ];

			// Remove the element from the array.
			unset( $this->posts[ $id ] );

			if ( 0 === count( $this->posts ) ) {
				// All posts have been processed.
				$this->reset();
			}

			return $ret;
		}

		return false;
	}

	public function is_sorted() {
		return $this->sorted;
	}

	/**
	 * Sort posts topologically.
	 *
	 * Children posts should not be processed before their parent has been processed.
	 * This method sorts the posts in the order they should be processed.
	 *
	 * Sorted posts will be stored as attachments and posts/pages separately.
	 */
	public function sort_topologically( $free_space = true ) {
		foreach ( $this->categories as $slug => $category ) {
			$this->topological_category_sort( $slug, $category );
		}

		$this->sort_elements( $this->posts );

		// Free some space.
		if ( $free_space ) {
			/**
			 * @TODO: all the elements that have not been moved can be flushed away.
			 */
			foreach ( $this->posts as $id => $element ) {
				// Save only the byte offset.
				$this->posts[ $id ] = $element[1];
			}
		}

		$this->sorted = true;
	}

	/**
	 * Recursive sort elements. Posts with parents will be moved to the correct position.
	 *
	 * @return true
	 */
	private function sort_elements( &$elements ) {
		$sort_callback = function ( $a, $b ) use ( &$elements ) {
			$parent_a = $elements[ $a ][0];
			$parent_b = $elements[ $b ][0];

			if ( ! $parent_a && ! $parent_b ) {
				// No parents.
				return 0;
			} elseif ( $a === $parent_b ) {
				// A is the parent of B.
				return -1;
			} elseif ( $b === $parent_a ) {
				// B is the parent of A.
				return 1;
			}

			return 0;
		};

		/**
		 * @TODO: PHP uses quicksort: https://github.com/php/php-src/blob/master/Zend/zend_sort.c
		 * WordPress export posts by ID and so are likely to be already in order.
		 * Quicksort performs badly on already sorted arrays, O(n^2) is the worst case.
		 * Let's consider using a different sorting algorithm.
		 */
		uksort( $elements, $sort_callback );
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
