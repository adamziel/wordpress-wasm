<?php

use PHPUnit\Framework\TestCase;

class WPBlocksToMarkdownTests extends TestCase {

    public function test_markdown_ast_conversion() {
        $blocks = <<<HTML
<!-- wp:heading {"level":1} -->
<h1>WordPress 6.8 was released</h1>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Last week, WordPress 6.8 was released. This release includes a new default theme, a new block editor experience, and a new block library. It also includes a new block editor experience, and a new block library.</p>
<!-- /wp:paragraph -->

<!-- wp:table -->
<figure class="wp-block-table"><table><thead><tr><th>Feature</th><th>Status</th></tr></thead><tbody><tr><td>Block Editor</td><td>Released</td></tr><tr><td>New Theme</td><td>Released</td></tr></tbody></table></figure>
<!-- /wp:table -->

<!-- wp:list -->
<ul>
    <!-- wp:list-item -->
    <li>Major Features
        <!-- wp:list -->
        <ul>
            <!-- wp:list-item -->
            <li>Block Editor Updates
                <!-- wp:list -->
                <ul>
                    <!-- wp:list-item -->
                    <li>New <code>block patterns</code> added</li>
                    <!-- /wp:list-item -->
                    <!-- wp:list-item -->
                    <li>
                        Improved performance

                        <!-- wp:list -->
                        <ul>
                            <!-- wp:list-item -->
                            <li>New <code>block patterns</code> added</li>
                            <!-- /wp:list-item -->
                            <!-- wp:list-item -->
                            <li>Improved performance</li>
                            <!-- /wp:list-item -->
                        </ul>
                        <!-- /wp:list -->
                    </li>
                    <!-- /wp:list-item -->
                </ul>
                <!-- /wp:list -->
            </li>
            <!-- /wp:list-item -->
        </ul>
        <!-- /wp:list -->
    </li>
    <!-- /wp:list-item -->
</ul>
<!-- /wp:list -->

<!-- wp:code -->
<pre class="wp-block-code"><code>function example() {
    return "WordPress 6.8";
}</code></pre>
<!-- /wp:code -->

<!-- wp:paragraph -->
<p>The <b>most significant</b> update includes <em>improved</em> block editing capabilities.</p>
<!-- /wp:paragraph -->

HTML;
        $expected = [
            [
              'type' => 'heading',
              'level' => 1,
              'content' => [
                [
                  'type' => 'text',
                  'content' => 'WordPress 6.8 was released',
                ],
              ],
            ],
            [
              'type' => 'paragraph',
              'content' => [
                [
                  'type' => 'text',
                  'content' => 'Last week, WordPress 6.8 was released. This release includes a new default theme, a new block editor experience, and a new block library. It also includes a new block editor experience, and a new block library.',
                ],
              ],
            ],
            [
              'type' => 'html_block',
              'content' => '
<figure class="wp-block-table"><table><thead><tr><th>Feature</th><th>Status</th></tr></thead><tbody><tr><td>Block Editor</td><td>Released</td></tr><tr><td>New Theme</td><td>Released</td></tr></tbody></table></figure>
',
            ],
            [
              'type' => 'list',
              'content' => [
                [
                  'type' => 'list_item',
                  'depth' => 0,
                  'content' => [
                    [
                      'type' => 'text',
                      'content' => 'Major Features',
                    ],
                    [
                      'type' => 'list',
                      'content' => [
                        [
                          'type' => 'list_item',
                          'depth' => 0,
                          'content' => [
                            [
                              'type' => 'text',
                              'content' => 'Block Editor Updates',
                            ],
                            [
                              'type' => 'list',
                              'content' => [
                                [
                                  'type' => 'list_item',
                                  'depth' => 0,
                                  'content' => [
                                    [
                                      'type' => 'text',
                                      'content' => 'New',
                                    ],
                                    [
                                      'type' => 'code',
                                    ],
                                    [
                                      'type' => 'text',
                                      'content' => 'block patterns',
                                    ],
                                    [
                                      'type' => 'code',
                                    ],
                                    [
                                      'type' => 'text',
                                      'content' => 'added',
                                    ],
                                  ],
                                ],
                                [
                                  'type' => 'list_item',
                                  'depth' => 0,
                                  'content' => [
                                    [
                                      'type' => 'text',
                                      'content' => 'Improved performance',
                                    ],
                                  ],
                                ],
                              ],
                            ],
                          ],
                        ],
                      ],
                    ],
                  ],
                ],
              ],
            ],
            [
              'type' => 'code_block',
              'language' => false,
              'content' => [
                [
                  'type' => 'text',
                  'content' => 'function example() {
 return "WordPress 6.8";
}',
                ],
              ],
            ],
            [
              'type' => 'paragraph',
              'content' => [
                [
                  'type' => 'text',
                  'content' => 'The',
                ],
                [
                  'type' => 'strong',
                ],
                [
                  'type' => 'text',
                  'content' => 'most significant',
                ],
                [
                  'type' => 'strong',
                ],
                [
                  'type' => 'text',
                  'content' => 'update includes',
                ],
                [
                  'type' => 'emphasis',
                ],
                [
                  'type' => 'text',
                  'content' => 'improved',
                ],
                [
                  'type' => 'emphasis',
                ],
                [
                  'type' => 'text',
                  'content' => 'block editing capabilities.',
                ],
              ],
            ],
        ];

        $converter = new WP_Blocks_To_Markdown($blocks);
        $converter->convert();
        $markdown_ast = $converter->get_markdown_ast();

        $this->assertEquals($expected, $markdown_ast);
    }

    public function test_metadata_preservation() {
        $metadata = [
            'post_title' => 'WordPress 6.8 was released',
            'post_date' => '2024-12-16',
            'post_modified' => '2024-12-16',
            'post_author' => '1',
            'post_author_name' => 'The WordPress Team',
            'post_author_url' => 'https://wordpress.org',
            'post_author_avatar' => 'https://wordpress.org/wp-content/uploads/2024/04/wordpress-logo-2024.png'
        ];

        $blocks = <<<HTML
<!-- wp:heading {"level":1} -->
<h1>WordPress 6.8 was released</h1>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Last week, WordPress 6.8 was released. This release includes a new default theme, a new block editor experience, and a new block library. It also includes a new block editor experience, and a new block library.</p>
<!-- /wp:paragraph -->
HTML;

        $converter = new WP_Blocks_To_Markdown($blocks, $metadata);
        $this->assertTrue($converter->convert());
        $markdown = $converter->get_result();

        $expected = <<<MD
---
post_title: "WordPress 6.8 was released"
post_date: "2024-12-16"
post_modified: "2024-12-16"
post_author: "1"
post_author_name: "The WordPress Team"
post_author_url: "https:\/\/wordpress.org"
post_author_avatar: "https:\/\/wordpress.org\/wp-content\/uploads\/2024\/04\/wordpress-logo-2024.png"
---

# WordPress 6.8 was released

Last week, WordPress 6.8 was released. This release includes a new default theme, a new block editor experience, and a new block library. It also includes a new block editor experience, and a new block library.
MD;
        $this->assertEquals(
            trim($expected, " \n"),
            trim($markdown, " \n")
        );
    }

    /**
     * @dataProvider provider_test_conversion
     */
    public function test_blocks_to_markdown_conversion($blocks, $expected) {
        $converter = new WP_Blocks_To_Markdown($blocks);
        $converter->convert();
        $markdown = $converter->get_result();

        $this->assertEquals($expected, $markdown);
    }

    public function provider_test_conversion() {
        return [
            'A simple paragraph' => [
                'blocks' => '<!-- wp:paragraph --><p>A simple paragraph</p><!-- /wp:paragraph -->',
                'expected' => "A simple paragraph\n\n"
            ],
            'A simple list' => [
                'blocks' => <<<HTML
<!-- wp:list {"ordered":false} -->
<ul class="wp-block-list">
<!-- wp:list-item --><li>Item 1</li><!-- /wp:list-item -->
<!-- wp:list-item --><li>Item 2</li><!-- /wp:list-item -->
</ul>
<!-- /wp:list -->
HTML,
                'expected' => "* Item 1\n* Item 2\n\n"
            ],
            'A nested list' => [
                'blocks' => <<<HTML
<!-- wp:list {"ordered":false} -->
<ul class="wp-block-list">
<!-- wp:list-item --><li>Item 1
<!-- wp:list {"ordered":false} -->
<ul class="wp-block-list">
<!-- wp:list-item --><li>Item 1.1</li><!-- /wp:list-item -->
<!-- wp:list-item --><li>Item 1.2</li><!-- /wp:list-item -->
</ul>
<!-- /wp:list -->
</li><!-- /wp:list-item -->
<!-- wp:list-item --><li>Item 2</li><!-- /wp:list-item -->
</ul>
<!-- /wp:list -->
HTML,
                'expected' => "* Item 1\n  * Item 1.1\n  * Item 1.2\n* Item 2\n\n"
            ],
            'An image' => [
                'blocks' => '<!-- wp:image {"url":"https://w.org/logo.png","alt":"An image"} -->',
                'expected' => "![An image](https://w.org/logo.png)\n\n"
            ],
            'A heading' => [
                'blocks' => '<!-- wp:heading {"level":4} --><h4>A simple heading</h4><!-- /wp:heading -->',
                'expected' => "#### A simple heading\n\n"
            ],
            'A link inside a paragraph' => [
                'blocks' => '<!-- wp:paragraph --><p>A simple paragraph with a <a href="https://wordpress.org">link</a></p><!-- /wp:paragraph -->',
                'expected' => "A simple paragraph with a [link](https://wordpress.org)\n\n"
            ],
            'Formatted text' => [
                'blocks' => '<!-- wp:paragraph --><p><b>Bold</b> and <em>Italic</em></p><!-- /wp:paragraph -->',
                'expected' => "**Bold** and *Italic*\n\n"
            ],
            'A blockquote' => [
                'blocks' => '<!-- wp:quote --><blockquote class="wp-block-quote"><!-- wp:paragraph --><p>A simple blockquote</p><!-- /wp:paragraph --></blockquote><!-- /wp:quote -->',
                'expected' => "> A simple blockquote\n> \n"
            ],
            'A table' => [
                'blocks' => <<<HTML
<!-- wp:table -->
<figure class="wp-block-table"><table class="has-fixed-layout">
<thead><tr><th>Header 1</th><th>Header 2</th></tr></thead>
<tbody><tr><td>Cell 1</td><td>Cell 2</td></tr><tr><td>Cell 3</td><td>Cell 4</td></tr></tbody>
</table></figure>
<!-- /wp:table -->
HTML,
                'expected' => <<<MD
| Header 1 | Header 2 |
|----------|----------|
| Cell 1   | Cell 2   |
| Cell 3   | Cell 4   |

MD
            ],
        ];
    }

    public function test_blocks_to_markdown_excerpt() {
        $input = file_get_contents(__DIR__ . '/fixtures/blocks-to-markdown/excerpt.input.html');
        $converter = new WP_Blocks_To_Markdown($input);
        $converter->convert();
        $markdown = $converter->get_result();

        $output_file = __DIR__ . '/fixtures/blocks-to-markdown/excerpt.output.md';
        if (getenv('UPDATE_FIXTURES')) {
            file_put_contents($output_file, $markdown);
        }

        $this->assertEquals(file_get_contents($output_file), $markdown);
    }

    public function test_metadata_preservation_with_frontmatter() {
        $blocks = <<<HTML
<!-- wp:heading {"level":1} -->
<h1>Brian Chesky – Founder Mode & The Art of Hiring</h1>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Here are the key insights...</p>
<!-- /wp:paragraph -->
HTML;

        $metadata = [
            'title' => 'Brian Chesky – Founder Mode & The Art of Hiring'
        ];

        $converter = new WP_Blocks_To_Markdown($blocks, $metadata);
        $converter->convert();
        $markdown = $converter->get_result();

        $expected = <<<MD
---
title: "Brian Chesky \u2013 Founder Mode & The Art of Hiring"
---

# Brian Chesky – Founder Mode & The Art of Hiring

Here are the key insights...


MD;
        $this->assertEquals($expected, $markdown);
    }
}
