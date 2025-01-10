<?php

use WordPress\ByteReader\WP_Byte_Reader;

/**
 * Data Liberation API: WP_WXR_Entity_Reader class
 *
 * Reads WordPress eXtended RSS (WXR) files and emits entities like posts,
 * comments, users, and terms. Enables efficient processing of large WXR
 * files without loading everything into memory.
 *
 * Note this is just a reader. It doesn't import any data into WordPress. It
 * only reads meaningful entities from the WXR file.
 *
 * ## Design goals
 *
 * WP_WXR_Entity_Reader is built with the following characteristics in mind:
 *
 * * Speed – it should be as fast as possible
 * * No PHP extensions required – it can run on any PHP installation
 * * Reliability – no random crashes when encountering malformed XML or UTF-8 sequences
 * * Low, predictable memory footprint to support 1000GB+ WXR files
 * * Ability to pause, finish execution, and resume later, e.g. after a fatal error
 *
 * ## Implementation
 *
 * `WP_WXR_Entity_Reader` uses the `WP_XML_Processor` to find XML tags representing meaningful
 * WordPress entities. The reader knows the WXR schema and only looks for relevant elements.
 * For example, it knows that posts are stored in `rss > channel > item` and comments are
 * stored in `rss > channel > item > `wp:comment`.
 *
 * The `$wxr->next_entity()` method stream-parses the next entity from the WXR document and
 * exposes it to the API consumer via `$wxr->get_entity_type()` and `$wxr->get_entity_date()`.
 * The next call to `$wxr->next_entity()` remembers where the parsing has stopped and parses
 * the next entity after that point.
 *
 * Example:
 *
 *     $reader = WP_WXR_Entity_Reader::create_for_streaming();
 *
 *     // Add data as it becomes available
 *     $reader->append_bytes( fread( $file_handle, 8192 ) );
 *
 *     // Process entities
 *     while ( $reader->next_entity() ) {
 *         switch ( $wxr_reader->get_entity_type() ) {
 *             case 'post':
 *                 // ... process post ...
 *                 break;
 *
 *             case 'comment':
 *                 // ... process comment ...
 *                 break;
 *
 *             case 'site_option':
 *                 // ... process site option ...
 *                 break;
 *
 *             // ... process other entity types ...
 *         }
 *     }
 *
 *     // Check if we need more input
 *     if ( $reader->is_paused_at_incomplete_input() ) {
 *         // Add more data and continue processing
 *         $reader->append_bytes( fread( $file_handle, 8192 ) );
 *     }
 *
 * The next_entity() -> fread -> break usage pattern may seem a bit tedious. This is expected. Even
 * if the WXR parsing part of the WP_WXR_Entity_Reader offers a high-level API, working with byte streams
 * requires reasoning on a much lower level. The StreamChain class shipped in this repository will
 * make the API consumption easier with its transformation–oriented API for chaining data processors.
 *
 * Similarly to `WP_XML_Processor`, the `WP_WXR_Entity_Reader` enters a paused state when it doesn't
 * have enough XML bytes to parse the entire entity.
 *
 * ## Caveats
 *
 * ### Extensibility
 *
 * `WP_WXR_Entity_Reader` ignores any XML elements it doesn't recognize. The WXR format is extensible
 * so in the future the  reader may start supporting registration of custom handlers for unknown
 * tags in the future.
 *
 * ### Nested entities intertwined with data
 *
 * `WP_WXR_Entity_Reader` flushes the current entity whenever another entity starts. The upside is
 * simplicity and a tiny memory footprint. The downside is that it's possible to craft a WXR
 * document where some information would be lost. For example:
 *
 * ```xml
 * <rss>
 *  <channel>
 *      <item>
 *        <title>Page with comments</title>
 *        <link>http://wpthemetestdata.wordpress.com/about/page-with-comments/</link>
 *        <wp:postmeta>
 *          <wp:meta_key>_wp_page_template</wp:meta_key>
 *          <wp:meta_value><![CDATA[default]]></wp:meta_value>
 *        </wp:postmeta>
 *        <wp:post_id>146</wp:post_id>
 *      </item>
 *  </channel>
 * </rss>
 * ```
 *
 * `WP_WXR_Entity_Reader` would accumulate post data until the `wp:post_meta` tag. Then it would emit a
 * `post` entity and accumulate the meta information until the `</wp:postmeta>` closer. Then it
 * would advance to `<wp:post_id>` and **ignore it**.
 *
 * This is not a problem in all the `.wxr` files I saw. Still, it is important to note this limitation.
 * It is possible there is a `.wxr` generator somewhere out there that intertwines post fields with post
 *  meta and comments. If this ever comes up, we could:
 *
 * * Emit the `post` entity first, then all the nested entities, and then emit a special `post_update` entity.
 * * Do multiple passes over the WXR file – one for each level of nesting, e.g. 1. Insert posts, 2. Insert Comments, 3. Insert comment meta
 *
 * Buffering all the post meta and comments seems like a bad idea – there might be gigabytes of data.
 *
 * ## Remaining work
 *
 * @TODO:
 *
 * - Revisit the need to implement the Iterator interface.
 *
 * @since WP_VERSION
 */
class WP_WXR_Entity_Reader extends WP_Entity_Reader {

	/**
	 * The XML processor used to parse the WXR file.
	 *
	 * @since WP_VERSION
	 * @var WP_XML_Processor
	 */
	private $xml;

	/**
	 * The name of the XML tag containing information about the WordPress entity
	 * currently being extracted from the WXR file.
	 *
	 * @since WP_VERSION
	 * @var string|null
	 */
	private $entity_tag;

	/**
	 * The name of the current WordPress entity, such as 'post' or 'comment'.
	 *
	 * @since WP_VERSION
	 * @var string|null
	 */
	private $entity_type;

	/**
	 * The data accumulated for the current entity.
	 *
	 * @since WP_VERSION
	 * @var array
	 */
	private $entity_data;

	/**
	 * The byte offset of the current entity in the original input stream.
	 *
	 * @since WP_VERSION
	 * @var int
	 */
	private $last_xml_byte_offset_outside_of_entity;

	/**
	 * The XML processor cursor of the last entity opener.
	 *
	 * @since WP_VERSION
	 * @var string|null
	 */
	private $last_xml_cursor_outside_of_entity;

	/**
	 * Whether the current entity has been emitted.
	 *
	 * @since WP_VERSION
	 * @var bool
	 */
	private $entity_finished = false;

	/**
	 * The number of entities read so far.
	 *
	 * @since WP_VERSION
	 * @var int
	 */
	private $entities_read_so_far = 0;

	/**
	 * The attributes from the last opening tag.
	 *
	 * @since WP_VERSION
	 * @var array
	 */
	private $last_opener_attributes = array();

	/**
	 * The ID of the last processed post.
	 *
	 * @since WP_VERSION
	 * @var int|null
	 */
	private $last_post_id = null;

	/**
	 * The ID of the last processed comment.
	 *
	 * @since WP_VERSION
	 * @var int|null
	 */
	private $last_comment_id = null;

	/**
	 * Buffer for accumulating text content between tags.
	 *
	 * @since WP_VERSION
	 * @var string
	 */
	private $text_buffer = '';

	/**
	 * Stream to pull bytes from when the input bytes are exhausted.
	 *
	 * @var WP_Byte_Reader
	 */
	private $upstream;

	/**
	 * Mapping of WXR tags representing site options to their WordPress options names.
	 * These tags are only matched if they are children of the <channel> element.
	 *
	 * @since WP_VERSION
	 * @var array
	 */
	const KNOWN_SITE_OPTIONS = array(
		'wp:base_blog_url' => 'home',
		'wp:base_site_url' => 'siteurl',
		'title' => 'blogname',
	);

	/**
	 * Mapping of WXR tags to their corresponding entity types and field mappings.
	 *
	 * @since WP_VERSION
	 * @var array
	 */
	const KNOWN_ENITIES = array(
		'wp:comment' => array(
			'type' => 'comment',
			'fields' => array(
				'wp:comment_id' => 'comment_id',
				'wp:comment_author' => 'comment_author',
				'wp:comment_author_email' => 'comment_author_email',
				'wp:comment_author_url' => 'comment_author_url',
				'wp:comment_author_IP' => 'comment_author_IP',
				'wp:comment_date' => 'comment_date',
				'wp:comment_date_gmt' => 'comment_date_gmt',
				'wp:comment_content' => 'comment_content',
				'wp:comment_approved' => 'comment_approved',
				'wp:comment_type' => 'comment_type',
				'wp:comment_parent' => 'comment_parent',
				'wp:comment_user_id' => 'comment_user_id',
			),
		),
		'wp:commentmeta' => array(
			'type' => 'comment_meta',
			'fields' => array(
				'wp:meta_key' => 'meta_key',
				'wp:meta_value' => 'meta_value',
			),
		),
		'wp:author' => array(
			'type' => 'user',
			'fields' => array(
				'wp:author_id' => 'ID',
				'wp:author_login' => 'user_login',
				'wp:author_email' => 'user_email',
				'wp:author_display_name' => 'display_name',
				'wp:author_first_name' => 'first_name',
				'wp:author_last_name' => 'last_name',
			),
		),
		'item' => array(
			'type' => 'post',
			'fields' => array(
				'title' => 'post_title',
				'link' => 'link',
				'guid' => 'guid',
				'description' => 'post_excerpt',
				'pubDate' => 'post_published_at',
				'dc:creator' => 'post_author',
				'content:encoded' => 'post_content',
				'excerpt:encoded' => 'post_excerpt',
				'wp:post_id' => 'post_id',
				'wp:status' => 'post_status',
				'wp:post_date' => 'post_date',
				'wp:post_date_gmt' => 'post_date_gmt',
				'wp:post_modified' => 'post_modified',
				'wp:post_modified_gmt' => 'post_modified_gmt',
				'wp:comment_status' => 'comment_status',
				'wp:ping_status' => 'ping_status',
				'wp:post_name' => 'post_name',
				'wp:post_parent' => 'post_parent',
				'wp:menu_order' => 'menu_order',
				'wp:post_type' => 'post_type',
				'wp:post_password' => 'post_password',
				'wp:is_sticky' => 'is_sticky',
				'wp:attachment_url' => 'attachment_url',
			),
		),
		'wp:postmeta' => array(
			'type' => 'post_meta',
			'fields' => array(
				'wp:meta_key' => 'meta_key',
				'wp:meta_value' => 'meta_value',
			),
		),
		'wp:term' => array(
			'type' => 'term',
			'fields' => array(
				'wp:term_id' => 'term_id',
				'wp:term_taxonomy' => 'taxonomy',
				'wp:term_slug' => 'slug',
				'wp:term_parent' => 'parent',
				'wp:term_name' => 'name',
			),
		),
		'wp:tag' => array(
			'type' => 'tag',
			'fields' => array(
				'wp:term_id' => 'term_id',
				'wp:tag_slug' => 'slug',
				'wp:tag_name' => 'name',
				'wp:tag_description' => 'description',
			),
		),
		'wp:category' => array(
			'type' => 'category',
			'fields' => array(
				'wp:category_nicename' => 'slug',
				'wp:category_parent' => 'parent',
				'wp:cat_name' => 'name',
				'wp:category_description' => 'description',
			),
		),
	);

	public static function create( WP_Byte_Reader $upstream = null, $cursor = null ) {
		$xml_cursor = null;
		if ( null !== $cursor ) {
			$cursor = json_decode( $cursor, true );
			if ( false === $cursor ) {
				_doing_it_wrong(
					__METHOD__,
					'Invalid cursor provided for WP_WXR_Entity_Reader::create().',
					null
				);
				return false;
			}
			$xml_cursor = $cursor['xml'];
		}

		$xml    = WP_XML_Processor::create_for_streaming( '', $xml_cursor );
		$reader = new WP_WXR_Entity_Reader( $xml );
		if ( null !== $cursor ) {
			$reader->last_post_id    = $cursor['last_post_id'];
			$reader->last_comment_id = $cursor['last_comment_id'];
		}
		if ( null !== $upstream ) {
			$reader->connect_upstream( $upstream );
			if ( null !== $cursor ) {
				if ( ! isset( $cursor['upstream'] ) ) {
					// No upstream cursor means we've processed the
					// entire input stream.
					$xml->input_finished();
					$xml->next_token();
				} else {
					$upstream->seek( $cursor['upstream'] );
				}
			}
		}
		return $reader;
	}

	/**
	 * Constructor.
	 *
	 * @since WP_VERSION
	 *
	 * @param WP_XML_Processor $xml The XML processor to use.
	 */
	protected function __construct( WP_XML_Processor $xml ) {
		$this->xml = $xml;
	}

	public function get_reentrancy_cursor() {
		/**
		 * @TODO: Instead of adjusting the XML cursor internals, adjust the get_reentrancy_cursor()
		 *        call to support $bookmark_name, e.g. $this->xml->get_reentrancy_cursor( 'last_entity' );
		 *        If the cursor internal data was a part of every bookmark, this would have worked
		 *        even after evicting the actual bytes where $last_entity is stored.
		 */
		$xml_cursor                             = $this->xml->get_reentrancy_cursor();
		$xml_cursor                             = json_decode( base64_decode( $xml_cursor ), true );
		$xml_cursor['upstream_bytes_forgotten'] = $this->last_xml_byte_offset_outside_of_entity;
		$xml_cursor                             = base64_encode( json_encode( $xml_cursor ) );
		return json_encode(
			array(
				'xml' => $xml_cursor,
				'upstream' => $this->last_xml_byte_offset_outside_of_entity,
				'last_post_id' => $this->last_post_id,
				'last_comment_id' => $this->last_comment_id,
			)
		);
	}

	/**
	 * Gets the data for the current entity.
	 *
	 * @since WP_VERSION
	 *
	 * @return WP_Imported_Entity The entity.
	 */
	public function get_entity(): WP_Imported_Entity {
		return new WP_Imported_Entity(
			$this->get_entity_type(),
			$this->entity_data
		);
	}

	/**
	 * Gets the type of the current entity.
	 *
	 * @since WP_VERSION
	 *
	 * @return string|false The entity type, or false if no entity is being processed.
	 */
	private function get_entity_type() {
		if ( null !== $this->entity_type ) {
			return $this->entity_type;
		}
		if ( null === $this->entity_tag ) {
			return false;
		}
		if ( ! array_key_exists( $this->entity_tag, static::KNOWN_ENITIES ) ) {
			return false;
		}
		return static::KNOWN_ENITIES[ $this->entity_tag ]['type'];
	}

	/**
	 * Gets the ID of the last processed post.
	 *
	 * @since WP_VERSION
	 *
	 * @return int|null The post ID, or null if no posts have been processed.
	 */
	public function get_last_post_id() {
		return $this->last_post_id;
	}

	/**
	 * Gets the ID of the last processed comment.
	 *
	 * @since WP_VERSION
	 *
	 * @return int|null The comment ID, or null if no comments have been processed.
	 */
	public function get_last_comment_id() {
		return $this->last_comment_id;
	}

	/**
	 * Appends bytes to the input stream.
	 *
	 * @since WP_VERSION
	 *
	 * @param string $bytes The bytes to append.
	 */
	public function append_bytes( string $bytes ): void {
		$this->xml->append_bytes( $bytes );
	}

	/**
	 * Marks the input as finished.
	 *
	 * @since WP_VERSION
	 */
	public function input_finished(): void {
		$this->xml->input_finished();
	}

	/**
	 * Checks if processing is finished.
	 *
	 * @since WP_VERSION
	 *
	 * @return bool Whether processing is finished.
	 */
	public function is_finished(): bool {
		return $this->xml->is_finished();
	}

	/**
	 * Checks if processing is paused waiting for more input.
	 *
	 * @since WP_VERSION
	 *
	 * @return bool Whether processing is paused.
	 */
	public function is_paused_at_incomplete_input(): bool {
		return $this->xml->is_paused_at_incomplete_input();
	}

	/**
	 * Gets the last error that occurred.
	 *
	 * @since WP_VERSION
	 *
	 * @return string|null The error message, or null if no error occurred.
	 */
	public function get_last_error(): ?string {
		return $this->xml->get_last_error();
	}

	/**
	 * Advances to the next entity in the WXR file.
	 *
	 * @since WP_VERSION
	 *
	 * @return bool Whether another entity was found.
	 */
	public function next_entity() {
		while ( true ) {
			if ( $this->read_next_entity() ) {
				return true;
			}
			// If the read failed because of incomplete input data,
			// try pulling more bytes from upstream before giving up.
			if (
				$this->is_paused_at_incomplete_input() &&
				$this->pull_upstream_bytes()
			) {
				continue;
			}
			return false;
		}
	}

	/**
	 * Advances to the next entity in the WXR file.
	 *
	 * @since WP_VERSION
	 *
	 * @return bool Whether another entity was found.
	 */
	private function read_next_entity() {
		if ( $this->xml->is_finished() ) {
			$this->after_entity();
			return false;
		}

		if ( $this->xml->is_paused_at_incomplete_input() ) {
			return false;
		}

		/**
		 * This is the first call after emitting an entity.
		 * Remove the previous entity details from the internal state
		 * and prepare for the next entity.
		 */
		if ( $this->entity_type && $this->entity_finished ) {
			$this->after_entity();
			// If we finished processing the entity on a closing tag, advance the XML processor to
			// the next token. Otherwise the array_key_exists( $tag, static::KNOWN_ENITIES ) branch
			// below will cause an infinite loop.
			if ( $this->xml->is_tag_closer() ) {
				if ( false === $this->xml->next_token() ) {
					return false;
				}
			}
		}

		/**
		 * Main parsing loop. It advances the XML parser state until a full entity
		 * is available.
		 */
		do {
			$breadcrumbs = $this->xml->get_breadcrumbs();
			// Don't process anything outside the <rss> <channel> hierarchy.
			if (
				count( $breadcrumbs ) < 2 ||
				$breadcrumbs[0] !== 'rss' ||
				$breadcrumbs[1] !== 'channel'
			) {
				continue;
			}

			/*
			 * Buffer text and CDATA sections until we find the next tag.
			 * Each tag may contain multiple text or CDATA sections so we can't
			 * just assume that a single `get_modifiable_text()` call would get
			 * the entire text content of an element.
			 */
			if (
				$this->xml->get_token_type() === '#text' ||
				$this->xml->get_token_type() === '#cdata-section'
			) {
				$this->text_buffer .= $this->xml->get_modifiable_text();
				continue;
			}

			// We're only interested in tags after this point.
			if ( $this->xml->get_token_type() !== '#tag' ) {
				continue;
			}

			if ( count( $breadcrumbs ) <= 2 && $this->xml->is_tag_opener() ) {
				$this->last_xml_byte_offset_outside_of_entity = $this->xml->get_token_byte_offset_in_the_input_stream();
				$this->last_xml_cursor_outside_of_entity      = $this->xml->get_reentrancy_cursor();
			}

			$tag = $this->xml->get_tag();
			/**
			 * Custom adjustment: the Accessibility WXR file uses a non-standard
			 * wp:wp_author tag.
			 * @TODO: Should WP_WXR_Entity_Reader care about such non-standard tags when
			 *        the regular WXR importer would ignore them? Perhaps a warning
			 *        and an upstream PR would be a better solution.
			 */
			if ( $tag === 'wp:wp_author' ) {
				$tag = 'wp:author';
			}

			/**
			 * If the tag is a known entity root, assume the previous entity is
			 * finished, emit it, and start processing the new entity the next
			 * time this function is called.
			 */
			if ( array_key_exists( $tag, static::KNOWN_ENITIES ) ) {
				if ( $this->entity_type && ! $this->entity_finished ) {
					$this->emit_entity();
					return true;
				}
				$this->after_entity();
				// Only tag openers indicate a new entity. Closers just mean
				// the previous entity is finished.
				if ( $this->xml->is_tag_opener() ) {
					$this->set_entity_tag( $tag );
					$this->last_xml_byte_offset_outside_of_entity = $this->xml->get_token_byte_offset_in_the_input_stream();
					$this->last_xml_cursor_outside_of_entity      = $this->xml->get_reentrancy_cursor();
				}
				continue;
			}

			/**
			 * We're inside of an entity tag at this point.
			 *
			 * The following code assumes that we'll only see three types of tags:
			 *
			 * * Empty elements – such as <wp:comment_content />, that we'll ignore
			 * * XML element openers with only text nodes inside them.
			 * * XML element closers.
			 *
			 * Specifically, we don't expect to see any nested XML elements such as:
			 *
			 *     <wp:comment_content>
			 *         <title>Pygmalion</title>
			 *         Long time ago...
			 *     </wp:comment_content>
			 *
			 * The semantics of such a structure is not clear. The WP_WXR_Entity_Reader will
			 * enter an error state when it encounters such a structure.
			 *
			 * Such nesting wasn't found in any WXR files analyzed when building
			 * this class. If it actually is a part of the WXR standard, every
			 * supported nested element will need a custom handler.
			 */

			/**
			 * Buffer the XML tag opener attributes for later use.
			 *
			 * In WXR files, entity attributes come from two sources:
			 * * XML attributes on the tag itself
			 * * Text content between the opening and closing tags
			 *
			 * We store the XML attributes when encountering an opening tag,
			 * but wait until the closing tag to process the entity attributes.
			 * Why? Because only at that point we have both the attributes
			 * and all the related text nodes.
			 */
			if ( $this->xml->is_tag_opener() ) {
				$this->last_opener_attributes = array();
				$names                        = $this->xml->get_attribute_names_with_prefix( '' );
				foreach ( $names as $name ) {
					$this->last_opener_attributes[ $name ] = $this->xml->get_attribute( $name );
				}
				$this->text_buffer = '';

				$is_site_option_opener = (
					count( $this->xml->get_breadcrumbs() ) === 3 &&
					$this->xml->matches_breadcrumbs( array( 'rss', 'channel', '*' ) ) &&
					array_key_exists( $this->xml->get_tag(), static::KNOWN_SITE_OPTIONS )
				);
				if ( $is_site_option_opener ) {
					$this->last_xml_byte_offset_outside_of_entity = $this->xml->get_token_byte_offset_in_the_input_stream();
				}
				continue;
			}

			/**
			 * At this point we're looking for the nearest tag closer so we can
			 * turn the buffered data into an entity attribute.
			 */
			if ( ! $this->xml->is_tag_closer() ) {
				continue;
			}

			if (
				! $this->entity_finished &&
				$this->xml->get_breadcrumbs() === array( 'rss', 'channel' )
			) {
				// Look for site options in children of the <channel> tag.
				if ( $this->parse_site_option() ) {
					return true;
				} else {
					// Keep looking for an entity if none was found in the current tag.
					continue;
				}
			}

			/**
			 * Special handling to accumulate categories stored inside the <category>
			 * tag found inside <item> tags.
			 *
			 * For example, we want to convert this:
			 *
			 *     <category><![CDATA[Uncategorized]]></category>
			 *     <category domain="category" nicename="wordpress">
			 *         <![CDATA[WordPress]]>
			 *     </category>
			 *
			 * Into this:
			 *
			 *     'terms' => [
			 *         [ 'taxonomy' => 'category', 'slug' => '', 'description' => 'Uncategorized' ],
			 *         [ 'taxonomy' => 'category', 'slug' => 'WordPress', 'description' => 'WordPress' ],
			 *     ]
			 */
			if (
				$this->entity_type === 'post' &&
				$tag === 'category' &&
				array_key_exists( 'domain', $this->last_opener_attributes ) &&
				array_key_exists( 'nicename', $this->last_opener_attributes )
			) {
				$this->entity_data['terms'][] = array(
					'taxonomy' => $this->last_opener_attributes['domain'],
					'slug' => $this->last_opener_attributes['nicename'],
					'description' => $this->text_buffer,
				);
				$this->text_buffer            = '';
				continue;
			}

			/**
			 * Store the text content of known tags as the value of the corresponding
			 * entity attribute as defined by the KNOWN_ENITIES mapping.
			 *
			 * Ignores tags unlisted in the KNOWN_ENITIES mapping.
			 *
			 * The WXR format is extensible so this reader could potentially
			 * support registering custom handlers for unknown tags in the future.
			 */
			if ( ! isset( static::KNOWN_ENITIES[ $this->entity_tag ]['fields'][ $tag ] ) ) {
				continue;
			}

			$key                       = static::KNOWN_ENITIES[ $this->entity_tag ]['fields'][ $tag ];
			$this->entity_data[ $key ] = $this->text_buffer;
			$this->text_buffer         = '';
		} while ( $this->xml->next_token() );

		if ( $this->is_paused_at_incomplete_input() ) {
			return false;
		}

		/**
		 * Emit the last unemitted entity after parsing all the data.
		 */
		if (
			$this->is_finished() &&
			$this->entity_type &&
			! $this->entity_finished
		) {
			$this->emit_entity();
			return true;
		}

		return false;
	}

	/**
	 * Emits a site option entity from known children of the <channel>
	 * tag, e.g. <wp:base_blog_url> or <title>.
	 *
	 * @return bool Whether a site_option entity was emitted.
	 */
	private function parse_site_option() {
		if ( ! array_key_exists( $this->xml->get_tag(), static::KNOWN_SITE_OPTIONS ) ) {
			return false;
		}

		$this->entity_type = 'site_option';
		$this->entity_data = array(
			'option_name' => static::KNOWN_SITE_OPTIONS[ $this->xml->get_tag() ],
			'option_value' => $this->text_buffer,
		);
		$this->emit_entity();
		return true;
	}

	/**
	 * Connects a byte stream to automatically pull bytes from once
	 * the last input chunk have been processed.
	 *
	 * @param WP_Byte_Reader $stream The upstream stream.
	 */
	public function connect_upstream( WP_Byte_Reader $stream ) {
		$this->upstream = $stream;
	}

	/**
	 * Appends another chunk of bytes from upstream if available.
	 */
	private function pull_upstream_bytes() {
		if ( ! $this->upstream ) {
			return false;
		}
		if ( ! $this->upstream->next_bytes() ) {
			if ( $this->upstream->is_finished() ) {
				$this->input_finished();
			}
			return false;
		}
		$this->append_bytes( $this->upstream->get_bytes() );
		return true;
	}

	/**
	 * Marks the current entity as emitted and updates tracking variables.
	 *
	 * @since WP_VERSION
	 */
	private function emit_entity() {
		if ( $this->entity_type === 'post' ) {
			// Not all posts have a `<wp:post_id>` tag.
			$this->last_post_id = $this->entity_data['post_id'] ?? null;
		} elseif ( $this->entity_type === 'post_meta' ) {
			$this->entity_data['post_id'] = $this->last_post_id;
		} elseif ( $this->entity_type === 'comment' ) {
			$this->last_comment_id        = $this->entity_data['comment_id'];
			$this->entity_data['post_id'] = $this->last_post_id;
		} elseif ( $this->entity_type === 'comment_meta' ) {
			$this->entity_data['comment_id'] = $this->last_comment_id;
		} elseif ( $this->entity_type === 'tag' ) {
			$this->entity_data['taxonomy'] = 'post_tag';
		} elseif ( $this->entity_type === 'category' ) {
			$this->entity_data['taxonomy'] = 'category';
		}
		$this->entity_finished = true;
		++$this->entities_read_so_far;
	}

	/**
	 * Sets the current entity tag and type.
	 *
	 * @since WP_VERSION
	 *
	 * @param string $tag The entity tag name.
	 */
	private function set_entity_tag( string $tag ) {
		$this->entity_tag = $tag;
		if ( array_key_exists( $tag, static::KNOWN_ENITIES ) ) {
			$this->entity_type = static::KNOWN_ENITIES[ $tag ]['type'];
		}
	}

	/**
	 * Resets the state after processing an entity.
	 *
	 * @since WP_VERSION
	 */
	private function after_entity() {
		$this->entity_tag             = null;
		$this->entity_type            = null;
		$this->entity_data            = array();
		$this->entity_finished        = false;
		$this->text_buffer            = '';
		$this->last_opener_attributes = array();
	}

	public function current(): object {
		// Lazily initialize the iterator when it is first accessed.
		// The alternative is eager initialization in the constructor.
		if ( null === $this->entity_data && ! $this->is_finished() && ! $this->get_last_error() ) {
			$this->next();
		}
		return $this->get_entity();
	}

	private $last_next_result = null;
	public function next(): void {
		// @TODO: Don't keep track of this. Just make sure the next_entity()
		//        call will make the is_finished() true.
		$this->last_next_result = $this->next_entity();
	}

	public function key(): string {
		return $this->get_reentrancy_cursor();
	}

	public function valid(): bool {
		return false !== $this->last_next_result && ! $this->is_finished() && ! $this->get_last_error();
	}

	public function rewind(): void {
		// Haven't started yet.
		if ( null === $this->last_next_result ) {
			return;
		}
		_doing_it_wrong(
			__METHOD__,
			'WP_WXR_Reader does not support rewinding.',
			null
		);
	}
}
