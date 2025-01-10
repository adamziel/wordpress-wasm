<?php

require_once __DIR__ . '/blueprints-library/src/WordPress/Streams/StreamWrapperInterface.php';
require_once __DIR__ . '/blueprints-library/src/WordPress/Streams/StreamWrapper.php';
require_once __DIR__ . '/blueprints-library/src/WordPress/Streams/StreamPeekerWrapper.php';
require_once __DIR__ . '/blueprints-library/src/WordPress/AsyncHttp/StreamWrapper/ChunkedEncodingWrapper.php';
require_once __DIR__ . '/blueprints-library/src/WordPress/AsyncHttp/StreamWrapper/InflateStreamWrapper.php';
require_once __DIR__ . '/blueprints-library/src/WordPress/AsyncHttp/Request.php';
require_once __DIR__ . '/blueprints-library/src/WordPress/AsyncHttp/Response.php';
require_once __DIR__ . '/blueprints-library/src/WordPress/AsyncHttp/HttpError.php';
require_once __DIR__ . '/blueprints-library/src/WordPress/AsyncHttp/Connection.php';
require_once __DIR__ . '/blueprints-library/src/WordPress/AsyncHttp/Client.php';

require_once __DIR__ . '/blueprints-library/src/WordPress/Filesystem/WP_Abstract_Filesystem.php';
require_once __DIR__ . '/blueprints-library/src/WordPress/Filesystem/WP_Local_Filesystem.php';
require_once __DIR__ . '/blueprints-library/src/WordPress/Filesystem/WP_File_Visitor_Event.php';
require_once __DIR__ . '/blueprints-library/src/WordPress/Filesystem/WP_Filesystem_Visitor.php';
require_once __DIR__ . '/blueprints-library/src/WordPress/Filesystem/functions.php';

require_once __DIR__ . '/blueprints-library/src/WordPress/ByteReader/WP_Byte_Reader.php';
require_once __DIR__ . '/blueprints-library/src/WordPress/ByteReader/WP_File_Reader.php';
require_once __DIR__ . '/blueprints-library/src/WordPress/ByteReader/WP_GZ_File_Reader.php';
require_once __DIR__ . '/blueprints-library/src/WordPress/ByteReader/WP_Remote_File_Reader.php';
require_once __DIR__ . '/blueprints-library/src/WordPress/ByteReader/WP_Remote_File_Ranged_Reader.php';

require_once __DIR__ . '/blueprints-library/src/WordPress/Zip/ZipStreamReader.php';
require_once __DIR__ . '/blueprints-library/src/WordPress/Zip/WP_Zip_Filesystem.php';

if (
	! class_exists( 'WP_HTML_Tag_Processor' ) &&
	file_exists( __DIR__ . '/src/wordpress-core-html-api/class-wp-html-token.php' )
) {
	require_once __DIR__ . '/src/wordpress-core-html-api/class-wp-html-token.php';
	require_once __DIR__ . '/src/wordpress-core-html-api/class-wp-html-span.php';
	require_once __DIR__ . '/src/wordpress-core-html-api/class-wp-html-text-replacement.php';
	require_once __DIR__ . '/src/wordpress-core-html-api/class-wp-html-decoder.php';
	require_once __DIR__ . '/src/wordpress-core-html-api/class-wp-html-attribute-token.php';

	require_once __DIR__ . '/src/wordpress-core-html-api/class-wp-html-decoder.php';
	require_once __DIR__ . '/src/wordpress-core-html-api/class-wp-html-tag-processor.php';
	require_once __DIR__ . '/src/wordpress-core-html-api/class-wp-html-open-elements.php';
	require_once __DIR__ . '/src/wordpress-core-html-api/class-wp-token-map.php';
	require_once __DIR__ . '/src/wordpress-core-html-api/html5-named-character-references.php';
	require_once __DIR__ . '/src/wordpress-core-html-api/class-wp-html-active-formatting-elements.php';
	require_once __DIR__ . '/src/wordpress-core-html-api/class-wp-html-processor-state.php';
	require_once __DIR__ . '/src/wordpress-core-html-api/class-wp-html-unsupported-exception.php';
	require_once __DIR__ . '/src/wordpress-core-html-api/class-wp-html-processor.php';
}
if (
	! isset( $html5_named_character_references ) &&
	file_exists( __DIR__ . '/src/wordpress-core-html-api/html5-named-character-references.php' )
) {
	require_once __DIR__ . '/src/wordpress-core-html-api/html5-named-character-references.php';
}

require_once __DIR__ . '/src/Data_Liberation_Exception.php';
require_once __DIR__ . '/src/data-format-consumers/WP_Blocks_With_Metadata.php';
require_once __DIR__ . '/src/data-format-consumers/WP_Data_Format_Consumer.php';
require_once __DIR__ . '/src/data-format-consumers/WP_Markup_Processor_Consumer.php';
require_once __DIR__ . '/src/data-format-consumers/WP_Annotated_Block_Markup_Consumer.php';

require_once __DIR__ . '/src/data-format-producers/WP_Data_Format_Producer.php';
require_once __DIR__ . '/src/data-format-producers/WP_Annotated_Block_Markup_Producer.php';

require_once __DIR__ . '/src/block-markup/WP_Block_Markup_Processor.php';
require_once __DIR__ . '/src/block-markup/WP_Block_Markup_Url_Processor.php';
require_once __DIR__ . '/src/block-markup/WP_URL_In_Text_Processor.php';
require_once __DIR__ . '/src/block-markup/WP_URL.php';

require_once __DIR__ . '/src/entity-readers/WP_Entity_Reader.php';
require_once __DIR__ . '/src/entity-readers/WP_Blocks_With_Metadata_Entity_Reader.php';
require_once __DIR__ . '/src/entity-readers/WP_HTML_Entity_Reader.php';
require_once __DIR__ . '/src/entity-readers/WP_EPub_Entity_Reader.php';
require_once __DIR__ . '/src/entity-readers/WP_WXR_Entity_Reader.php';

require_once __DIR__ . '/src/xml-api/WP_XML_Decoder.php';
require_once __DIR__ . '/src/xml-api/WP_XML_Processor.php';
require_once __DIR__ . '/src/import/WP_Import_Utils.php';
require_once __DIR__ . '/src/import/WP_Block_Object.php';
require_once __DIR__ . '/src/import/WP_Entity_Importer.php';
require_once __DIR__ . '/src/import/WP_Imported_Entity.php';
require_once __DIR__ . '/src/import/WP_Attachment_Downloader.php';
require_once __DIR__ . '/src/import/WP_Attachment_Downloader_Event.php';
require_once __DIR__ . '/src/import/WP_Import_Session.php';
require_once __DIR__ . '/src/import/WP_Stream_Importer.php';
require_once __DIR__ . '/src/import/WP_Entity_Iterator_Chain.php';
require_once __DIR__ . '/src/import/WP_Retry_Frontloading_Iterator.php';
require_once __DIR__ . '/src/entity-readers/WP_Entity_Reader.php';
require_once __DIR__ . '/src/entity-readers/WP_HTML_Entity_Reader.php';
require_once __DIR__ . '/src/entity-readers/WP_Filesystem_Entity_Reader.php';

require_once __DIR__ . '/src/WP_Data_Liberation_HTML_Processor.php';

require_once __DIR__ . '/src/utf8_decoder.php';

// When running in Playground, the composer autoloader script sees CLI SAPI and
// tries to use the STDERR, STDIN, and STDOUT constants.
// @TODO: Don't use the "cli" SAPI string and don't allow composer to run platform checks.
if ( ! defined( 'STDERR' ) ) {
	define( 'STDERR', fopen( 'php://stderr', 'w' ) );
}
if ( ! defined( 'STDIN' ) ) {
	define( 'STDIN', fopen( 'php://stdin', 'r' ) );
}
if ( ! defined( 'STDOUT' ) ) {
	define( 'STDOUT', fopen( 'php://stdout', 'w' ) );
}
require_once __DIR__ . '/vendor/autoload.php';

// Polyfill WordPress core functions
if ( ! function_exists( '_doing_it_wrong' ) ) {
	$GLOBALS['_doing_it_wrong_messages'] = array();
	function _doing_it_wrong( $method, $message, $version ) {
		$GLOBALS['_doing_it_wrong_messages'][] = $message;
	}
}

if ( ! function_exists( 'wp_kses_uri_attributes' ) ) {
	function wp_kses_uri_attributes() {
		return array();
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $input ) {
		return $input;
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $input ) {
		return htmlspecialchars( $input );
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $input ) {
		return htmlspecialchars( $input );
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url ) {
		return htmlspecialchars( $url );
	}
}

if ( ! function_exists( 'wp_kses_uri_attributes' ) ) {
	function wp_kses_uri_attributes() {
		return array();
	}
}

if ( ! function_exists( 'mbstring_binary_safe_encoding' ) ) {
	function mbstring_binary_safe_encoding( $reset = false ) {
		static $encodings  = array();
		static $overloaded = null;

		if ( is_null( $overloaded ) ) {
			if ( function_exists( 'mb_internal_encoding' )
				&& ( (int) ini_get( 'mbstring.func_overload' ) & 2 ) // phpcs:ignore PHPCompatibility.IniDirectives.RemovedIniDirectives.mbstring_func_overloadDeprecated
			) {
				$overloaded = true;
			} else {
				$overloaded = false;
			}
		}

		if ( false === $overloaded ) {
			return;
		}

		if ( ! $reset ) {
			$encoding = mb_internal_encoding();
			array_push( $encodings, $encoding );
			mb_internal_encoding( 'ISO-8859-1' );
		}

		if ( $reset && $encodings ) {
			$encoding = array_pop( $encodings );
			mb_internal_encoding( $encoding );
		}
	}
}

if ( ! function_exists( 'reset_mbstring_encoding' ) ) {
	function reset_mbstring_encoding() {
		mbstring_binary_safe_encoding( true );
	}
}
