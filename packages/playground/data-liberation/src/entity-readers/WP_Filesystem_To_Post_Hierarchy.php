<?php

/**
 */
class WP_Filesystem_To_Post_Hierarchy {
	private $file_visitor;

	private $current_post;

	private $pending_files = array();
	private $pending_directory_index;

	private $parent_ids = array();
	private $next_post_id;
	private $create_index_pages;
	private $entities_read_so_far = 0;
	private $filter_pattern       = '##';
	private $index_file_pattern   = '##';
	private $is_finished          = false;

	public static function create(
		\WordPress\Filesystem\WP_Abstract_Filesystem $filesystem,
		$options
	) {
		if ( ! isset( $options['root_dir'] ) ) {
			_doing_it_wrong( __FUNCTION__, 'Missing required options: root_dir', '1.0.0' );
			return false;
		}
		if ( ! isset( $options['first_post_id'] ) ) {
			_doing_it_wrong( __FUNCTION__, 'Missing required options: first_post_id', '1.0.0' );
			return false;
		}
		if ( 1 === $options['first_post_id'] ) {
			_doing_it_wrong( __FUNCTION__, 'First post ID must be greater than 1', '1.0.0' );
			return false;
		}
		if ( ! isset( $options['filter_pattern'] ) ) {
			_doing_it_wrong( __FUNCTION__, 'Missing required options: filter_pattern', '1.0.0' );
			return false;
		}
		if ( ! isset( $options['index_file_pattern'] ) ) {
			_doing_it_wrong( __FUNCTION__, 'Missing required options: index_file_pattern', '1.0.0' );
			return false;
		}
		return new self(
			new WordPress\Filesystem\WP_Filesystem_Visitor( $filesystem, $options['root_dir'] ),
			$options
		);
	}

	private function __construct(
		\WordPress\Filesystem\WP_Filesystem_Visitor $file_visitor,
		$options
	) {
		$this->file_visitor       = $file_visitor;
		$this->create_index_pages = $options['create_index_pages'] ?? true;
		$this->next_post_id       = $options['first_post_id'];
		$this->filter_pattern     = $options['filter_pattern'];
		$this->index_file_pattern = $options['index_file_pattern'];
	}

	public function get_current_post() {
		return $this->current_post;
	}

	public function next_post() {
		$this->current_post = null;
		if ( $this->is_finished ) {
			return false;
		}
		while ( true ) {
			if ( null !== $this->pending_directory_index ) {
				$dir       = $this->file_visitor->get_event()->dir;
				$depth     = $this->file_visitor->get_current_depth();
				$parent_id = $this->parent_ids[ $depth - 1 ] ?? null;

				if ( null === $parent_id && $depth > 1 ) {
					// There's no parent ID even though we're a few levels deep.
					// This is a scenario where `next_file()` skipped a few levels
					// of directories with no relevant content in them:
					//
					// - /docs/
					//   - /foo/
					//     - /bar/
					//       - /baz.md
					//
					// In this case, we need to backtrack and create the missing
					// parent pages for /bar/ and /foo/.

					// Find the topmost missing parent ID
					$missing_parent_id_depth = 1;
					while ( isset( $this->parent_ids[ $missing_parent_id_depth ] ) ) {
						++$missing_parent_id_depth;
					}

					// Move up to the corresponding directory
					$missing_parent_path = $dir;
					for ( $i = $missing_parent_id_depth; $i < $depth; $i++ ) {
						$missing_parent_path = dirname( $missing_parent_path );
					}

					$this->parent_ids[ $missing_parent_id_depth ] = $this->emit_object(
						array(
							'type' => 'directory',
							'source_path' => $missing_parent_path,
							'parent_id' => $this->parent_ids[ $missing_parent_id_depth - 1 ] ?? null,
						)
					);
				} elseif ( false === $this->pending_directory_index ) {
					// No directory index candidate – let's create a fake page
					// just to have something in the page tree.
					$this->parent_ids[ $depth ] = $this->emit_object(
						array(
							'type' => 'file_placeholder',
							'source_path' => $dir,
							'parent_id' => $parent_id,
						)
					);
					// We're no longer looking for a directory index.
					$this->pending_directory_index = null;
				} else {
					$file_path                  = $this->pending_directory_index;
					$this->parent_ids[ $depth ] = $this->emit_object(
						array(
							'type' => 'file',
							'source_path' => $file_path,
							'parent_id' => $parent_id,
						)
					);
					// We're no longer looking for a directory index.
					$this->pending_directory_index = null;
				}
				return true;
			}

			while ( count( $this->pending_files ) ) {
				$parent_id = $this->parent_ids[ $this->file_visitor->get_current_depth() ] ?? null;
				$file_path = array_shift( $this->pending_files );
				$this->emit_object(
					array(
						'type' => 'file',
						'source_path' => $file_path,
						'parent_id' => $parent_id,
					)
				);
				return true;
			}

			if ( false === $this->next_file() ) {
				break;
			}
		}
		$this->is_finished = true;
		return false;
	}

	protected function emit_object( $options ) {
		$post_id = $this->next_post_id;
		++$this->next_post_id;
		$this->current_post = array_merge(
			$options,
			array(
				'post_id' => $post_id,
			)
		);
		++$this->entities_read_so_far;
		return $post_id;
	}

	private function next_file() {
		$this->pending_files = array();
		while ( $this->file_visitor->next() ) {
			$event = $this->file_visitor->get_event();

			if ( $event->is_exiting() ) {
				// Clean up stale IDs to save some memory when processing
				// large directory trees.
				unset( $this->parent_ids[ $event->dir ] );
				continue;
			}

			if ( $event->is_entering() ) {
				$abs_paths = array();
				foreach ( $event->files as $filename ) {
					$abs_paths[] = $event->dir . '/' . $filename;
				}
				$this->pending_files = $this->choose_relevant_files( $abs_paths );
				if ( ! count( $this->pending_files ) ) {
					// Only consider directories with relevant files in them.
					// Otherwise we'll create fake pages for media directories
					// and other directories that don't contain any content.
					//
					// One corner case is when there's a few levels of directories
					// with a single relevant file at the bottom:
					//
					// - /docs/
					//   - /foo/
					//     - /bar/
					//       - /baz.md
					//
					// In this case, `next_entity()` will backtrack at baz.md and
					// create the missing parent pages.
					continue;
				}
				$directory_index_idx = $this->choose_directory_index( $this->pending_files );
				if ( -1 === $directory_index_idx ) {
					$this->pending_directory_index = false;
				} else {
					$this->pending_directory_index = $this->pending_files[ $directory_index_idx ];
					unset( $this->pending_files[ $directory_index_idx ] );
				}
				return true;
			}

			return false;
		}
		return false;
	}

	protected function choose_directory_index( $files ) {
		foreach ( $files as $idx => $file ) {
			if ( $this->looks_like_directory_index( $file ) ) {
				return $idx;
			}
		}
		if ( ! $this->create_index_pages && count( $files ) > 0 ) {
			return 0;
		}
		return -1;
	}

	protected function looks_like_directory_index( $path ) {
		return preg_match( $this->index_file_pattern, basename( $path ) );
	}

	protected function choose_relevant_files( $paths ) {
		$filtered_paths = array();
		foreach ( $paths as $path ) {
			if ( preg_match( $this->filter_pattern, $path ) ) {
				$filtered_paths[] = $path;
			}
		}
		return $filtered_paths;
	}

	/**
	 * @TODO: Either implement this method, or introduce a concept of
	 *        reentrant and non-reentrant entity readers.
	 */
	public function get_reentrancy_cursor() {
		return '';
	}
}
