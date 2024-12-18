<?php

interface WP_Block_Markup_Converter {
	public function convert();
	public function get_block_markup();
	public function get_all_metadata();
	public function get_meta_value( $key );
}
