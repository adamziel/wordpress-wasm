<?php

class WP_Entity_Iterator_Chain extends AppendIterator {

    public function get_reentrancy_cursor() {
        $inner_iterator = $this->getInnerIterator();
        if (! $inner_iterator ) {
            return null;
        }
        if ( ! method_exists( $inner_iterator, 'get_reentrancy_cursor' ) ) {
            return null;
        }
        return $inner_iterator->get_reentrancy_cursor();
    }

}