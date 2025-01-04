<?php

/**
 * Represents a single entity, whether a WordPress post, post meta,
 * a single SQL record, or something entirely different.
 */
class WP_Imported_Entity {

	const TYPE_POST         = 'post';
	const TYPE_POST_META    = 'post_meta';
	const TYPE_COMMENT      = 'comment';
	const TYPE_COMMENT_META = 'comment_meta';
	const TYPE_TERM         = 'term';
	const TYPE_TAG          = 'tag';
	const TYPE_CATEGORY     = 'category';
	const TYPE_USER         = 'user';
	const TYPE_SITE_OPTION  = 'site_option';

	const POST_FIELDS = array(
		'post_title',
		'link',
		'guid',
		'post_excerpt',
		'post_published_at',
		'post_author',
		'post_content',
		'post_excerpt',
		'post_id',
		'post_status',
		'post_date',
		'post_date_gmt',
		'post_modified',
		'post_modified_gmt',
		'comment_status',
		'ping_status',
		'post_name',
		'post_parent',
		'menu_order',
		'post_type',
		'post_password',
		'is_sticky',
		'attachment_url',
	);

	private $type;
	private $data;

	public function __construct( $type, $data ) {
		$this->type = $type;
		$this->data = $data;
	}

	public function get_type() {
		return $this->type;
	}

	public function get_data() {
		return $this->data;
	}

	public function set_data( $data ) {
		$this->data = $data;
	}
}
