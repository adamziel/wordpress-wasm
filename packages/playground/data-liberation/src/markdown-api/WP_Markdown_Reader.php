<?php
/**
 * Data Liberation: Markdown reader.
 *
 * This exploration accompanies the WXR reader to inform a generic
 * data importing pipeline that's not specific to a single input format.
 */

class WP_Markdown_Reader {

	private $markdown_content;
	private $entity_type;
	private $entity_data;

	public function __construct( $markdown_content ) {
		$this->markdown_content = $markdown_content;
	}

	public function get_entity_type() {
		return $this->entity_type;
	}

	public function get_entity_data() {
		return $this->entity_data;
	}

	public function next_entity() {
		if ( $this->entity_type ) {
			return false;
		}

		$blocks            = WP_Markdown_To_Blocks::convert( $this->markdown_content );
		$this->entity_type = 'post';
		$this->entity_data = array(
			'post_type' => 'post',
			// 'post_title' => '',
			'post_content' => $blocks,
			'post_status' => 'publish',
		);
		return true;
	}
}
