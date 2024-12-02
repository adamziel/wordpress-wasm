<?php

class WP_Attachment_Downloader_Event {

	const SUCCESS = '#success';
	const FAILURE = '#failure';
	const SKIPPED = '#skipped';

	public $type;
	public $resource_id;
	public $error;

	public function __construct( $resource_id, $type, $error = null ) {
		$this->resource_id = $resource_id;
		$this->type        = $type;
		$this->error       = $error;
	}
}
