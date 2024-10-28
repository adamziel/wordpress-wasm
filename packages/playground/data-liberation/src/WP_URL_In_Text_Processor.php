<?php
/**
 * Finds string fragments that look like URLs and allow replacing them.
 * This is the first, "thick" sieve that yields "URL candidates" that must be
 * validated with a WHATWG-compliant parser. Some of the candidates will be
 * false positives.
 *
 * This is a "thick sieve" that matches too much instead of too little. It
 * will yield false positives, but will not miss a URL
 *
 * Looks for URLs:
 *
 * * Starting with http:// or https://
 * * Starting with //
 * * Domain-only, e.g. www.example.com
 * * Domain + path, e.g. www.example.com/path
 *
 * ### Protocols
 *
 * As a migration-oriented tool, this processor will only consider http and https protocols.
 *
 * ### Domain names
 *
 * UTF-8 characters in the domain names are supported even if they're
 * not encoded as punycode. For example, scanning the text:
 *
 * > Więcej na łąka.pl
 *
 * Would yield `łąka.pl`
 *
 * ### Paths
 *
 * The path is limited to ASCII characters, as per the URL specification.
 * For example, scanning the text:
 *
 * > Visit the WordPress plugins directory https://w.org/plugins?łąka=1
 *
 * Would yield `https://w.org/plugins?`, not `https://w.org/plugins?łąka=1`.
 * However, scanning this text:
 *
 * > Visit the WordPress plugins directory https://w.org/plugins?%C5%82%C4%85ka=1
 *
 * Would yield `https://w.org/plugins?%C5%82%C4%85ka=1`.
 *
 * ### Parenthesis treatment
 *
 * This scanner captures parentheses as a part of the path, query, or fragment, except
 * when they're seen as the last character in the URL. For example, scanning the text:
 *
 * > Visit the WordPress plugins directory (https://w.org/plugins)
 *
 * Would yield `https://w.org/plugins`, but scanning the text:
 *
 * > Visit the WordPress plugins directory (https://w.org/plug(in)s
 *
 * Would yield `https://w.org/plug(in)s`.
 */
class WP_URL_In_Text_Processor {

	private $text;
	private $url_starts_at;
	private $url_length;
	private $bytes_already_parsed = 0;
	/**
	 * @var string
	 */
	private $raw_url;
	/**
	 * @var URL
	 */
	private $parsed_url;
	private $did_prepend_protocol;
	/**
	 * The base URL for the parsing algorithm.
	 * See https://url.spec.whatwg.org/.
	 *
	 * @var mixed|null
	 */
	private $base_url;
	private $base_protocol;

	/**
	 * The regular expression pattern used for the matchin URL candidates
	 * from the text.
	 *
	 * @var string
	 */
	private $regex;

	/**
	 * @see WP_HTML_Tag_Processor
	 * @var string[]
	 */
	private $lexical_updates = array();

	/**
	 * @var bool
	 * A flag to indicate whether the URL matching should be strict or not.
	 * If set to true, the matching will be strict, meaning it will only match URLs that strictly adhere to the pattern.
	 * If set to false, the matching will be more lenient, allowing for potential false positives.
	 */
	private $strict = false;
	private static $public_suffix_list;


	public function __construct( $text, $base_url = null ) {
		if ( ! self::$public_suffix_list ) {
			// @TODO: Parse wildcards and exceptions from the public suffix list.
			self::$public_suffix_list = require_once __DIR__ . '/public_suffix_list.php';
		}
		$this->text          = $text;
		$this->base_url      = $base_url;
		$this->base_protocol = $base_url ? parse_url( $base_url, PHP_URL_SCHEME ) : null;

		$prefix = $this->strict ? '^' : '';
		$suffix = $this->strict ? '$' : '';

		// Source: https://github.com/vstelmakh/url-highlight/blob/master/src/Matcher/Matcher.php.
		$this->regex = '/' . $prefix . '
            (?:                                                      # scheme
                (?<scheme>https?:)?                                  # Only consider http and https
                \/\/                                                 # The protocol does not have to be there, but when
                                                                     # it is, is must be followed by \/\/
            )?
            (?:                                                        # userinfo
                (?:
                    (?<=\/{2})                                             # prefixed with \/\/
                    |                                                      # or
                    (?=[^\p{Sm}\p{Sc}\p{Sk}\p{P}])                         # start with not: mathematical, currency, modifier symbol, punctuation
                )
                (?<userinfo>[^\s<>@\/]+)                                   # not: whitespace, < > @ \/
                @                                                          # at
            )?
            (?=[^\p{Z}\p{Sm}\p{Sc}\p{Sk}\p{C}\p{P}])                   # followed by valid host char
            (?|                                                        # host
                (?<host>                                                   # host prefixed by scheme or userinfo (less strict)
                    (?<=\/\/|@)                                               # prefixed with \/\/ or @
                    (?=[^\-])                                                  # label start, not: -
                    (?:[^\p{Z}\p{Sm}\p{Sc}\p{Sk}\p{C}\p{P}]|-){1,63}           # label not: whitespace, mathematical, currency, modifier symbol, control point, punctuation | except -
                    (?<=[^\-])                                                 # label end, not: -
                    (?:                                                        # more label parts
                        \.
                        (?=[^\-])                                                  # label start, not: -
                        (?<tld>(?:[^\p{Z}\p{Sm}\p{Sc}\p{Sk}\p{C}\p{P}]|-){1,63})   # label not: whitespace, mathematical, currency, modifier symbol, control point, punctuation | except -
                        (?<=[^\-])                                                 # label end, not: -
                    )*
                )
                |                                                          # or
                (?<host>                                                   # host with tld (no scheme or userinfo)
                    (?=[^\-])                                                  # label start, not: -
                    (?:[^\p{Z}\p{Sm}\p{Sc}\p{Sk}\p{C}\p{P}]|-){1,63}           # label not: whitespace, mathematical, currency, modifier symbol, control point, punctuation | except -
                    (?<=[^\-])                                                 # label end, not: -
                    (?:                                                        # more label parts
                        \.
                        (?=[^\-])                                                  # label start, not: -
                        (?:[^\p{Z}\p{Sm}\p{Sc}\p{Sk}\p{C}\p{P}]|-){1,63}           # label not: whitespace, mathematical, currency, modifier symbol, control point, punctuation | except -
                        (?<=[^\-])                                                 # label end, not: -
                    )*                                                             
                    \.(?<tld>\w{2,63})                                         # tld
                )
            )
            (?:\:(?<port>\d+))?                                        # port
            (?<path>                                                   # path, query, fragment
                [\/?#]                                                 # prefixed with \/ or ? or #
                [^\s<>]*                                               # any chars except whitespace and <>
                (?<=[^\s<>({\[`!;:\'".,?«»“”‘’])                       # end with not a space or some punctuation chars
            )?
        ' . $suffix . '/ixuJ';
	}

	/**
	 * @return string
	 */
	public function next_url() {
		$this->raw_url              = null;
		$this->parsed_url           = null;
		$this->url_starts_at        = null;
		$this->url_length           = null;
		$this->did_prepend_protocol = false;
		while ( true ) {
			/**
			 * Thick sieve – eagerly match things that look like URLs but turn out to not be URLs in the end.
			 */
			$matches = array();
			$found   = preg_match( $this->regex, $this->text, $matches, PREG_OFFSET_CAPTURE, $this->bytes_already_parsed );
			if ( 1 !== $found ) {
				return false;
			}

			$matched_url = $matches[0][0];
			if (
				$matched_url[ strlen( $matched_url ) - 1 ] === ')' ||
				$matched_url[ strlen( $matched_url ) - 1 ] === '.'
			) {
				$matched_url = substr( $matched_url, 0, - 1 );
			}
			$this->bytes_already_parsed = $matches[0][1] + strlen( $matched_url );

			$had_double_slash = WP_URL::has_double_slash( $matched_url );

			$url_to_parse = $matched_url;
			if ( $this->base_url && $this->base_protocol && ! $had_double_slash ) {
				$url_to_parse               = WP_URL::ensure_protocol( $url_to_parse, $this->base_protocol );
				$this->did_prepend_protocol = true;
			}

			/*
			 * Extra fine sieve – parse the candidates using a WHATWG-compliant parser to rule out false positives.
			 */
			$parsed_url = WP_URL::parse( $url_to_parse, $this->base_url );
			if ( false === $parsed_url ) {
				continue;
			}

			// Additional rigor for URLs that are not explicitly preceded by a double slash.
			if ( false && ! $had_double_slash ) {
				/*
				 * Skip TLDs that are not in the public suffix.
				 * This reduces false positives like `index.html` or `plugins.php`.
				 *
				 * See https://publicsuffix.org/.
				 */
				$last_dot_position = strrpos( $parsed_url->hostname, '.' );
				if ( false === $last_dot_position ) {
					/*
					 * Oh, there was no dot in the hostname AND no double slash at
					 * the beginning! Let's assume this isn't a valid URL and move on.
					 * @TODO: Explore updating the regular expression above to avoid matching
					 *        URLs without a dot in the hostname when they're not preceeded
					 *        by a protocol.
					 */
					continue;
				}

				$tld = strtolower( substr( $parsed_url->hostname, $last_dot_position + 1 ) );
				if ( empty( self::$public_suffix_list[ $tld ] ) ) {
					// This TLD is not in the public suffix list. It's not a valid domain name.
					continue;
				}
			}

			$this->parsed_url    = $parsed_url;
			$this->raw_url       = $matched_url;
			$this->url_starts_at = $matches[0][1];
			$this->url_length    = strlen( $matches[0][0] );

			return true;
		}
	}

	public function get_raw_url() {
		if ( null === $this->raw_url ) {
			return false;
		}

		return $this->raw_url;
	}

	public function get_parsed_url() {
		if ( null === $this->parsed_url ) {
			return false;
		}

		return $this->parsed_url;
	}

	public function set_raw_url( $new_url ) {
		if ( null === $this->raw_url ) {
			return false;
		}
		if ( $this->did_prepend_protocol ) {
			$new_url = substr( $new_url, strpos( $new_url, '://' ) + 3 );
		}
		$this->raw_url                                 = $new_url;
		$this->lexical_updates[ $this->url_starts_at ] = new WP_HTML_Text_Replacement(
			$this->url_starts_at,
			$this->url_length,
			$new_url
		);

		return true;
	}

	private function apply_lexical_updates() {
		if ( ! count( $this->lexical_updates ) ) {
			return 0;
		}

		/*
		 * Attribute updates can be enqueued in any order but updates
		 * to the document must occur in lexical order; that is, each
		 * replacement must be made before all others which follow it
		 * at later string indices in the input document.
		 *
		 * Sorting avoid making out-of-order replacements which
		 * can lead to mangled output, partially-duplicated
		 * attributes, and overwritten attributes.
		 */

		ksort( $this->lexical_updates );

		$bytes_already_copied = 0;
		$output_buffer        = '';
		foreach ( $this->lexical_updates as $diff ) {
			$shift = strlen( $diff->text ) - $diff->length;

			// Adjust the cursor position by however much an update affects it.
			if ( $diff->start < $this->bytes_already_parsed ) {
				$this->bytes_already_parsed += $shift;
			}

			$output_buffer .= substr( $this->text, $bytes_already_copied, $diff->start - $bytes_already_copied );
			if ( $diff->start === $this->url_starts_at ) {
				$this->url_starts_at = strlen( $output_buffer );
				$this->url_length    = strlen( $diff->text );
			}
			$output_buffer       .= $diff->text;
			$bytes_already_copied = $diff->start + $diff->length;
		}

		$this->text            = $output_buffer . substr( $this->text, $bytes_already_copied );
		$this->lexical_updates = array();
	}

	public function get_updated_text() {
		$this->apply_lexical_updates();

		return $this->text;
	}

	/**
	 * Characters that are forbidden in the host part of a URL.
	 * See https://url.spec.whatwg.org/#host-miscellaneous.
	 */
	private const FORBIDDEN_HOST_BYTES   = "\x00\x09\x0a\x0d\x20\x23\x2f\x3a\x3c\x3e\x3f\x40\x5b\x5c\x5d\x5e\x7c";
	private const FORBIDDEN_DOMAIN_BYTES = "\x00\x09\x0a\x0d\x20\x23\x25\x2f\x3a\x3c\x3e\x3f\x40\x5b\x5c\x5d\x5e\x7c\x7f";
	/**
	 * Unlike RFC 3986, the WHATWG URL specification does not the domain part of
	 * a URL to any length. That being said, we apply an arbitrary limit here as
	 * an optimization to avoid scanning the entire text for a domain name.
	 *
	 * Rationale: Domains larger than 1KB are extremely rare. The WHATWG URL
	 */
	private const CONSIDER_DOMAINS_UP_TO_BYTES = 1024;

	/**
	 * An exploration to match URLs without using regular expressions.
	 * Need to benchmark and rigorously test the current next_url()
	 * implementation. We may either:
	 *
	 * * Be fine with preg_match in next_url()
	 * * Need a custom implementation like this one
	 * * Be forced to ditch this approach entirely and find a way to plug
	 *   in a proper WHATWG-compliant URL parser into the task of finding
	 *   URLs in text. This may or may not be possible/viable.
	 *
	 * @wip
	 */
	private function experimental_next_url_without_regexs() {
		$at = $this->bytes_already_parsed;

		// Find the next dot in the text.
		$dot_at = strpos( $this->text, '.', $at );

		// If there's no dot, assume there's no URL.
		if ( false === $dot_at ) {
			return false;
		}

		// The shortest tld is 2 characters long.
		if ( $dot_at + 2 >= strlen( $this->text ) ) {
			return false;
		}

		$host_bytes_after_dot = strcspn(
			$this->text,
			self::FORBIDDEN_DOMAIN_BYTES,
			$dot_at + 1,
			self::CONSIDER_DOMAINS_UP_TO_BYTES
		);

		if ( 0 === $host_bytes_after_dot ) {
			return false;
		}

		// Lookbehind to capture the rest of the domain name up to a forbidden character.
		$host_bytes_before_dot = strcspn(
			$this->text_rev,
			self::FORBIDDEN_DOMAIN_BYTES,
			strlen( $this->text ) - $dot_at - 1,
			self::CONSIDER_DOMAINS_UP_TO_BYTES
		);

		$host_starts_at = $dot_at - $host_bytes_before_dot;

		// Capture the protocol, if any.
		$has_double_slash = false;
		if ( $host_starts_at > 2 ) {
			if ( '/' === $this->text[ $host_starts_at - 1 ] && '/' === $this->text[ $host_starts_at - 2 ] ) {
				$has_double_slash = true;
			}
		}

		/**
		 * Look for http or https at the beginning of the URL.
		 *
		 * @TODO: Ensure the character before http or https is a word boundary.
		 */
		$has_protocol = false;
		if ( $has_double_slash && (
				(
					$host_starts_at >= 6 &&
					'h' === $this->text[ $host_starts_at - 6 ] &&
					't' === $this->text[ $host_starts_at - 5 ] &&
					't' === $this->text[ $host_starts_at - 4 ] &&
					'p' === $this->text[ $host_starts_at - 3 ]
				) ||
				(
					$host_starts_at >= 7 &&
					'h' === $this->text[ $host_starts_at - 7 ] &&
					't' === $this->text[ $host_starts_at - 6 ] &&
					't' === $this->text[ $host_starts_at - 5 ] &&
					'p' === $this->text[ $host_starts_at - 4 ] &&
					's' === $this->text[ $host_starts_at - 3 ]
				)
			) ) {
			$has_protocol = true;
		}

		// Move the pointer to the end of the host.
		$at = $dot_at + $host_bytes_after_dot;
	}
}
