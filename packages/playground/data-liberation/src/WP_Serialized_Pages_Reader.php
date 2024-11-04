<?php

class WP_Serialized_Pages_Reader {

    private $root_dir;
    private $iterator;

    /** @var SplFileInfo */
    private $file;

    private $directory_indexes = [];

    public function __construct( $root_dir ) {
        $this->root_dir = $root_dir;
        $this->iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator( $root_dir ),
            RecursiveIteratorIterator::CHILD_FIRST
        );
    }

    public function get_parent_directory_index() {
        if($this->iterator->getDepth() === 0) {
            return null;
        }
        $starting_depth = $this->iterator->getDepth() - 1;
        do {
            if(isset($this->directory_indexes[$starting_depth])) {
                return $this->directory_indexes[$starting_depth];
            }
            $starting_depth--;
        } while($starting_depth > 0);

        return false;
    }

    public function has_directory_index() {
        return isset($this->directory_indexes[$this->iterator->getDepth()]);
    }

    public function mark_as_directory_index() {
        $this->directory_indexes[$this->iterator->getDepth()] = $this->get_relative_path();
    }

    public function next_file() {
        $last_depth = $this->iterator->getDepth();
        do {
            $this->iterator->next();
            if(!$this->iterator->valid()) {
                return null;
            }
        } while(!$this->iterator->current()->isFile());

        $this->file = $this->iterator->current();        
        $current_depth = $this->iterator->getDepth();
        if ($current_depth < $last_depth) {
            unset($this->directory_indexes[$last_depth]);
        }

        return true;
    }

    public function get_file() {
        return $this->file;
    }

    public function get_relative_path() {
        $pathname = $this->file->getPathname();
        // Normalize directory separators to forward slashes
        $pathname = str_replace('\\', '/', $pathname);
        $root = str_replace('\\', '/', $this->root_dir);
        
        // Ensure root has trailing slash
        $root = rtrim($root, '/') . '/';
        
        return substr($pathname, strlen($root));
    }

}
