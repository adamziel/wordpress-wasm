<?php
use Rowbot\URL\URL;

/**
 * Migrate URLs in post content. See WPRewriteUrlsTests for
 * specific examples. TODO: A better description.
 *
 * Example:
 *
 * ```php
 * php > wp_rewrite_urls('<!-- wp:image {"src": "http://legacy-blog.com/image.jpg"} -->')
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
		$options['base_url'] = $options['current-site-url'];
	}

	$current_site_url = WP_URL::parse( $options['current-site-url'] );
	if ( $current_site_url->pathname[ strlen( $current_site_url->pathname ) - 1 ] === '/' ) {
		$current_site_url->pathname = substr( $current_site_url->pathname, 0, strlen( $current_site_url->pathname ) - 1 );
	}
	$current_site_pathname_with_trailing_slash = $current_site_url->pathname === '/' ? $current_site_url->pathname : $current_site_url->pathname . '/';
	$current_site_url_string                   = $current_site_url->toString();

	$new_site_url = WP_URL::parse( $options['new-site-url'] );
	if ( $new_site_url->pathname[ strlen( $new_site_url->pathname ) - 1 ] === '/' ) {
		$new_site_url->pathname = substr( $new_site_url->pathname, 0, strlen( $new_site_url->pathname ) - 1 );
	}
	$new_site_pathname_with_trailing_slash =
		$new_site_url->pathname === '/' ? $new_site_url->pathname : $new_site_url->pathname . '/';

	$p = new WP_Block_Markup_Url_Processor( $options['block_markup'], $options['base_url'] );
	while ( $p->next_url() ) {
		if ( ! url_matches( $p->get_parsed_url(), $current_site_url_string ) ) {
			continue;
		}
		$raw_url = $p->get_raw_url();

		$parsed_matched_url           = $p->get_parsed_url();
		$parsed_matched_url->protocol = $new_site_url->protocol;
		$parsed_matched_url->hostname = $new_site_url->hostname;

		// Update the pathname if needed.
		if ( '/' !== $current_site_url->pathname ) {
			/**
			 * The matched URL starts with $current_site_name->pathname.
			 * 
			 * We want to retain the portion of the pathname that comes
			 * after $current_site_name->pathname. This is not a simple
			 * substring operation because the matched URL may have
			 * urlencoded bytes at the beginning. We need to decode
			 * them before taking the substring.
			 * 
			 * However, we can't just decode the entire pathname because
			 * the part after $current_site_name->pathname may itself
			 * contain urlencoded bytes. If we decode them here, it
			 * may change a path such as `/%2561/foo`, which decodes
			 * as `/61/foo`, to `/a/foo`.
			 * 
			 * Therefore, we're going to decode a part of the string. We'll
			 * start at the beginning and keep going until we've found
			 * enough decoded bytes to skip over $current_site_name->pathname.
			 * Then we'll take the remaining, still encoded bytes as the new pathname.
			 */
			$decoded_matched_pathname = urldecode_n(
				$parsed_matched_url->pathname,
				strlen( $current_site_pathname_with_trailing_slash )
			);
			$parsed_matched_url->pathname =
				$new_site_pathname_with_trailing_slash .
					substr(
						$decoded_matched_pathname,
						strlen( $current_site_pathname_with_trailing_slash )
					);
		}

		/*
		 * Stylistic choice – if the matched URL has no trailing slash,
		 * do not add it to the new URL. The WHATWG URL parser will
		 * add one automatically if the path is empty, so we have to
		 * explicitly remove it.
		 */
		$new_raw_url     = $parsed_matched_url->toString();
		$raw_matched_url = $p->get_raw_url();
		if (
			$raw_matched_url[ strlen( $raw_matched_url ) - 1 ] !== '/' &&
			$parsed_matched_url->pathname === '/' &&
			$parsed_matched_url->search === '' &&
			$parsed_matched_url->hash === ''
		) {
			$new_raw_url = rtrim( $new_raw_url, '/' );
		}
		if ( $new_raw_url ) {
			$is_relative = (
				// Ensure protocol-less URLs coming from text nodes
				// are not treated as relative.
				//
				// We're only capturing absolute URLs from text nodes,
				// but some of them may look like relative URLs to the
				// URL parser. For example, "mysite.com/path" would
				// be parsed as a relative URL.
				$p->get_token_type() !== '#text' &&
				! str_starts_with( $raw_url, 'http://' ) &&
				! str_starts_with( $raw_url, 'https://' )
			);
			if ( $is_relative ) {
				$new_relative_url = $parsed_matched_url->pathname;
				if ( $parsed_matched_url->search !== '' ) {
					$new_relative_url .= $parsed_matched_url->search;
				}
				if ( $parsed_matched_url->hash !== '' ) {
					$new_relative_url .= $parsed_matched_url->hash;
				}
				$p->set_raw_url( $new_relative_url );
			} else {
				$p->set_raw_url( $new_raw_url );
			}
		}
	}
	return $p->get_updated_html();
}
/**
 * Check if a given URL matches the current site URL.
 *
 * @param URL $subject The URL to check.
 * @param string $current_site_url_no_trailing_slash The current site URL to compare against.
 * @return bool Whether the URL matches the current site URL.
 */
function url_matches( URL $subject, string $current_site_url_no_trailing_slash ) {
	$parsed_current_site_url            = WP_URL::parse( $current_site_url_no_trailing_slash );
	$current_pathname_no_trailing_slash = rtrim( urldecode( $parsed_current_site_url->pathname ), '/' );

	if ( $subject->hostname !== $parsed_current_site_url->hostname ) {
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
function urldecode_n( $string, $target_length ) {
	$result = '';
	$at = 0;
	while(true) {
		if($at + 3 > strlen($string)) {
			break;
		}

		$last_at = $at;
		$at += strcspn( $string, '%', $at );
		// Consume bytes except for the percent sign.
		$result .= substr( $string, $last_at, $at - $last_at );

		// If we've already decoded the requested number of bytes, stop.
		if(strlen($result) >= $target_length) {
			break;
		}

		++$at;
		$decodable_length = strspn(
			$string,
			'0123456789ABCDEFabcdef',
			$at,
			2
		);
		if($decodable_length === 2) {
			// Decode the hex sequence.
			$result .= chr(hexdec($string[$at] . $string[$at + 1]));
			$at += 2;
		} else {
			// Consume the percent sign and move on.
			$result .= '%';
		}
	}
	$result .= substr($string, $at);
	return $result;
}
