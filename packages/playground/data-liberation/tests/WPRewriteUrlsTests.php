<?php

use PHPUnit\Framework\TestCase;

class WPRewriteUrlsTests extends TestCase {

	/**
	 *
	 * @dataProvider provider_test_wp_rewrite_urls
	 */
	public function test_wp_rewrite_urls(
		$original_markup,
		$expected_markup,
		$current_site_url,
		$new_site_url
	) {
		$result = wp_rewrite_urls( array(
			'block_markup' => $original_markup,
			'url-mapping' => [
				$current_site_url => $new_site_url,
			],
		) );
		$this->assertEquals( $expected_markup, $result, 'Failed to migrate the URLs in the block markup' );
	}

	static public function provider_test_wp_rewrite_urls() {
		return [
			'Domain in a block attribute' => [ 
				'<!-- wp:image {"src": "http://legacy-blog.com/image.jpg"} -->',
				'<!-- wp:image {"src":"https:\/\/modern-webstore.org\/image.jpg"} -->',
				'https://legacy-blog.com',
				'https://modern-webstore.org'
			],
			'Domain in a block attribute expressed with JSON UTF-8 escape sequences' => [ 
				'<!-- wp:image {"src": "https:\/\/\u006c\u0065\u0067\u0061\u0063y-bl\u006fg.\u0063\u006fm/wp-content/image.png"} -->',
				'<!-- wp:image {"src":"https:\/\/modern-webstore.org\/wp-content\/image.png"} -->',
				'https://legacy-blog.com',
				'https://modern-webstore.org'
			],
			'Domain in an HTML attribute semantically expressing a URL' => [ 
				<<<HTML
				<a href="https://legacy-blog.com/contact-us/">Contact us</a>
				<img src="https://legacy-blog.com/wp-content/assets/image.jpg">
				<link rel="stylesheet" href="https://legacy-blog.com/style.css">
				<script src="https://legacy-blog.com/main.js"></script>
				HTML,
				<<<HTML
				<a href="https://modern-webstore.org/contact-us/">Contact us</a>
				<img src="https://modern-webstore.org/wp-content/assets/image.jpg">
				<link rel="stylesheet" href="https://modern-webstore.org/style.css">
				<script src="https://modern-webstore.org/main.js"></script>
				HTML,
				'https://legacy-blog.com',
				'https://modern-webstore.org'
			],
			'Path in an HTML attribute semantically expressing a URL – source path has no trailing slash' => [ 
				'<a href="/~jappleseed/1997.10.1/nuclear-fusion/">Nuclear fusion</a>',
				'<a href="/blog/nuclear-fusion/">Nuclear fusion</a>',
				'https://legacy-blog.com/~jappleseed/1997.10.1',
				'https://modern-webstore.org/blog/'
			],
			'Path in an HTML attribute semantically expressing a URL – source path has a trailing slash' => [ 
				'<a href="/~jappleseed/1997.10.1/nuclear-fusion/">Nuclear fusion</a>',
				'<a href="/blog/nuclear-fusion/">Nuclear fusion</a>',
				'https://legacy-blog.com/~jappleseed/1997.10.1/',
				'https://modern-webstore.org/blog/'
			],
			/**
			 * The urlencoded data needs to stay urlencoded. If it's decoded, the
			 * resulting path will be wrongly rewritten as
			 * 
			 * "/blog/%65-reasons-to-migrate-data/"
			 * 
			 * Which decodes to:
			 * 
			 * "/blog/a-reasons-to-migrate-data/"
			 * 
			 * But if the path is not decoded, it correctly becomes
			 * 
			 * "/blog/%2565-reasons-to-migrate-data/"
			 * 
			 * Which decodes to:
			 * 
			 * "/blog/%65-reasons-to-migrate-data/"
			 */
			'Path in an HTML attribute with URLencoded data' => [ 
				'<a href="/~jappleseed/1997.10.1/%2561-reasons-to-migrate-data/">61 reasons to migrate data</a>',
				'<a href="/blog/%2561-reasons-to-migrate-data/">61 reasons to migrate data</a>',
				'https://legacy-blog.com/~jappleseed/1997.10.1/',
				'https://modern-webstore.org/blog/'
			],
			'Domain in an HTML attribute – encoded using HTML entities' => [ 
				'<a href="&#104;&#116;tps://&#108;&#101;g&#97;&#99;&#121;&#45;&#98;&#108;&#111;&#103;.&#99;&#111;&#109;/pages/contact-us">Contact us</a>',
				'<a href="https://modern-webstore.org/pages/contact-us">Contact us</a>',
				'https://legacy-blog.com',
				'https://modern-webstore.org'
			],
			// 'Domain in an HTML attribute semantically expressing text' => [  
			// 	'<img alt="Johnny Appleseed, the founder of https://legacy-blog.com/">',
			// 	'<img alt="Johnny Appleseed, the founder of https://modern-webstore.org/">',
			// 	'https://legacy-blog.com',
			// 	'https://modern-webstore.org'
			// ],
			// @TODO Is that actually a thing? Can we distinguish between "special tokens"
			//       (such as CSS classes) and "text" (such as the alt attribute)?
			// 'Ignores domains in HTML attributes semantically expressing data different that text or URLs' => [ 
			// 	'<h1 class="https://legacy-blog.com/">CSS quirks – anything can be a class</h1>',
			// 	'<h1 class="https://legacy-blog.com/">CSS quirks – anything can be a class</h1>',
			// 	'https://legacy-blog.com',
			// 	'https://modern-webstore.org'
			// ],
			"Domain in a regular text snippet – preceeded by a protocol" => [ 
				'Join the team at https://legacy-blog.com/we-are-hiring',
				'Join the team at https://modern-webstore.org/we-are-hiring',
				'https://legacy-blog.com',
				'https://modern-webstore.org'
			],
			"Domain in a regular text snippet – not preceeded by a protocol" => [ 
				'Join the team at legacy-blog.com/we-are-hiring',
				'Join the team at modern-webstore.org/we-are-hiring',
				'https://legacy-blog.com',
				'https://modern-webstore.org'
			],
			'Domain in a regular text snippet – preceeded by a protocol – encoded using HTML entities' => [ 
				'Join the team at https://&#108;&#101;g&#97;&#99;&#121;&#45;&#98;&#108;&#111;&#103;.&#99;&#111;&#109;/pages/contact-us',
				'Join the team at https://modern-webstore.org/pages/contact-us',
				'https://legacy-blog.com',
				'https://modern-webstore.org'
			],
			'Domain in a regular text snippet – no protocol – encoded using HTML entities' => [ 
				'Join the team at &#108;&#101;g&#97;&#99;&#121;&#45;&#98;&#108;&#111;&#103;.&#99;&#111;&#109;/pages/contact-us',
				'Join the team at modern-webstore.org/pages/contact-us',
				'https://legacy-blog.com',
				'https://modern-webstore.org'
			],
			"Ignores lookalikes: retains legacy-blog.comdot in a regular text snippet when migrating legacy-blog.com" => [
				'Join the team at legacy-blog.comdot/we-are-hiring',
				'Join the team at legacy-blog.comdot/we-are-hiring',
				'https://legacy-blog.com',
				'https://modern-webstore.org'
			],
			"A single, longer, tricky input" => [
				<<<MARKUP
				<!-- wp:paragraph -->
				<p>
					<!-- Inline URLs are migrated -->
					🚀-science.com/science has the best scientific articles on the internet! We're also
					available via the punycode URL:
					
					<!-- No problem handling HTML-encoded punycode URLs with urlencoded characters in the path -->
					&#104;ttps://xn---&#115;&#99;ience-7f85g.com/%73%63ience/.
					
					<!-- Correctly ignores similar–but–different URLs -->
					This isn't migrated: https://🚀-science.comcast/science <br>
					Or this: super-🚀-science.com/science
				</p>
				<!-- /wp:paragraph -->

				<!-- Block attributes are migrated without any issue -->
				<!-- wp:image {"src": "https:\/\/\ud83d\ude80-\u0073\u0063ience.com/%73%63ience/wp-content/image.png"} -->
				<!-- As are URI HTML attributes -->
				<img src="&#104;ttps://xn---&#115;&#99;ience-7f85g.com/science/wp-content/image.png">
				<!-- /wp:image -->

				<!-- Classes are not migrated. -->
				<span class="https://🚀-science.com/science"></span>
				MARKUP,
				<<<MARKUP
				<!-- wp:paragraph -->
				<p>
					<!-- Inline URLs are migrated -->
					science.wordpress.org has the best scientific articles on the internet! We&apos;re also
					available via the punycode URL:
					
					<!-- No problem handling HTML-encoded punycode URLs with urlencoded characters in the path -->
					https://science.wordpress.org/.
					
					<!-- Correctly ignores similar–but–different URLs -->
					This isn't migrated: https://🚀-science.comcast/science <br>
					Or this: super-🚀-science.com/science
				</p>
				<!-- /wp:paragraph -->

				<!-- Block attributes are migrated without any issue -->
				<!-- wp:image {"src":"https:\/\/science.wordpress.org\/wp-content\/image.png"} -->
				<!-- As are URI HTML attributes -->
				<img src="https://science.wordpress.org/wp-content/image.png">
				<!-- /wp:image -->

				<!-- Classes are not migrated. -->
				<span class="https://🚀-science.com/science"></span>
				MARKUP,
				'https://🚀-science.com/science',
				'https://science.wordpress.org'
			]
		];
	}


	/**
	 *
	 * @dataProvider provider_diverse_domains
	 */
	public function test_wp_rewrite_url_migrates_domains_in_a_href(
		$domain_for_markup,
		$domain_for_lookup = null
	) {
		$current_site_url = $domain_for_lookup ? "https://$domain_for_lookup" : "https://$domain_for_markup";
		$new_site_url = "https://wordpress.org";
		$result = wp_rewrite_urls( array(
			'block_markup' => "<a href=\"$current_site_url/about-us/\"></a>",
			'url-mapping' => [
				$current_site_url => $new_site_url,
			],
		) );
		$this->assertEquals( "<a href=\"$new_site_url/about-us/\"></a>", $result, 'Failed to migrate the domain found in <a href=""></a>' );
	}

	static public function provider_diverse_domains() {
		return [
			"Regular ascii" => [ 'rocket-science.com' ],
			// "Prefixed with an emoji" => [ '🚀-science.com' ],
			// "Emoji-only – lookup by emoji notation" => [ '🚀.com', '🚀.com' ],
			// "Emoji-only – lookup by punycode notation" => [ '🚀.com', 'xn---science-7f85g.com' ],
			// "Punycode-encoded – lookup by punycode notation" => [ 'xn---science-7f85g.com', 'xn---science-7f85g.com' ],
			// "Punycode-encoded – lookup by emoji notation" => [ 'xn---science-7f85g.com', '🚀.com' ],
		];
	}
}
