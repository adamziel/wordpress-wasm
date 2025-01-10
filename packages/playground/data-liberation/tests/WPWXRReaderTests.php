<?php

use PHPUnit\Framework\TestCase;

use WordPress\ByteReader\WP_File_Reader;

class WPWXRReaderTests extends TestCase {
    
    /**
     * @dataProvider preexisting_wxr_files_provider
     */
    public function test_does_not_crash_when_parsing_preexisting_wxr_files_as_string($path, $expected_entitys) {
        $wxr = WP_WXR_Entity_Reader::create();
        $wxr->append_bytes(file_get_contents($path));
        $wxr->input_finished();

        $found_entities = 0;
        while( $wxr->next_entity() ) {
            ++$found_entities;
        }

        $this->assertEquals($expected_entitys, $found_entities);
    }

    /**
     * @dataProvider preexisting_wxr_files_provider
     */
    public function test_does_not_crash_when_parsing_preexisting_wxr_files_as_stream($path, $expected_entitys) {
        $stream = fopen($path, 'r');
        $wxr = WP_WXR_Entity_Reader::create();
        $found_entities = 0;
        while(true) {
            $chunk = fread($stream, 100);
            if(false === $chunk || '' === $chunk) {
                break;
            }

            $wxr->append_bytes($chunk);
            while(true === $wxr->next_entity()) {
                ++$found_entities;
            }
        }

        $this->assertEquals($expected_entitys, $found_entities);
    }

    public function preexisting_wxr_files_provider() {
        return [
            [__DIR__ . '/wxr/a11y-unit-test-data.xml', 1043],
            [__DIR__ . '/wxr/crazy-cdata-escaped.xml', 5],
            [__DIR__ . '/wxr/crazy-cdata.xml', 5],
            [__DIR__ . '/wxr/invalid-version-tag.xml', 57],
            [__DIR__ . '/wxr/missing-version-tag.xml', 57],
            [__DIR__ . '/wxr/slashes.xml', 9],
            [__DIR__ . '/wxr/small-export.xml', 68],
            [__DIR__ . '/wxr/test-serialized-postmeta-no-cdata.xml', 5],
            [__DIR__ . '/wxr/test-serialized-postmeta-with-cdata.xml', 7],
            [__DIR__ . '/wxr/test-utw-post-meta-import.xml', 5],
            [__DIR__ . '/wxr/theme-unit-test-data.xml', 1146],
            [__DIR__ . '/wxr/valid-wxr-1.0.xml', 32],
            [__DIR__ . '/wxr/valid-wxr-1.1.xml', 11],
            [__DIR__ . '/wxr/woocommerce-demo-products.xml', 975],
            [__DIR__ . '/wxr/10MB.xml', 16442],
        ];
    }


    public function test_simple_wxr() {
        $importer = WP_WXR_Entity_Reader::create();
        $importer->append_bytes(file_get_contents(__DIR__ . '/fixtures/wxr-simple.xml'));
        $importer->input_finished();
        $this->assertTrue( $importer->next_entity() );
        $this->assertEquals(
            'site_option',
            $importer->get_entity()->get_type()
        );
        $this->assertEquals(
            [
                'option_name' => 'blogname',
                'option_value' => 'My WordPress Website',
            ],
            $importer->get_entity()->get_data()
        );

        $this->assertTrue( $importer->next_entity() );
        $this->assertEquals(
            'site_option',
            $importer->get_entity()->get_type()
        );
        $this->assertEquals(
            [
                'option_name' => 'siteurl',
                'option_value' => 'https://playground.internal/path',
            ],
            $importer->get_entity()->get_data()
        );

        $this->assertTrue( $importer->next_entity() );
        $this->assertEquals(
            [
                'option_name' => 'home',
                'option_value' => 'https://playground.internal/path',
            ],
            $importer->get_entity()->get_data()
        );

        $this->assertTrue( $importer->next_entity() );
        $this->assertEquals(
            [
                'user_login' => 'admin',
                'user_email' => 'admin@localhost.com',
                'display_name' => 'admin',
                'first_name' => '',
                'last_name' => '',
                'ID' => 1
            ],
            $importer->get_entity()->get_data()
        );
        
        $this->assertTrue( $importer->next_entity() );
        $this->assertEquals(
            [
                'post_title' => '"The Road Not Taken" by Robert Frost',
                'guid' => 'https://playground.internal/path/?p=1',
                'link' => 'https://playground.internal/path/?p=1',
                'post_date' => '2024-06-05 16:04:48',
                'post_published_at' => 'Wed, 05 Jun 2024 16:04:48 +0000',
                'post_author' => 'admin',
                'post_excerpt' => '',
                'post_content' => '<!-- wp:paragraph -->
<p>Two roads diverged in a yellow wood,<br>And sorry I could not travel both</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>
<a href="https://playground.internal/path/one">One</a> seemed great, but <a href="https://playground.internal/path-not-taken">the other</a> seemed great too.
There was also a <a href="https://w.org">third</a> option, but it was not as great.

playground.internal/path/one was the best choice.
https://playground.internal/path-not-taken was the second best choice.
</p>
<!-- /wp:paragraph -->',
                'post_id' => '10',
                'post_date_gmt' => '2024-06-05 16:04:48',
                'post_modified' => '2024-06-10 12:28:55',
                'post_modified_gmt' => '2024-06-10 12:28:55',
                'comment_status' => 'open',
                'ping_status' => 'open',
                'post_name' => 'hello-world',
                'post_status' => 'publish',
                'post_parent' => '0',
                'menu_order' => '0',
                'post_type' => 'post',
                'post_password' => '',
                'is_sticky' => '0',
                'terms' => [
                    [ 'taxonomy' => 'category', 'slug' => 'uncategorized', 'description' => 'Uncategorized' ],
                ],
            ],
            $importer->get_entity()->get_data()
        );

        $this->assertTrue( $importer->next_entity() );
        $this->assertEquals(
            [
                'meta_key' => '_pingme',
                'meta_value' => '1',
                'post_id' => '10',
            ],
            $importer->get_entity()->get_data()
        );

        $this->assertTrue( $importer->next_entity() );
        $this->assertEquals(
            [
                'meta_key' => '_encloseme',
                'meta_value' => '1',
                'post_id' => '10',
            ],
            $importer->get_entity()->get_data()
        );

        $this->assertFalse($importer->next_entity());
    }

    public function test_attachments() {
        $importer = WP_WXR_Entity_Reader::create();
        $importer->append_bytes(<<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <rss>
                <channel>
                    <item>
                        <title>vneck-tee-2.jpg</title>
                        <link>https://stylish-press.wordpress.org/?attachment_id=31</link>
                        <pubDate>Wed, 16 Jan 2019 13:01:56 +0000</pubDate>
                        <dc:creator>shopmanager</dc:creator>
                        <guid isPermaLink="false">https://raw.githubusercontent.com/wordpress/blueprints/stylish-press/blueprints/stylish-press/woo-product-images/vneck-tee-2.jpg</guid>
                        <description/>
                        <content:encoded><![CDATA[]]></content:encoded>
                        <excerpt:encoded><![CDATA[]]></excerpt:encoded>
                        <wp:post_id>31</wp:post_id>
                        <wp:post_date>2019-01-16 13:01:56</wp:post_date>
                        <wp:post_date_gmt>2019-01-16 13:01:56</wp:post_date_gmt>
                        <wp:comment_status>open</wp:comment_status>
                        <wp:ping_status>closed</wp:ping_status>
                        <wp:post_name>vneck-tee-2-jpg</wp:post_name>
                        <wp:status>inherit</wp:status>
                        <wp:post_parent>6</wp:post_parent>
                        <wp:menu_order>0</wp:menu_order>
                        <wp:post_type>attachment</wp:post_type>
                        <wp:post_password/>
                        <wp:is_sticky>0</wp:is_sticky>
                        <wp:attachment_url>https://raw.githubusercontent.com/wordpress/blueprints/stylish-press/blueprints/stylish-press/woo-product-images/vneck-tee-2.jpg</wp:attachment_url>
                        <wp:postmeta>
                            <wp:meta_key>_wc_attachment_source</wp:meta_key>
                            <wp:meta_value><![CDATA[https://raw.githubusercontent.com/wordpress/blueprints/stylish-press/blueprints/stylish-press/woo-product-images/vneck-tee-2.jpg]]></wp:meta_value>
                        </wp:postmeta>
                    </item>
                </channel>
            </rss>
            XML
        );
        $importer->input_finished();
        $this->assertTrue( $importer->next_entity() );
        $this->assertEquals(
            'post',
            $importer->get_entity()->get_type()
        );
        $this->assertEquals(
            [
                'post_title' => 'vneck-tee-2.jpg',
                'post_id' => '31',
                'guid' => 'https://raw.githubusercontent.com/wordpress/blueprints/stylish-press/blueprints/stylish-press/woo-product-images/vneck-tee-2.jpg',
                'link' => 'https://stylish-press.wordpress.org/?attachment_id=31',
                'post_published_at' => 'Wed, 16 Jan 2019 13:01:56 +0000',
                'post_date' => '2019-01-16 13:01:56',
                'post_date_gmt' => '2019-01-16 13:01:56',
                'post_author' => 'shopmanager',
                'post_excerpt' => '',
                'comment_status' => 'open',
                'ping_status' => 'closed',
                'post_name' => 'vneck-tee-2-jpg',
                'post_status' => 'inherit',
                'post_parent' => '6',
                'menu_order' => '0',
                'post_type' => 'attachment',
                'attachment_url' => 'https://raw.githubusercontent.com/wordpress/blueprints/stylish-press/blueprints/stylish-press/woo-product-images/vneck-tee-2.jpg',
                'post_content' => '',
                'is_sticky' => '0',
            ],
            $importer->get_entity()->get_data()
        );

        $this->assertTrue( $importer->next_entity() );
        $this->assertEquals(
            'post_meta',
            $importer->get_entity()->get_type()
        );
        $this->assertEquals(
            [
                'meta_key' => '_wc_attachment_source',
                'meta_value' => 'https://raw.githubusercontent.com/wordpress/blueprints/stylish-press/blueprints/stylish-press/woo-product-images/vneck-tee-2.jpg',
                'post_id' => '31',
            ],
            $importer->get_entity()->get_data()
        );
    }

    public function test_terms() {
        $importer = WP_WXR_Entity_Reader::create();
        $importer->append_bytes(<<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <rss>
                <channel>
                    <wp:term>
                        <wp:term_id><![CDATA[9]]></wp:term_id>
                        <wp:term_taxonomy><![CDATA[slider_category]]></wp:term_taxonomy>
                        <wp:term_slug><![CDATA[fullscreen_slider]]></wp:term_slug>
                        <wp:term_parent><![CDATA[]]></wp:term_parent>
                        <wp:term_name><![CDATA[fullscreen_slider]]></wp:term_name>
                    </wp:term>
                </channel>
            </rss>
            XML
        );
        $importer->input_finished();
        $this->assertTrue( $importer->next_entity() );
        $this->assertEquals(
            'term',
            $importer->get_entity()->get_type()
        );
        $this->assertEquals(
            [
                'term_id' => '9',
                'taxonomy' => 'slider_category',
                'slug' => 'fullscreen_slider',
                'parent' => '',
                'name' => 'fullscreen_slider',
            ],
            $importer->get_entity()->get_data()
        );
    }

    public function test_category() {
        $importer = WP_WXR_Entity_Reader::create();
        $importer->append_bytes(<<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <rss>
                <channel>
                    <wp:category>
                        <wp:category_nicename>uncategorized</wp:category_nicename>
                        <wp:category_parent></wp:category_parent>
                        <wp:cat_name><![CDATA[Uncategorized]]></wp:cat_name>
                    </wp:category>
                </channel>
            </rss>
            XML
    );
        $this->assertTrue( $importer->next_entity() );
        $this->assertEquals(
            'category',
            $importer->get_entity()->get_type()
        );
        $this->assertEquals(
            [
                'slug' => 'uncategorized',
                'parent' => '',
                'name' => 'Uncategorized',
                'taxonomy' => 'category',
            ],
            $importer->get_entity()->get_data()
        );
    }

    public function test_tag_string() {
        $importer = WP_WXR_Entity_Reader::create();
        $importer->append_bytes(<<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <rss>
                <channel>
                    <wp:tag>
                        <wp:term_id>651</wp:term_id>
                        <wp:tag_slug>articles</wp:tag_slug>
                        <wp:tag_name><![CDATA[Articles]]></wp:tag_name>
                        <wp:tag_description><![CDATA[Tags posts about Articles.]]></wp:tag_description>
                    </wp:tag>
                </channel>
            </rss>
            XML
        );
        $this->assertTrue( $importer->next_entity() );
        $this->assertEquals(
            'tag',
            $importer->get_entity()->get_type()
        );
        $this->assertEquals(
            [
                'term_id' => '651',
                'slug' => 'articles',
                'name' => 'Articles',
                'description' => 'Tags posts about Articles.',
                'taxonomy' => 'post_tag',
            ],
            $importer->get_entity()->get_data()
        );
    }

    public function test_tag_streaming() {
        $wxr = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <rss>
                <channel>
                    <wp:tag>
                        <wp:term_id>651</wp:term_id>
                        <wp:tag_slug>articles</wp:tag_slug>
                        <wp:tag_name><![CDATA[Articles]]> for <![CDATA[everyone]]></wp:tag_name>
                        <wp:tag_description><![CDATA[Tags posts about Articles.]]></wp:tag_description>
                    </wp:tag>
                </channel>
            </rss>
        XML;
        $chunks = str_split($wxr, 10);

        $wxr = WP_WXR_Entity_Reader::create();
        while(true) {
            if(true === $wxr->next_entity()) {
                break;
            }

            if($wxr->is_paused_at_incomplete_input()) {
                $chunk = array_shift($chunks);
                $wxr->append_bytes($chunk);
                continue;
            } else {
                break;
            }
        }

        $this->assertEquals(
            'tag',
            $wxr->get_entity()->get_type()
        );
        $this->assertEquals(
            [
                'term_id' => '651',
                'slug' => 'articles',
                'name' => 'Articles for everyone',
                'description' => 'Tags posts about Articles.',
                'taxonomy' => 'post_tag',
            ],
            $wxr->get_entity()->get_data()
        );
    }

    public function test_parse_comment() {
        $wxr = WP_WXR_Entity_Reader::create();
        $wxr->append_bytes(<<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <rss>
                <channel>
                    <item>
                        <title>My post!</title>
                        <wp:comment>
                            <wp:comment_id>167</wp:comment_id>
                            <wp:comment_author><![CDATA[Anon]]></wp:comment_author>
                            <wp:comment_author_email>anon@example.com</wp:comment_author_email>
                            <wp:comment_author_url/>
                            <wp:comment_author_IP>59.167.157.3</wp:comment_author_IP>
                            <wp:comment_date>2007-09-04 10:49:28</wp:comment_date>
                            <wp:comment_date_gmt>2007-09-04 00:49:28</wp:comment_date_gmt>
                            <wp:comment_content><![CDATA[Anonymous comment.]]></wp:comment_content>
                            <wp:comment_approved>1</wp:comment_approved>
                            <wp:comment_type/>
                            <wp:comment_parent>0</wp:comment_parent>
                            <wp:comment_user_id>0</wp:comment_user_id>
                            <wp:commentmeta>
                                <wp:meta_key>_wp_karma</wp:meta_key>
                                <wp:meta_value><![CDATA[1]]></wp:meta_value>
                            </wp:commentmeta>
                        </wp:comment>
                    </item>
                </channel>
            </rss>
            XML
        );
        $wxr->input_finished();
        $this->assertTrue( $wxr->next_entity() );
        $this->assertEquals(
            'post',
            $wxr->get_entity()->get_type()
        );
        $this->assertEquals(
            [
                'post_title' => 'My post!',
            ],
            $wxr->get_entity()->get_data()
        );

        $this->assertTrue( $wxr->next_entity() );
        $this->assertEquals(
            'comment',
            $wxr->get_entity()->get_type()
        );
        $this->assertEquals(
            [
                'comment_id' => '167',
                'comment_approved' => '1',
                'comment_author' => 'Anon',
                'comment_author_email' => 'anon@example.com',
                'comment_author_IP' => '59.167.157.3',
                'comment_user_id' => '0',
                'comment_date' => '2007-09-04 10:49:28',
                'comment_date_gmt' => '2007-09-04 00:49:28',
                'comment_content' => 'Anonymous comment.',
                'comment_parent' => '0',
                'post_id' => null
            ],
            $wxr->get_entity()->get_data()
        );

        $this->assertTrue( $wxr->next_entity() );
        $this->assertEquals(
            'comment_meta',
            $wxr->get_entity()->get_type()
        );
        $this->assertEquals(
            [
                'meta_key' => '_wp_karma',
                'meta_value' => '1',
                'comment_id' => '167',
            ],
            $wxr->get_entity()->get_data()
        );

        $this->assertFalse( $wxr->next_entity() );
    }

    public function test_retains_last_ids() {
        $wxr = WP_WXR_Entity_Reader::create();
        $wxr->append_bytes(<<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <rss>
                <channel>
                    <item>
                        <title>My post!</title>
                        <wp:post_id>10</wp:post_id>
                        <wp:post_parent>0</wp:post_parent>
                        <wp:comment>
                            <wp:comment_id>167</wp:comment_id>
                            <wp:comment_user_id>0</wp:comment_user_id>
                        </wp:comment>
                        <wp:comment>
                            <wp:comment_id>168</wp:comment_id>
                            <wp:comment_user_id>0</wp:comment_user_id>
                        </wp:comment>
                    </item>
                    <item>
                        <wp:post_id>11</wp:post_id>
                        <wp:post_parent>0</wp:post_parent>
                        <wp:comment>
                            <wp:comment_id>169</wp:comment_id>
                            <wp:comment_user_id>0</wp:comment_user_id>
                        </wp:comment>
                    </item>
                </channel>
            </rss>
            XML
        );
        $wxr->input_finished();
        $this->assertTrue( $wxr->next_entity() );
        $this->assertEquals('post', $wxr->get_entity()->get_type());
        $this->assertEquals( 10, $wxr->get_last_post_id() );

        $this->assertTrue( $wxr->next_entity() );
        $this->assertEquals('comment', $wxr->get_entity()->get_type());
        $this->assertEquals( 10, $wxr->get_last_post_id() );
        $this->assertEquals( 167, $wxr->get_last_comment_id() );

        $this->assertTrue( $wxr->next_entity() );
        $this->assertEquals('comment', $wxr->get_entity()->get_type());
        $this->assertEquals( 10, $wxr->get_last_post_id() );
        $this->assertEquals( 168, $wxr->get_last_comment_id() );

        $this->assertTrue( $wxr->next_entity() );
        $this->assertEquals('post', $wxr->get_entity()->get_type());
        $this->assertEquals( 11, $wxr->get_last_post_id() );

        $this->assertTrue( $wxr->next_entity() );
        $this->assertEquals('comment', $wxr->get_entity()->get_type());
        $this->assertEquals( 11, $wxr->get_last_post_id() );
        $this->assertEquals( 169, $wxr->get_last_comment_id() );
    }

    public function test_scan_entities_without_reentrancy() {
        $xml_path = __DIR__ . '/wxr/entities-options-and-posts.xml';
        $expected_entities = [
            "site_option",
            "site_option",
            "site_option",
            "user",
            "category",
            "category",
            "category",
            "user",
            "post",
            "post_meta",
            "post_meta"
        ];

		$wxr = WP_WXR_Entity_Reader::create(
            WP_File_Reader::create( $xml_path )
        );

        for($i = 0; $i < 11; $i++) {
            $this->assertTrue( $wxr->next_entity() );
            $this->assertEquals(
                $expected_entities[$i],
                $wxr->get_entity()->get_type()
            );
        }
        $this->assertFalse( $wxr->next_entity() );
    }

    public function test_scan_entities_with_reentrancy() {
        $xml_path = __DIR__ . '/wxr/entities-options-and-posts.xml';
        $expected_entities = [
            "site_option",
            "site_option",
            "site_option",
            "user",
            "category",
            "category",
            "category",
            "user",
            "post",
            "post_meta",
            "post_meta"
        ];

		$wxr = WP_WXR_Entity_Reader::create(
            WP_File_Reader::create( $xml_path )
        );

        for($i = 0; $i < 11; $i++) {
            $this->assertTrue( $wxr->next_entity() );
            $this->assertEquals(
                $expected_entities[$i],
                $wxr->get_entity()->get_type()
            );
            $cursor = $wxr->get_reentrancy_cursor();
            $wxr = WP_WXR_Entity_Reader::create(
                WP_File_Reader::create( $xml_path ),
                $cursor
            );
            $this->assertTrue( $wxr->next_entity() );
        }
        $this->assertFalse( $wxr->next_entity() );
    }

}
