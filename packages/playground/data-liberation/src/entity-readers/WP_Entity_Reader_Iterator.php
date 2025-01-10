<?php

/**
 * An iterator that reads entities from a WP_Entity_Reader.
 */
class WP_Entity_Reader_Iterator implements Iterator {

    /**
     * @var WP_Entity_Reader
     */
    private $entity_reader;
    private $is_initialized = false;
    private $key = 0;

    public function __construct( WP_Entity_Reader $entity_reader ) {
        $this->entity_reader = $entity_reader;
    }

    public function get_entity_reader() {
        return $this->entity_reader;
    }

    #[\ReturnTypeWillChange]
    public function current() {
        $this->ensure_initialized();
        return $this->entity_reader->get_entity();
    }

    #[\ReturnTypeWillChange]
    public function next() {
        $this->ensure_initialized();
        $this->advance_to_next_entity();
    }

    #[\ReturnTypeWillChange]
    public function key() {
        $this->ensure_initialized();
        return $this->key;
    }

    #[\ReturnTypeWillChange]
    public function valid() {
        $this->ensure_initialized();
        return ! $this->entity_reader->is_finished();
    }

    #[\ReturnTypeWillChange]
    public function rewind() {
        throw new Data_Liberation_Exception( 'WP_Entity_Reader_Iterator does not support rewinding.' );
    }

    private function ensure_initialized() {
        if ( ! $this->is_initialized ) {
            $this->is_initialized = true;
            $this->advance_to_next_entity();
        }
    }

    private function advance_to_next_entity() {
        if ( $this->entity_reader->next_entity() ) {
            $this->key++;
        }
    }

}