<?php

/**
 * Data Liberation: Markdown reader.
 *
 * This exploration accompanies the WXR reader to inform a generic
 * data importing pipeline that's not specific to a single input format.
 *
 * @TODO: Support multiple data sources – filesystem directory tree, zip file, ...
 * @TODO: Expose a cursor to allow resuming from where we left off.
 */

use WordPress\DataLiberation\Import\WP_Import_Utils;

class WP_Markdown_Directory_Tree_Reader implements Iterator {
	private $file_visitor;
    private $filesystem;
	private $entity;

	private $pending_directory_index;
	private $pending_files = array();
	private $parent_ids    = array();
	private $next_post_id;
	private $is_finished          = false;
	private $entities_read_so_far = 0;

	public function __construct( \WordPress\Filesystem\WP_Abstract_Filesystem $filesystem, $root_dir, $first_post_id ) {
		$this->file_visitor = new \WordPress\Filesystem\WP_Filesystem_Visitor( $filesystem, $root_dir );
        $this->filesystem = $filesystem;
		$this->next_post_id = $first_post_id;
	}

	public function next_entity() {
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
						$missing_parent_path = dirname($missing_parent_path);
					}

					$this->parent_ids[ $missing_parent_id_depth ] = $this->emit_post_entity(
						array(
							'markdown' => '',
							'source_path' => $missing_parent_path,
							'parent_id' => $this->parent_ids[ $missing_parent_id_depth - 1 ],
							'title_fallback' => WP_Import_Utils::slug_to_title( basename( $missing_parent_path ) ),
						)
					);
				} else if ( false === $this->pending_directory_index ) {
					// No directory index candidate – let's create a fake page
					// just to have something in the page tree.
					$this->parent_ids[ $depth ] = $this->emit_post_entity(
						array(
							'markdown' => '',
							'source_path' => $dir,
							'parent_id' => $parent_id,
							'title_fallback' => WP_Import_Utils::slug_to_title( basename( $dir ) ),
						)
					);
					// We're no longer looking for a directory index.
					$this->pending_directory_index = null;
				} else {
					$file_path = $this->pending_directory_index;
					$this->parent_ids[ $depth ] = $this->emit_post_entity(
						array(
							'markdown' => $this->filesystem->read_file( $file_path ),
							'source_path' => $file_path,
							'parent_id' => $parent_id,
							'title_fallback' => WP_Import_Utils::slug_to_title( basename( $file_path ) ),
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
				$this->emit_post_entity(
					array(
						'markdown' => $this->filesystem->read_file( $file_path ),
						'source_path' => $file_path,
						'parent_id' => $parent_id,
						'title_fallback' => WP_Import_Utils::slug_to_title( basename( $file_path ) ),
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

	public function get_entity(): ?WP_Imported_Entity {
		return $this->entity;
	}

	protected function emit_post_entity( $options ) {
		$converter = new WP_Markdown_To_Blocks( $options['markdown'] );
		$converter->parse();
		$block_markup = $converter->get_block_markup();
		$frontmatter  = $converter->get_frontmatter();

		$removed_title = WP_Import_Utils::remove_first_h1_block_from_block_markup( $block_markup );
		if ( false !== $removed_title ) {
			$block_markup = $removed_title['remaining_html'];
		}

		$post_title = '';
		if ( ! $post_title && ! empty( $removed_title['content'] ) ) {
			$post_title = $removed_title['content'];
		}
		if ( ! $post_title && ! empty( $frontmatter['title'] ) ) {
			// In WordPress Playground docs, the frontmatter title
			// is actually a worse candidate than the first H1 block
			//
			// There will, inevitably, be 10,000 ways people will want
			// to use this importer with different projects. Let's just
			// enable plugins to customize the title resolution.
			$post_title = $frontmatter['title'];
		}
		if ( ! $post_title ) {
			$post_title = $options['title_fallback'];
		}

		$entity_data = array(
			'post_id' => $this->next_post_id,
			'post_type' => 'page',
			'guid' => $options['source_path'],
			'post_title' => $post_title,
			'post_content' => $block_markup,
			'post_excerpt' => $frontmatter['description'] ?? '',
			'post_status' => 'publish',
		);

		/**
		 * Technically `source_path` isn't a part of the WordPress post object,
		 * but we need it to resolve relative URLs in the imported content.
		 *
		 * This path is relative to the root directory traversed by this class.
		 */
		if ( ! empty( $options['source_path'] ) ) {
			$source_path = $options['source_path'];
			$root_dir    = $this->file_visitor->get_root_dir();
			if ( str_starts_with( $source_path, $root_dir ) ) {
				$source_path = substr( $source_path, strlen( $root_dir ) );
			}
			$source_path                = ltrim( $source_path, '/' );
			$entity_data['source_path'] = $source_path;
		}

		if ( ! empty( $frontmatter['slug'] ) ) {
			$slug                     = $frontmatter['slug'];
			$last_segment             = substr( $slug, strrpos( $slug, '/' ) + 1 );
			$entity_data['post_name'] = $last_segment;
		}

		if ( isset( $frontmatter['sidebar_position'] ) ) {
			$entity_data['post_order'] = $frontmatter['sidebar_position'];
		}

		if ( $options['parent_id'] ) {
			$entity_data['post_parent'] = $options['parent_id'];
		}
		
		$this->entity = new WP_Imported_Entity( 'post', $entity_data );
		++$this->next_post_id;
		++$this->entities_read_so_far;
		return $entity_data['post_id'];
	}

	private function next_file() {
		$this->pending_files = array();
		$this->entity        = null;
		while ( $this->file_visitor->next() ) {
			$event = $this->file_visitor->get_event();

			if ( $event->is_exiting() ) {
				// Clean up stale IDs to save some memory when processing
				// large directory trees.
				unset( $this->parent_ids[ $event->dir ] );
				continue;
			}

			if ( $event->is_entering() ) {
                $abs_paths = [];
                foreach($event->files as $filename) {
                    $abs_paths[] = $event->dir . '/' . $filename;
                }
				$this->pending_files = $this->choose_relevant_files( $abs_paths );
				if( ! count($this->pending_files)) {
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
		return -1;
	}

	protected function looks_like_directory_index( $path ) {
        $filename = basename($path);
		return str_contains( $filename, 'index' );
	}

	protected function choose_relevant_files( $paths ) {
		return array_filter( $paths, array( $this, 'is_valid_file' ) );
	}

	protected function is_valid_file( $path ) {
        $extension = pathinfo($path, PATHINFO_EXTENSION);
		return 'md' === $extension;
	}

	/**
	 * @TODO: Either implement this method, or introduce a concept of
	 *        reentrant and non-reentrant importers.
	 */
	public function get_reentrancy_cursor() {
		return '';
	}

	public function current(): mixed {
		if ( null === $this->entity && ! $this->is_finished ) {
			$this->next();
		}
		return $this->get_entity();
	}

	public function next(): void {
		$this->next_entity();
	}

	public function key(): int {
		return $this->entities_read_so_far - 1;
	}

	public function valid(): bool {
		return ! $this->is_finished;
	}

	public function rewind(): void {
		// noop
	}
}
