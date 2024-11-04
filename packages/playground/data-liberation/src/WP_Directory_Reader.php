<?php

class WP_Serialized_Pages_Reader {

    private $iterator;
    private $file;

    public function __construct( $dir ) {
        $this->iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator( $dir )
        );
    }

    public function next_file() {
        if(!$this->iterator->valid()) {
            return null;
        }
        $this->file = $this->iterator->current();
        $this->iterator->next();
        return true;
    }

    /**
     * @return SplFileInfo|null
     */
    public function get_file() {
        return $this->file;
    }

}
