<?php

use PHPUnit\Framework\TestCase;

class WPBlockMarkupProcessorTests extends TestCase {

	/**
	 *
	 * @dataProvider provider_test_finds_block_openers
	 */
	public function test_finds_block_openers( $markup, $block_name, $block_attributes ) {
		$p = new WP_Block_Markup_Processor( $markup );
		$p->next_token();
		$this->assertEquals( '#block-comment', $p->get_token_type(), 'Failed to identify the block comment' );
		$this->assertEquals( $block_name, $p->get_block_name(), 'Failed to identify the block name' );
		$this->assertEquals( $block_attributes, $p->get_block_attributes(), 'Failed to identify the block attributes' );
	}

	static public function provider_test_finds_block_openers() {
		return [
			'Opener without attributes'                        => [ '<!-- wp:paragraph -->', 'wp:paragraph', [] ],
			'Opener without the trailing whitespace'           => [ '<!--wp:paragraph-->', 'wp:paragraph', [] ],
			'Opener with a lot of trailing whitespace'         => [ '<!--    wp:paragraph          -->', 'wp:paragraph', [] ],
			'Opener with attributes'                           => [
				'<!-- wp:paragraph {"class": "wp-bold"} -->',
				'wp:paragraph',
				[ 'class' => 'wp-bold' ],
			],
			'Opener with empty attributes'                     => [ '<!-- wp:paragraph {} -->', 'wp:paragraph', [] ],
			'Opener with lots of whitespace around attributes' => [
				'<!-- wp:paragraph   {    "class":   "wp-bold"  }   -->',
				'wp:paragraph',
				[ 'class' => 'wp-bold' ],
			],
			'Opener with object and array attributes'          => [
				'<!-- wp:code { "meta": { "language": "php", "highlightedLines": [14, 22] }, "class": "dark" } -->',
				'wp:code',
				[ 'meta' => [ 'language' => 'php', 'highlightedLines' => [ 14, 22 ] ], 'class' => 'dark' ],
			],
		];
	}

	/**
	 *
	 * @dataProvider provider_test_finds_self_closing_blocks
	 */
	public function test_finds_self_closing_blocks( $markup, $block_name, $block_attributes ) {
		$p = new WP_Block_Markup_Processor( $markup );
		$p->next_token();
		$this->assertEquals( '#block-comment', $p->get_token_type(), 'Failed to identify the block comment' );
		$this->assertEquals( $block_name, $p->get_block_name(), 'Failed to identify the block name' );
		$this->assertEquals( $block_attributes, $p->get_block_attributes(), 'Failed to identify the block attributes' );
		$this->assertTrue( $p->is_self_closing_block(), 'Failed to identify the self-closing block status' );
	}

	static public function provider_test_finds_self_closing_blocks() {
		return [
			'Self-closing block without attributes' => [ 
				'<!-- wp:spacer /-->', 
				'wp:spacer', 
				[] 
			],
			'Self-closing block with attributes' => [ 
				'<!-- wp:spacer {"height":"20px"} /-->', 
				'wp:spacer', 
				[ 'height' => '20px' ] 
			],
		];
	}

	/**
	 *
	 * @dataProvider provider_test_finds_block_closers
	 */
	public function test_find_block_closers( $markup, $block_name ) {
		$p = new WP_Block_Markup_Processor( $markup );
		$p->next_token();
		$this->assertEquals( '#block-comment', $p->get_token_type(), 'Failed to identify the block comment' );
		$this->assertEquals( $block_name, $p->get_block_name(), 'Failed to identify the block name' );
		$this->assertTrue( $p->is_block_closer(), 'Failed to identify the block closer status' );
	}

	static public function provider_test_finds_block_closers() {
		return [
			'Closer without attributes'                => [ '<!-- /wp:paragraph -->', 'wp:paragraph' ],
			'Closer without the trailing whitespace'   => [ '<!--/wp:paragraph-->', 'wp:paragraph' ],
			'Closer with a lot of trailing whitespace' => [ '<!--    /wp:paragraph          -->', 'wp:paragraph' ],
		];
	}

	/**
	 *
	 * @dataProvider provider_test_treat_invalid_block_openers_as_comments
	 */
	public function test_treat_invalid_block_openers_as_comments( $markup ) {
		$p = new WP_Block_Markup_Processor( $markup );
		$p->next_token();
		$this->assertEquals( '#comment', $p->get_token_type(), 'Failed to identify the comment' );
		$this->assertFalse( $p->get_block_name(), 'The block name wasn\'t false' );
		$this->assertFalse( $p->get_block_attributes(), 'The block attributes weren\'t false' );
	}

	static public function provider_test_treat_invalid_block_openers_as_comments() {
		return [
			'Opener with a line break before whitespace' => [ "<!-- \nwp:paragraph -->", ],
			'Block name including !'                     => [ '<!-- wp:pa!ragraph -->', ],
			'Block name including a whitespace'          => [ '<!-- wp: paragraph -->', ],
			'No namespace in the block name'             => [ '<!-- paragraph -->', ],
			'Non-object attributes'                      => [ '<!-- wp:paragraph "attrs" -->', ],
			'Invalid JSON as attributes – Double }} '    => [ '<!-- wp:paragraph {"class":"wp-block"}} -->', ],
		];
	}

	/**
	 *
	 * @dataProvider provider_test_treat_invalid_block_closers_as_comments
	 */
	public function test_treat_invalid_block_closers_as_comments( $markup ) {
		$p = new WP_Block_Markup_Processor( $markup );
		$p->next_token();
		$this->assertEquals( '#comment', $p->get_token_type(), 'Failed to identify the comment' );
		$this->assertFalse( $p->get_block_name(), 'The block name wasn\'t false' );
		$this->assertFalse( $p->get_block_attributes(), 'The block attributes weren\'t false' );
	}

	static public function provider_test_treat_invalid_block_closers_as_comments() {
		return [
			'Closer with a line break before whitespace'         => [ "<!-- \n/wp:paragraph -->", ],
			'Closer with attributes'                             => [ '<!-- /wp:paragraph {"class": "block"} -->', ],
			'Closer with solidus at the end (before whitespace)' => [ '<!-- wp:paragraph/ -->', ],
		];
	}

	/**
	 * @dataProvider provider_test_set_modifiable_text
	 */
	public function test_set_modifiable_text( $markup, $new_text, $new_markup, $which_token = 1 ) {
		$p = new WP_Block_Markup_Processor( $markup );
		for ( $i = 0; $i < $which_token; $i ++ ) {
			$p->next_token();
		}
		$this->assertTrue( $p->set_modifiable_text( $new_text ), 'Failed to set the modifiable text.' );
		$this->assertEquals( $new_markup, $p->get_updated_html(), 'Failed to set the modifiable text.' );
	}

	static public function provider_test_set_modifiable_text() {
		return [
			'Changing the text of a block comment'      => [
				'<!-- wp:paragraph -->',
				' wp:paragraph {"class": "wp-bold"} ',
				'<!-- wp:paragraph {"class": "wp-bold"} -->',
			],
			'Changing the text of a text node'          => [
				'Hello, there',
				'I am a new text',
				'I am a new text',
			],
			'Changing the text of a text node in a tag' => [
				'<p>Hello, there</p>',
				'I am a new text',
				'<p>I am a new text</p>',
				2,
			],
			'Escapes the text in a text node' => [
				'<p>Hello, there</p>',
				'The <div> tag is my favorite one',
				'<p>The &lt;div&gt; tag is my favorite one</p>',
				2,
			],
		];
	}

	/**
	 * @dataProvider provider_test_set_modifiable_text_invalid_nodes
	 */
	public function test_set_modifiable_text_refuses_to_process_unsupported_nodes( $markup ) {
		$p = new WP_Block_Markup_Processor( $markup );
		$p->next_token();
		$this->assertFalse( $p->set_modifiable_text( 'New text' ), 'Set the modifiable text on an unsupported node.' );
	}


	static public function provider_test_set_modifiable_text_invalid_nodes() {
		return [
			'Tag' => ['<a href="">'],
			'DOCTYPE' => ['<!DOCTYPE html>'],
			'Funky comment' => ['</1I am a comment>'],
		];
	}

	public function test_set_modifiable_text_can_be_called_twice() {
		$p = new WP_Block_Markup_Processor( '<p>Hey there</p>' );
		$p->next_token();
		$p->next_token();
		$this->assertTrue( $p->set_modifiable_text( 'This is the new text, it is much longer' ), 'Failed to set the modifiable text.' );
		$this->assertEquals(
			'<p>This is the new text, it is much longer</p>',
			$p->get_updated_html(),
			'Failed to set the modifiable text.'
		);

		$this->assertTrue( $p->set_modifiable_text( 'Back to short text :)' ), 'Failed to set the modifiable text.' );
		$this->assertEquals(
			'<p>Back to short text :)</p>',
			$p->get_updated_html(),
			'Failed to set the modifiable text.'
		);
	}

	public function test_next_block_attribute_returns_false_after_the_last_attribute() {
		$p = new WP_Block_Markup_Processor(
			'<!-- wp:image {"class": "wp-bold", "id": "New York City" } -->'
		);
		$this->assertTrue( $p->next_token(), 'Failed to find the block opener' );
		$this->assertTrue( $p->next_block_attribute(), 'Failed to find the first block attribute' );
		$this->assertTrue( $p->next_block_attribute(), 'Failed to find the first block attribute' );
		$this->assertFalse( $p->next_block_attribute(), 'Returned true even though there was no next attribute' );
	}

	public function test_next_block_attribute_finds_the_first_attribute() {
		$p = new WP_Block_Markup_Processor(
			'<!-- wp:image {"class": "wp-bold", "id": "New York City" } -->'
		);
		$this->assertTrue( $p->next_token(), 'Failed to find the block opener' );
		$this->assertTrue( $p->next_block_attribute(), 'Failed to find the first block attribute' );

		$this->assertEquals( 'class', $p->get_block_attribute_key(), 'Failed to find the block attribute name' );
		$this->assertEquals( 'wp-bold', $p->get_block_attribute_value(), 'Failed to find the block attribute value' );
	}

	public function test_next_block_attribute_finds_the_second_attribute() {
		$p = new WP_Block_Markup_Processor(
			'<!-- wp:image {"class": "wp-bold", "id": "New York City" } -->'
		);
		$this->assertTrue( $p->next_token(), 'Failed to find the block opener' );
		$this->assertTrue( $p->next_block_attribute(), 'Failed to find the first block attribute' );
		$this->assertTrue( $p->next_block_attribute(), 'Failed to find the second block attribute' );

		$this->assertEquals( 'id', $p->get_block_attribute_key(), 'Failed to find the block attribute name' );
		$this->assertEquals( 'New York City', $p->get_block_attribute_value(), 'Failed to find the block attribute value' );
	}

	public function test_next_block_attribute_finds_nested_attributes() {
		$p = new WP_Block_Markup_Processor(
			'<!-- wp:image {"class": "wp-bold", "sources": { "lowres": "small.png", "hires": "large.png" } } -->'
		);
		$this->assertTrue( $p->next_token(), 'Failed to find the block opener' );
		$this->assertTrue( $p->next_block_attribute(), 'Failed to find the first block attribute' );
		$this->assertTrue( $p->next_block_attribute(), 'Failed to find the second block attribute' );
		$this->assertTrue( $p->next_block_attribute(), 'Failed to find the third block attribute' );

		$this->assertEquals( 'lowres', $p->get_block_attribute_key(), 'Failed to find the block attribute name' );
		$this->assertEquals( 'small.png', $p->get_block_attribute_value(), 'Failed to find the block attribute value' );

		$this->assertTrue( $p->next_block_attribute(), 'Failed to find the second block attribute' );

		$this->assertEquals( 'hires', $p->get_block_attribute_key(), 'Failed to find the block attribute name' );
		$this->assertEquals( 'large.png', $p->get_block_attribute_value(), 'Failed to find the block attribute value' );
	}

	public function test_next_block_attribute_loops_over_lists() {
		$p = new WP_Block_Markup_Processor(
			'<!-- wp:image {"class": "wp-bold", "sources": ["small.png", "large.png"] } -->'
		);
		$this->assertTrue( $p->next_token(), 'Failed to find the block opener' );
		$this->assertTrue( $p->next_block_attribute(), 'Failed to find the first block attribute' );
		$this->assertTrue( $p->next_block_attribute(), 'Failed to find the second block attribute' );
		$this->assertTrue( $p->next_block_attribute(), 'Failed to find the third block attribute' );

		$this->assertEquals( 0, $p->get_block_attribute_key(), 'Failed to find the block attribute name' );
		$this->assertEquals( 'small.png', $p->get_block_attribute_value(), 'Failed to find the block attribute value' );

		$this->assertTrue( $p->next_block_attribute(), 'Failed to find the second block attribute' );

		$this->assertEquals( 1, $p->get_block_attribute_key(), 'Failed to find the block attribute name' );
		$this->assertEquals( 'large.png', $p->get_block_attribute_value(), 'Failed to find the block attribute value' );
	}

	public function test_next_block_attribute_finds_top_level_attributes_after_nesting() {
		$p = new WP_Block_Markup_Processor(
			'<!-- wp:image {"sources": { "lowres": "small.png", "hires": "large.png" }, "class": "wp-bold" } -->'
		);
		$this->assertTrue( $p->next_token(), 'Failed to find the block opener' );
		$this->assertTrue( $p->next_block_attribute(), 'Failed to find the first block attribute' );
		$this->assertTrue( $p->next_block_attribute(), 'Failed to find the second block attribute' );
		$this->assertTrue( $p->next_block_attribute(), 'Failed to find the third block attribute' );
		$this->assertTrue( $p->next_block_attribute(), 'Failed to find the fourth block attribute' );

		$this->assertEquals( 'class', $p->get_block_attribute_key(), 'Failed to find the block attribute name' );
		$this->assertEquals( 'wp-bold', $p->get_block_attribute_value(), 'Failed to find the block attribute value' );
	}

	public function test_set_block_attribute_value_updates_a_simple_attribute() {
		$p = new WP_Block_Markup_Processor(
			'<!-- wp:image {"class": "wp-bold"} -->'
		);
		$this->assertTrue( $p->next_token(), 'Failed to find the block opener' );
		$this->assertTrue( $p->next_block_attribute(), 'Failed to find the first block attribute' );

		$p->set_block_attribute_value( 'wp-italics' );
		$this->assertEquals( '<!-- wp:image {"class":"wp-italics"} -->', $p->get_updated_html(),
			'Failed to update the block attribute value' );
	}

	public function test_set_block_attribute_value_updates_affects_get_block_attribute_value() {
		$p = new WP_Block_Markup_Processor(
			'<!-- wp:image {"class": "wp-bold"} -->'
		);
		$this->assertTrue( $p->next_token(), 'Failed to find the block opener' );
		$this->assertTrue( $p->next_block_attribute(), 'Failed to find the first block attribute' );

		$p->set_block_attribute_value( 'wp-italics' );
		$this->assertEquals( 'wp-italics', $p->get_block_attribute_value(), 'Failed to find the block attribute value' );
	}

	public function test_set_block_attribute_value_updates_a_nested_attribute() {
		$p = new WP_Block_Markup_Processor(
			'<!-- wp:image {"sources": { "lowres": "small.png", "hires": "large.png" } } -->'
		);
		$this->assertTrue( $p->next_token(), 'Failed to find the block opener' );
		$this->assertTrue( $p->next_block_attribute(), 'Failed to find the first block attribute' );
		$this->assertTrue( $p->next_block_attribute(), 'Failed to find the second block attribute' );
		$this->assertTrue( $p->next_block_attribute(), 'Failed to find the third block attribute' );

		$p->set_block_attribute_value( 'medium.png' );
		$this->assertEquals( 'medium.png', $p->get_block_attribute_value(), 'Failed to find the block attribute value' );
		$this->assertEquals( '<!-- wp:image {"sources":{"lowres":"small.png","hires":"medium.png"}} -->', $p->get_updated_html(),
			'Failed to update the block attribute value' );
	}

	public function test_set_block_attribute_value_updates_a_list_value() {
		$p = new WP_Block_Markup_Processor(
			'<!-- wp:image {"sources": ["small.png", "large.png"] } -->'
		);
		$this->assertTrue( $p->next_token(), 'Failed to find the block opener' );
		$this->assertTrue( $p->next_block_attribute(), 'Failed to find the first block attribute' );
		$this->assertTrue( $p->next_block_attribute(), 'Failed to find the second block attribute' );
		$this->assertTrue( $p->next_block_attribute(), 'Failed to find the third block attribute' );

		$p->set_block_attribute_value( 'medium.png' );
		$this->assertEquals( 'medium.png', $p->get_block_attribute_value(), 'Failed to find the block attribute value' );
		$this->assertEquals( '<!-- wp:image {"sources":["small.png","medium.png"]} -->', $p->get_updated_html(),
			'Failed to update the block attribute value' );
	}

	public function test_set_block_attribute_can_be_called_multiple_times() {
		$p = new WP_Block_Markup_Processor(
			'<!-- wp:image {"sources": { "lowres": "small.png", "hires": "large.png" } } -->'
		);
		$this->assertTrue( $p->next_token(), 'Failed to find the block opener' );
		$this->assertTrue( $p->next_block_attribute(), 'Failed to find the first block attribute' );
		$this->assertTrue( $p->next_block_attribute(), 'Failed to find the second block attribute' );
		$this->assertTrue( $p->next_block_attribute(), 'Failed to find the third block attribute' );

		$p->set_block_attribute_value( 'medium.png' );
		$p->set_block_attribute_value( 'oh-completely-different-image.png' );
		$this->assertEquals( 'oh-completely-different-image.png', $p->get_block_attribute_value(),
			'Failed to find the block attribute value' );
		$this->assertEquals(
			'<!-- wp:image {"sources":{"lowres":"small.png","hires":"oh-completely-different-image.png"}} -->',
			$p->get_updated_html(),
			'Failed to update the block attribute value'
		);
	}

	public function test_set_block_attribute_value_flushes_updates_on_next_token() {
		$p = new WP_Block_Markup_Processor(
			'<!-- wp:paragraph {"class": "wp-bold"} -->Hello, there'
		);
		$this->assertTrue( $p->next_token(), 'Failed to find the block opener' );
		$this->assertTrue( $p->next_block_attribute(), 'Failed to find the first block attribute' );
		$this->assertTrue( $p->set_block_attribute_value( 'wp-italics' ), 'Failed to update the block attribute value' );
		$this->assertTrue( $p->next_token(), 'Failed to find the text node' );
		$this->assertEquals(
			'<!-- wp:paragraph {"class":"wp-italics"} -->Hello, there',
			$p->get_updated_html(),
			'Failed to update the block attribute value'
		);
	}
}
