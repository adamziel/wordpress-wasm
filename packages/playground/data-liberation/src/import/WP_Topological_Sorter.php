<?php

/**
 * The topological sorter class.
 *
 * We create an in-memory index that contains offsets and lengths of items in the WXR.
 * The indexer will also topologically sort posts so that the order we iterate over posts
 * ensures we always get parents before their children.
 */
class WP_Topological_Sorter {

	public $unsorted_posts = array();
	public $terms          = array();
	public $index          = array();

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

	public function map_term( $upstream, $data ) {
		if ( empty( $data ) ) {
			return false;
		}

		$this->terms[ $data['slug'] ] = array(
			'upstream' => $upstream,
			'visited'  => false,
		);
	}

	public function map_post( $upstream, $data ) {
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
				'upstream' => $upstream,
				'parent'   => $data['post_parent'],
				'visited'  => false,
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
	public function sort_posts_topologically() {
		foreach ( $this->unsorted_posts as $id => $post ) {
			$this->topological_sort( $id, $post );
		}

		// Empty the unsorted posts
		$this->unsorted_posts = array();
	}

	/**
	 * Recursive topological sorting.
	 *
	 * @param int $id     The id of the post to sort.
	 * @param array $post The post to sort.
	 *
	 * @todo Check for circular dependencies.
	 */
	private function topological_sort( $id, $post ) {
		if ( isset( $this->posts[ $id ]['visited'] ) ) {
			return;
		}

		$this->unsorted_posts[ $id ]['visited'] = true;

		if ( isset( $this->posts[ $post['parent'] ] ) ) {
			$this->topological_sort( $post['parent'], $this->unsorted_posts[ $post['parent'] ] );
		}

		$this->index[] = $post['upstream'];
	}
}
