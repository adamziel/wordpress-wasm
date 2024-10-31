<?php
/**
 * A fork of the WordPress Importer plugin (humanmade/WordPress-Importer).
 * It's meant to be rewritten to use the new libraries shipped with Data Liberation:
 *
 * * HTML parser
 * * Streaming XML parser
 * * Streaming multi-request HTTP client
 *
 * Original plugin created by Ryan Boren, Jon Cave (@joncave), Andrew Nacin (@nacin),
 * and Peter Westwood (@westi). Redux project by Ryan McCue and contributors.
 *
 * See https://github.com/humanmade/WordPress-Importer/blob/master/class-wxr-importer.php
 */
class WXR_Import_Info {
	public $home;
	public $siteurl;

	public $title;

	public $users         = array();
	public $post_count    = 0;
	public $media_count   = 0;
	public $comment_count = 0;
	public $term_count    = 0;

	public $generator = '';
	public $version;
}
