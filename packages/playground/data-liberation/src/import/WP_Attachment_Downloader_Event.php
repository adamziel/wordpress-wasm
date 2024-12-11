<?php

class WP_Attachment_Downloader_Event {

	const SUCCESS        = '#success';
	const FAILURE        = '#failure';
	const ALREADY_EXISTS = '#already_exists';
	const IN_PROGRESS    = '#in_progress';

	public $type;
	public $resource_id;
	public $error;

	public function __construct( $resource_id, $type, $error = null ) {
		$this->resource_id = $resource_id;
		$this->type        = $type;
		$this->error       = $error;
	}
}
