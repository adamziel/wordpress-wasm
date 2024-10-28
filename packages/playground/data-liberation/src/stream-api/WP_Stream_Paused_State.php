<?php

class WP_Stream_Paused_State {
	public $class;
	public $data;

	public function __construct( $class_name, $data ) {
		$this->class = $class_name;
		$this->data  = $data;
	}
}
