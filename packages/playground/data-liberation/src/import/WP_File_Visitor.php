<?php

class WP_File_Visitor {
	private $dir;
	private $directories = array();
	private $files       = array();
	private $current_event;
	private $iterator_stack = array();
	private $current_iterator;
	private $depth = 0;

	public function __construct( $dir ) {
		$this->dir              = $dir;
		$this->iterator_stack[] = $this->create_iterator( $dir );
	}

	public function get_current_depth() {
		return $this->depth;
	}

	public function get_root_dir() {
		return $this->dir;
	}

	private function create_iterator( $dir ) {
		$this->directories = array();
		$this->files       = array();

		$dh = opendir( $dir );
		if ( $dh === false ) {
			return new ArrayIterator( array() );
		}

		while ( true ) {
			$file = readdir( $dh );
			if ( $file === false ) {
				break;
			}
			if ( '.' === $file || '..' === $file ) {
				continue;
			}
			$file_path = $dir . '/' . $file;
			if ( is_dir( $file_path ) ) {
				$this->directories[] = $file_path;
				continue;
			}
			$this->files[] = new SplFileInfo( $file_path );
		}
		closedir( $dh );

		$events = array(
			new WP_File_Visitor_Event( WP_File_Visitor_Event::EVENT_ENTER, new SplFileInfo( $dir ), $this->files ),
		);

		foreach ( $this->directories as $directory ) {
			$events[] = $directory; // Placeholder for recursion
		}

		$events[] = new WP_File_Visitor_Event( WP_File_Visitor_Event::EVENT_EXIT, new SplFileInfo( $dir ) );

		return new ArrayIterator( $events );
	}

	public function next() {
		while ( ! empty( $this->iterator_stack ) ) {
			$this->current_iterator = end( $this->iterator_stack );

			if ( $this->current_iterator->valid() ) {
				$current = $this->current_iterator->current();
				$this->current_iterator->next();

				if ( $current instanceof WP_File_Visitor_Event ) {
					if ( $current->is_entering() ) {
						++$this->depth;
					}
					$this->current_event = $current;
					if ( $current->is_exiting() ) {
						--$this->depth;
					}
					return true;
				} else {
					// It's a directory path, push a new iterator onto the stack
					$this->iterator_stack[] = $this->create_iterator( $current );
				}
			} else {
				array_pop( $this->iterator_stack );
			}
		}

		return false;
	}

	public function get_event() {
		return $this->current_event;
	}
}
