<?php

use PHPUnit\Framework\TestCase;

class WPMarkdownToBlocksTests extends TestCase {

    public function test_metadata_extraction() {
        $markdown = <<<MD
---
post_title: "WordPress 6.8 was released"
post_date: "2024-12-16"
post_modified: "2024-12-16" 
post_author: "1"
post_author_name: "The WordPress Team"
post_author_url: "https://wordpress.org"
post_author_avatar: "https://wordpress.org/wp-content/uploads/2024/04/wordpress-logo-2024.png"
---

# WordPress 6.8 was released

Last week, WordPress 6.8 was released. This release includes a new default theme, a new block editor experience, and a new block library. It also includes a new block editor experience, and a new block library.
MD;
        $converter = new WP_Markdown_To_Blocks($markdown);
        $this->assertTrue($converter->convert());
        $metadata = $converter->get_all_metadata();
        $expected_metadata = [
            'post_title' => ['WordPress 6.8 was released'],
            'post_date' => ['2024-12-16'],
            'post_modified' => ['2024-12-16'],
            'post_author' => ['1'],
            'post_author_name' => ['The WordPress Team'],
            'post_author_url' => ['https://wordpress.org'],
            'post_author_avatar' => ['https://wordpress.org/wp-content/uploads/2024/04/wordpress-logo-2024.png'],
        ];
        $this->assertEquals($expected_metadata, $metadata);
    }

    /**
     * @dataProvider provider_test_conversion
     */
    public function test_markdown_to_blocks_conversion($markdown, $expected) {
        $converter = new WP_Markdown_To_Blocks($markdown);
        $converter->convert();
        $blocks = $converter->get_block_markup();

        $this->assertEquals($this->normalize_markup($expected), $this->normalize_markup($blocks));
    }

    private function normalize_markup($markup) {
        $processor = WP_HTML_Processor::create_fragment($markup);
        $serialized = $processor->serialize();
        $serialized = trim(
            str_replace(
                [
                    // Even more naively, remove all the newlines.
                    "\n"
                ],
                '',
                $serialized
            )
        );
        return $serialized;
    }

    public function provider_test_conversion() {
        return [
            'A simple paragraph' => [
                'markdown' => 'A simple paragraph',
                'expected' => "<!-- wp:paragraph --><p>A simple paragraph</p><!-- /wp:paragraph -->"
            ],
            'A simple list' => [
                'markdown' => "- Item 1\n- Item 2",
                'expected' => <<<HTML
<!-- wp:list {"ordered":false} --><ul class="wp-block-list"><!-- wp:list-item --><li>Item 1</li><!-- /wp:list-item --><!-- wp:list-item --><li>Item 2</li><!-- /wp:list-item --></ul><!-- /wp:list -->
HTML
            ],
            'A nested list' => [
                'markdown' => "- Item 1\n  - Item 1.1\n  - Item 1.2\n- Item 2",
                'expected' => <<<HTML
<!-- wp:list {"ordered":false} --><ul class="wp-block-list"><!-- wp:list-item --><li>Item 1<!-- wp:list {"ordered":false} --><ul class="wp-block-list"><!-- wp:list-item --><li>Item 1.1</li><!-- /wp:list-item --><!-- wp:list-item --><li>Item 1.2</li><!-- /wp:list-item --></ul><!-- /wp:list --></li><!-- /wp:list-item --><!-- wp:list-item --><li>Item 2</li><!-- /wp:list-item --></ul><!-- /wp:list -->
HTML
            ],
            'An image' => [
                'markdown' => '![An image](https://w.org/logo.png)',
                'expected' => "<!-- wp:paragraph --><p><img alt=\"An image\" src=\"https://w.org/logo.png\"></p><!-- /wp:paragraph -->"
            ],
            'A heading' => [
                'markdown' => '#### A simple heading',
                'expected' => "<!-- wp:heading {\"level\":4} --><h4>A simple heading</h4><!-- /wp:heading -->"
            ],
            'A link inside a paragraph' => [
                'markdown' => 'A simple paragraph with a [link](https://wordpress.org)',
                'expected' => "<!-- wp:paragraph --><p>A simple paragraph with a <a href=\"https://wordpress.org\">link</a></p><!-- /wp:paragraph -->"
            ],
            'Formatted text' => [
                'markdown' => '**Bold** and *Italic*',
                'expected' => "<!-- wp:paragraph --><p><b>Bold</b> and <em>Italic</em></p><!-- /wp:paragraph -->"
            ],
            'A blockquote' => [
                'markdown' => '> A simple blockquote',
                'expected' => "<!-- wp:quote --><blockquote class=\"wp-block-quote\"><!-- wp:paragraph --><p>A simple blockquote</p><!-- /wp:paragraph --></blockquote><!-- /wp:quote -->"
            ],
            'A table' => [
                'markdown' => <<<MD
| Header 1 | Header 2 |
|----------|----------|
| Cell 1   | Cell 2   |
| Cell 3   | Cell 4   |
MD,
                'expected' => <<<HTML
<!-- wp:table --><figure class="wp-block-table"><table class="has-fixed-layout"><thead><tr><th>Header 1</th><th>Header 2</th></tr></thead><tbody><tr><td>Cell 1</td><td>Cell 2</td></tr><tr><td>Cell 3</td><td>Cell 4</td></tr></tbody></table></figure><!-- /wp:table -->
HTML
            ],
        ];
    }

    public function test_markdown_to_blocks_excerpt() {
        $input = file_get_contents(__DIR__ . '/fixtures/markdown-to-blocks/excerpt.input.md');
        $converter = new WP_Markdown_To_Blocks($input);
        $converter->convert();
        $blocks = $converter->get_block_markup();

        $output_file = __DIR__ . '/fixtures/markdown-to-blocks/excerpt.output.html';
        if (getenv('UPDATE_FIXTURES')) {
            file_put_contents($output_file, $blocks);
        }

        $this->assertEquals(file_get_contents($output_file), $blocks);
    }

    public function test_frontmatter_extraction() {
        $markdown = <<<MD
---
title: "Brian Chesky – Founder Mode & The Art of Hiring"
---

# Brian Chesky – Founder Mode & The Art of Hiring

Here are the key insights...
MD;
        $converter = new WP_Markdown_To_Blocks($markdown);
        $converter->convert();
        $metadata = $converter->get_all_metadata();
        $expected_metadata = [
            'title' => ['Brian Chesky – Founder Mode & The Art of Hiring']
        ];
        $this->assertEquals($expected_metadata, $metadata);
    }
}
