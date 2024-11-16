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
			if ( url_matches( $parsed_url, $mapping['from_url'] ) ) {
				$p->rewrite_url_components( $mapping['from_url'], $mapping['to_url'] );
				break;
			}
		}
	}
	return $p->get_updated_html();
}

/**
 * Check if a given URL matches the current site URL.
 *
 * @param URL $subject The URL to check.
 * @param string $from_url_no_trailing_slash The current site URL to compare against.
 * @return bool Whether the URL matches the current site URL.
 */
function url_matches( URL $subject, $from_url ) {
	$parsed_from_url                    = is_string( $from_url ) ? WP_URL::parse( $from_url ) : $from_url;
	$current_pathname_no_trailing_slash = rtrim( urldecode( $parsed_from_url->pathname ), '/' );

	if ( $subject->hostname !== $parsed_from_url->hostname ) {
		return false;
	}

	$matched_pathname_decoded = urldecode( $subject->pathname );
	return (
		// Direct match
		$matched_pathname_decoded === $current_pathname_no_trailing_slash ||
		$matched_pathname_decoded === $current_pathname_no_trailing_slash . '/' ||
		// Path prefix
		str_starts_with( $matched_pathname_decoded, $current_pathname_no_trailing_slash . '/' )
	);
}

/**
 * Decodes the first n **encoded bytes** a URL-encoded string.
 *
 * For example, `urldecode_n( '%22is 6 %3C 6?%22 – asked Achilles', 1 )` returns
 * '"is 6 %3C 6?%22 – asked Achilles' because only the first encoded byte is decoded.
 *
 * @param string $string The string to decode.
 * @param int $target_length The maximum length of the resulting string.
 * @return string The decoded string.
 */
function urldecode_n( $input, $target_length ) {
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
		if ( strlen( $result ) >= $target_length ) {
			break;
		}

		++$at;
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
			// Consume the percent sign and move on.
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
	while ( ( $file = readdir( $dh ) ) !== false ) {
		if ( '.' === $file || '..' === $file ) {
			continue;
		}
		$filePath = $dir . '/' . $file;
		if ( is_dir( $filePath ) ) {
			$directories[] = $filePath;
			continue;
		}

		$files[] = new SplFileInfo( $filePath );
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

class WP_File_Visitor {
	private $dir;
	private $directories = array();
	private $files       = array();
	private $currentEvent;
	private $iteratorStack = array();
	private $currentIterator;
	private $depth = 0;

	public function __construct( $dir ) {
		$this->dir             = $dir;
		$this->iteratorStack[] = $this->createIterator( $dir );
	}

	public function get_current_depth() {
		return $this->depth;
	}

	public function get_root_dir() {
		return $this->dir;
	}

	private function createIterator( $dir ) {
		$this->directories = array();
		$this->files       = array();

		$dh = opendir( $dir );
		if ( $dh === false ) {
			return new ArrayIterator( array() );
		}

		while ( ( $file = readdir( $dh ) ) !== false ) {
			if ( '.' === $file || '..' === $file ) {
				continue;
			}
			$filePath = $dir . '/' . $file;
			if ( is_dir( $filePath ) ) {
				$this->directories[] = $filePath;
				continue;
			}
			$this->files[] = new SplFileInfo( $filePath );
		}
		closedir( $dh );

		$events = array(
			new WP_File_Visitor_Event( WP_File_Visitor_Event::EVENT_ENTER, new SplFileInfo( $dir ), $this->files ),
		);

		foreach ( $this->directories as $directory ) {
			$events[] = $directory; // Placeholder for recursion
		}

		$events[] = new WP_File_Visitor_Event( WP_File_Visitor_Event::EVENT_EXIT, new SplFileInfo( $dir ) );

		return new ArrayIterator( $events );
	}

	public function next() {
		while ( ! empty( $this->iteratorStack ) ) {
			$this->currentIterator = end( $this->iteratorStack );

			if ( $this->currentIterator->valid() ) {
				$current = $this->currentIterator->current();
				$this->currentIterator->next();

				if ( $current instanceof WP_File_Visitor_Event ) {
					if ( $current->isEntering() ) {
						++$this->depth;
					}
					$this->currentEvent = $current;
					if ( $current->isExiting() ) {
						--$this->depth;
					}
					return true;
				} else {
					// It's a directory path, push a new iterator onto the stack
					$this->iteratorStack[] = $this->createIterator( $current );
				}
			} else {
				array_pop( $this->iteratorStack );
			}
		}

		return false;
	}

	public function get_event() {
		return $this->currentEvent;
	}
}


class WP_File_Visitor_Event {
	public $type;
	public $dir;
	public $files;

	const EVENT_ENTER = 'entering';
	const EVENT_EXIT  = 'exiting';

	public function __construct( $type, $dir, $files = array() ) {
		$this->type  = $type;
		$this->dir   = $dir;
		$this->files = $files;
	}

	public function isEntering() {
		return $this->type === self::EVENT_ENTER;
	}

	public function isExiting() {
		return $this->type === self::EVENT_EXIT;
	}
}
