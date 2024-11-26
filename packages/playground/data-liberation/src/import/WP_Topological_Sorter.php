<?php

/**
 * The topological sorter class.
 *
 * We create an in-memory index that contains offsets and lengths of items in the WXR.
 * The indexer will also topologically sort posts so that the order we iterate over posts
 * ensures we always get parents before their children.
 */
class WP_Topological_Sorter {

	public $unsorted_posts      = array();
	public $unsorted_categories = array();
	public $category_index      = array();
	public $post_index          = array();

	/**
	 * Variable for keeping counts of orphaned posts/attachments, it'll also be assigned as temporarty post ID.
	 * To prevent duplicate post ID, we'll use negative number.
	 *
	 * @var int
	 */
	protected $orphan_post_counter = 0;

	/**
	 * Store the ID of the post ID currently being processed.
	 * @var int
	 */
	protected $last_post_id = 0;

	public function map_category( $byte_offset, $data ) {
		if ( empty( $data ) ) {
			return false;
		}

		$this->unsorted_categories[ $data['slug'] ] = array(
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

			$this->unsorted_posts[ $data['post_id'] ] = array(
				'byte_offset' => $byte_offset,
				'parent'      => $data['post_parent'],
				'visited'     => false,
			);
		}
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
		foreach ( $this->unsorted_categories as $slug => $category ) {
			$this->topological_category_sort( $slug, $category );
		}

		foreach ( $this->unsorted_posts as $id => $post ) {
			$this->topological_post_sort( $id, $post );
		}

		// Empty the unsorted posts
		$this->unsorted_posts = array();
	}

	/**
	 * Recursive posts topological sorting.
	 *
	 * @param int $id     The id of the post to sort.
	 * @param array $post The post to sort.
	 *
	 * @todo Check for circular dependencies.
	 */
	private function topological_post_sort( $id, $post ) {
		if ( isset( $this->unsorted_posts[ $id ]['visited'] ) ) {
			return;
		}

		$this->unsorted_posts[ $id ]['visited'] = true;

		if ( isset( $this->unsorted_posts[ $post['parent'] ] ) ) {
			$this->topological_post_sort( $post['parent'], $this->unsorted_posts[ $post['parent'] ] );
		}

		$this->post_index[] = $post['byte_offset'];
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
		if ( isset( $this->unsorted_categories[ $slug ]['visited'] ) ) {
			return;
		}

		$this->unsorted_categories[ $slug ]['visited'] = true;

		if ( isset( $this->unsorted_categories[ $category['parent'] ] ) ) {
			$this->topological_category_sort( $category['parent'], $this->unsorted_categories[ $category['parent'] ] );
		}

		$this->category_index[] = $category['byte_offset'];
	}
}
