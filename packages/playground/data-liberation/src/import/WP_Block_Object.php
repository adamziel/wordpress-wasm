<?php

class WP_Block_Object {
	public $block_name;
	public $attrs;
	public $inner_blocks;

	public function __construct( $block_name, $attrs = array(), $inner_blocks = array() ) {
		$this->block_name   = $block_name;
		$this->attrs        = $attrs;
		$this->inner_blocks = $inner_blocks;
	}
}
