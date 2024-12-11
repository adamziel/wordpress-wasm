<?php
use Rowbot\URL\URL;

/**
 * Migrate URLs in post content. See WPRewriteUrlsTests for
 * specific examples. TODO: A better description.
 *
 * Example:
 *
 * ```php
 * php > wp_rewrite_urls([
 *   'block_markup' => '<!-- wp:image {"src": "http://legacy-blog.com/image.jpg"} -->',
 *   'url-mapping' => [
 *     'http://legacy-blog.com' => 'https://modern-webstore.org'
 *   ]
 * ])
 * <!-- wp:image {"src":"https:\/\/modern-webstore.org\/image.jpg"} -->
 * ```
 *
 * @TODO Use a proper JSON parser and encoder to:
 * * Support UTF-16 characters
 * * Gracefully handle recoverable encoding issues
 * * Avoid changing the whitespace in the same manner as
 *   we do in WP_HTML_Tag_Processor
 */
function wp_rewrite_urls( $options ) {
	if ( empty( $options['base_url'] ) ) {
		// Use first from-url as base_url if not specified
		$from_urls           = array_keys( $options['url-mapping'] );
		$options['base_url'] = $from_urls[0];
	}

	$url_mapping = array();
	foreach ( $options['url-mapping'] as $from_url_string => $to_url_string ) {
		$url_mapping[] = array(
			'from_url' => WP_URL::parse( $from_url_string ),
			'to_url' => WP_URL::parse( $to_url_string ),
		);
	}

	$p = new WP_Block_Markup_Url_Processor( $options['block_markup'], $options['base_url'] );
	while ( $p->next_url() ) {
		$parsed_url = $p->get_parsed_url();
		foreach ( $url_mapping as $mapping ) {
			if ( is_child_url_of( $parsed_url, $mapping['from_url'] ) ) {
				$p->replace_base_url( $mapping['to_url'] );
				break;
			}
		}
	}
	return $p->get_updated_html();
}

/**
 * Check if a given URL matches the current site URL.
 *
 * @param URL $parent The URL to check.
 * @param string $child The current site URL to compare against.
 * @return bool Whether the URL matches the current site URL.
 */
function is_child_url_of( $child, $parent_url ) {
	$parent_url                       = is_string( $parent_url ) ? WP_URL::parse( $parent_url ) : $parent_url;
	$child                            = is_string( $child ) ? WP_URL::parse( $child ) : $child;
	$child_pathname_no_trailing_slash = rtrim( urldecode( $child->pathname ), '/' );

	if ( $parent_url->hostname !== $child->hostname ) {
		return false;
	}

	$parent_pathname = urldecode( $parent_url->pathname );
	return (
		// Direct match
		$parent_pathname === $child_pathname_no_trailing_slash ||
		$parent_pathname === $child_pathname_no_trailing_slash . '/' ||
		// Path prefix
		str_starts_with( $child_pathname_no_trailing_slash . '/', $parent_pathname )
	);
}

/**
 * Decodes the first n **encoded bytes** a URL-encoded string.
 *
 * For example, `urldecode_n( '%22is 6 %3C 6?%22 – asked Achilles', 1 )` returns
 * '"is 6 %3C 6?%22 – asked Achilles' because only the first encoded byte is decoded.
 *
 * @param string $string The string to decode.
 * @param int $decode_n The number of bytes to decode in $input
 * @return string The decoded string.
 */
function urldecode_n( $input, $decode_n ) {
	$result = '';
	$at     = 0;
	while ( true ) {
		if ( $at + 3 > strlen( $input ) ) {
			break;
		}

		$last_at = $at;
		$at     += strcspn( $input, '%', $at );
		// Consume bytes except for the percent sign.
		$result .= substr( $input, $last_at, $at - $last_at );

		// If we've already decoded the requested number of bytes, stop.
		if ( strlen( $result ) >= $decode_n ) {
			break;
		}

		++$at;
		if ( $at > strlen( $input ) ) {
			break;
		}

		$decodable_length = strspn(
			$input,
			'0123456789ABCDEFabcdef',
			$at,
			2
		);

		if ( $decodable_length === 2 ) {
			// Decode the hex sequence.
			$result .= chr( hexdec( $input[ $at ] . $input[ $at + 1 ] ) );
			$at     += 2;
		} else {
			// Consume the next byte and move on.
			$result .= '%';
		}
	}
	$result .= substr( $input, $at );
	return $result;
}

/**
 * A generator that recursively list files in a directory.
 *
 * Example:
 *
 * ```php
 * foreach(wp_list_files_recursive('./docs') as $event) {
 *
 *    echo $event->type . " " . ($event->isFile ? 'file' : 'directory') . ' ' . $event->path . "\n";
 * }
 * // Output:
 * // entering directory ./docs
 * // listing file ./docs/file1.txt
 * // listing file ./docs/file2.txt
 * // entering directory ./docs/subdir
 * // listing file ./docs/subdir/file3.txt
 * // exiting directory ./docs/subdir
 * // exiting directory ./docs
 * ```
 *
 * @param string $dir
 * @param integer $depth
 * @yield WP_File_Visitor_Event
 * @return Iterator<WP_File_Visitor_Event>
 */
function wp_visit_file_tree( $dir ) {
	$directories = array();
	$files       = array();
	$dh          = opendir( $dir );
	while ( true ) {
		$file = readdir( $dh );
		if ( $file === false ) {
			break;
		}
		if ( '.' === $file || '..' === $file ) {
			continue;
		}
		$file_path = $dir . '/' . $file;
		if ( is_dir( $file_path ) ) {
			$directories[] = $file_path;
			continue;
		}

		$files[] = new SplFileInfo( $file_path );
	}
	closedir( $dh );

	yield new WP_File_Visitor_Event(
		WP_File_Visitor_Event::EVENT_ENTER,
		new SplFileInfo( $dir ),
		$files
	);

	foreach ( $directories as $directory ) {
		yield from wp_visit_file_tree( $directory );
	}

	yield new WP_File_Visitor_Event(
		WP_File_Visitor_Event::EVENT_EXIT,
		new SplFileInfo( $dir )
	);
}

/**
 * Import a WXR file. Used by the CLI.
 *
 * @param string $path The path to the WXR file.
 * @return void
 */
function data_liberation_import( $path ): bool {
	$importer = WP_Stream_Importer::create_for_wxr_file( $path );

	if ( ! $importer ) {
		return false;
	}

	$is_wp_cli = defined( 'WP_CLI' ) && WP_CLI;

	if ( $is_wp_cli ) {
		WP_CLI::line( "Importing from {$path}" );
	}

	while ( $importer->next_step() ) {
		// Output the current stage if running in WP-CLI.
		if ( $is_wp_cli ) {
			$current_stage = $importer->get_current_stage();
			WP_CLI::line( "Import: stage {$current_stage}" );
		}
	}

	if ( $is_wp_cli ) {
		WP_CLI::success( 'Import ended' );
	}

	return true;
}

function get_all_post_meta_flat( $post_id ) {
	return array_map(
		function ( $value ) {
			return $value[0];
		},
		get_post_meta( $post_id )
	);
}

/**
 * Polyfill the mb_str_split function used by Rowbot\URL\URL.
 *
 * Source: https://www.php.net/manual/en/function.mb-str-split.php#125429
 */
if ( ! function_exists( 'mb_str_split' ) ) {
	function mb_str_split( $input, $split_length = 1, $encoding = null ) {
		if ( null !== $input && ! \is_scalar( $input ) && ! ( \is_object( $input ) && \method_exists( $input, '__toString' ) ) ) {
			trigger_error( 'mb_str_split(): expects parameter 1 to be string, ' . \gettype( $input ) . ' given', E_USER_WARNING );
			return null;
		}
		if ( null !== $split_length && ! \is_bool( $split_length ) && ! \is_numeric( $split_length ) ) {
			trigger_error( 'mb_str_split(): expects parameter 2 to be int, ' . \gettype( $split_length ) . ' given', E_USER_WARNING );
			return null;
		}
		$split_length = (int) $split_length;
		if ( 1 > $split_length ) {
			trigger_error( 'mb_str_split(): The length of each segment must be greater than zero', E_USER_WARNING );
			return false;
		}
		if ( null === $encoding ) {
			$encoding = mb_internal_encoding();
		} else {
			$encoding = (string) $encoding;
		}
		$encoding = strtoupper( $encoding );
		if ( ! in_array( $encoding, mb_list_encodings(), true ) ) {
			static $aliases;
			if ( $aliases === null ) {
				$aliases = array();
				foreach ( mb_list_encodings() as $encoding ) {
					$encoding_aliases = mb_encoding_aliases( $encoding );
					if ( $encoding_aliases ) {
						foreach ( $encoding_aliases as $alias ) {
							$aliases[] = $alias;
						}
					}
				}
			}
			if ( ! in_array( $encoding, $aliases, true ) ) {
				trigger_error( 'mb_str_split(): Unknown encoding "' . $encoding . '"', E_USER_WARNING );
				return null;
			}
		}

		$result = array();
		$length = mb_strlen( $input, $encoding );
		for ( $i = 0; $i < $length; $i += $split_length ) {
			$result[] = mb_substr( $input, $i, $split_length, $encoding );
		}
		return $result;
	}
}
