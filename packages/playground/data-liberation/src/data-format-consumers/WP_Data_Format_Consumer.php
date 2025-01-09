<?php

/**
 * Represents a {Data Format} -> Block Markup + Metadata consumer.
 *
 * Used by the Data Liberation importers to accept data formatted as HTML, Markdown, etc.
 * and convert them to WordPress posts.
 */
interface WP_Data_Format_Consumer {
	/**
	 * Converts the input document specified in the constructor to block markup.
	 *
	 * @return WP_Blocks_With_Metadata The consumed block markup and metadata.
	 */
	public function consume();
}
