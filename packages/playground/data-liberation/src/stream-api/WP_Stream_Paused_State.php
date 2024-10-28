<?php

class WP_Stream_Paused_State {
    public $class;
    public $data;

    public function __construct($class, $data) {
        $this->class = $class;
        $this->data = $data;
    }
}
