<?php

require_once __DIR__ . '/src/stream-api/WP_Stream_Processor.php';
require_once __DIR__ . '/src/stream-api/WP_Byte_Stream_State.php';
require_once __DIR__ . '/src/stream-api/WP_Byte_Stream.php';
require_once __DIR__ . '/src/stream-api/WP_Processor_Byte_Stream.php';
require_once __DIR__ . '/src/stream-api/WP_File_Byte_Stream.php';
require_once __DIR__ . '/src/stream-api/WP_Stream_Paused_State.php';
require_once __DIR__ . '/src/stream-api/WP_Stream_Chain.php';

require_once __DIR__ . "/src/wordpress-core-html-api/class-wp-html-token.php";
require_once __DIR__ . "/src/wordpress-core-html-api/class-wp-html-span.php";
require_once __DIR__ . "/src/wordpress-core-html-api/class-wp-html-text-replacement.php";
require_once __DIR__ . "/src/wordpress-core-html-api/class-wp-html-decoder.php";
require_once __DIR__ . "/src/wordpress-core-html-api/class-wp-html-attribute-token.php";

require_once __DIR__ . "/src/wordpress-core-html-api/class-wp-html-decoder.php";
require_once __DIR__ . "/src/wordpress-core-html-api/class-wp-html-tag-processor.php";
require_once __DIR__ . "/src/wordpress-core-html-api/class-wp-html-open-elements.php";
require_once __DIR__ . "/src/wordpress-core-html-api/class-wp-token-map.php";
require_once __DIR__ . "/src/wordpress-core-html-api/html5-named-character-references.php";
require_once __DIR__ . "/src/wordpress-core-html-api/class-wp-html-active-formatting-elements.php";
require_once __DIR__ . "/src/wordpress-core-html-api/class-wp-html-processor-state.php";
require_once __DIR__ . "/src/wordpress-core-html-api/class-wp-html-unsupported-exception.php";
require_once __DIR__ . "/src/wordpress-core-html-api/class-wp-html-processor.php";

require_once __DIR__ . '/src/WP_Block_Markup_Processor.php';
require_once __DIR__ . '/src/WP_Block_Markup_Url_Processor.php';
require_once __DIR__ . '/src/WP_URL_In_Text_Processor.php';
require_once __DIR__ . '/src/WP_URL.php';

require_once __DIR__ . '/src/xml-api/WP_XML_Decoder.php';
require_once __DIR__ . '/src/xml-api/WP_XML_Tag_Processor.php';
require_once __DIR__ . '/src/xml-api/WP_XML_Processor.php';
require_once __DIR__ . '/src/WP_WXR_URL_Rewrite_Processor.php';

require_once __DIR__ . '/vendor/autoload.php';


// Polyfill WordPress core functions
function _doing_it_wrong() {

}

function __($input) {
	return $input;
}

function esc_attr($input) {
	return htmlspecialchars($input);
}

function esc_html($input) {
	return htmlspecialchars($input);
}

function esc_url($url) {
	return htmlspecialchars($url);
}

function wp_kses_uri_attributes() {
	return array(
		'action',
		'archive',
		'background',
		'cite',
		'classid',
		'codebase',
		'data',
		'formaction',
		'href',
		'icon',
		'longdesc',
		'manifest',
		'poster',
		'profile',
		'src',
		'usemap',
		'xmlns',
	);
}
