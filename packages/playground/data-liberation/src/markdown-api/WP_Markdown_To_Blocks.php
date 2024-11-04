<?php

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Parser\MarkdownParser;
use League\CommonMark\Extension\CommonMark\Node\Block as ExtensionBlock;
use League\CommonMark\Extension\CommonMark\Node\Inline as ExtensionInline;
use League\CommonMark\Node\Block;
use League\CommonMark\Node\Inline;
use League\CommonMark\Extension\Table\Table;
use League\CommonMark\Extension\Table\TableCell;
use League\CommonMark\Extension\Table\TableRow;
use League\CommonMark\Extension\Table\TableSection;


class WP_Markdown_To_Blocks {
	private $root_block;
	private $block_stack   = array();
	private $current_block = null;

	public static function convert( $markdown ) {
		$builder = new WP_Markdown_To_Blocks();
		$blocks  = $builder->markdown_to_blocks( $markdown );
		return $builder->convert_blocks_to_markup( $blocks );
	}

	private function convert_blocks_to_markup( $blocks ) {
		$markup = '';

		foreach ( $blocks as $block ) {
			// Start of block comment
			$comment = '<!-- -->';
			$p       = new WP_HTML_Tag_Processor( $comment );
			$p->next_token();
			$attrs   = $block->attrs;
			$content = $block->attrs['content'] ?? '';
			unset( $attrs['content'] );
			$encoded_attrs = json_encode( $attrs );
			if ( $encoded_attrs === '[]' ) {
				$encoded_attrs = '';
			}
			$p->set_modifiable_text( " wp:{$block->blockName} " . $encoded_attrs . ' ' );
			$open_comment = $p->get_updated_html();

			$markup .= $open_comment . "\n";
			$markup .= $content . "\n";
			$markup .= $this->convert_blocks_to_markup( $block->innerBlocks );

			// End of block comment
			$markup .= "<!-- /wp:{$block->blockName} -->\n";
		}

		return $markup;
	}

	private function markdown_to_blocks( $markdown_content ) {
		$this->root_block    = $this->create_block( 'post-content' );
		$this->block_stack[] = $this->root_block;
		$this->current_block = $this->root_block;

		$environment = new Environment( array() );
		$environment->addExtension( new CommonMarkCoreExtension() );
		$environment->addExtension( new GithubFlavoredMarkdownExtension() );

		$parser = new MarkdownParser( $environment );

		$document = $parser->parse( $markdown_content );

		$walker = $document->walker();
		while ( $event = $walker->next() ) {
			$node = $event->getNode();

			if ( $event->isEntering() ) {
				switch ( get_class( $node ) ) {
					case Block\Document::class:
						// Ignore
						break;

					case ExtensionBlock\Heading::class:
						$this->push_block(
							'heading',
							array(
								'level' => $node->getLevel(),
								'content' => '<h' . $node->getLevel() . '>',
							)
						);
						break;

					case ExtensionBlock\ListBlock::class:
						$this->push_block(
							'list',
							array(
								'ordered' => $node->getListData()->type === 'ordered',
								'content' => '<ul>',
							)
						);
						if ( $node->getListData()->start && $node->getListData()->start !== 1 ) {
							$this->current_block->attrs['start'] = $node->getListData()->start;
						}
						break;

					case ExtensionBlock\ListItem::class:
						$this->push_block(
							'list-item',
							array(
								'content' => '<li>',
							)
						);
						break;

					case Table::class:
						$this->push_block(
							'table',
							array(
								'head' => array(),
								'body' => array(),
								'foot' => array(),
							)
						);
						break;

					case TableSection::class:
						$this->push_block(
							'table-section',
							array(
								'type' => $node->isHead() ? 'head' : 'body',
							)
						);
						break;

					case TableRow::class:
						$this->push_block( 'table-row' );
						break;

					case TableCell::class:
						/** @var TableCell $node */
						$this->push_block( 'table-cell' );
						break;

					case ExtensionBlock\BlockQuote::class:
						$this->push_block( 'quote' );
						break;

					case ExtensionBlock\FencedCode::class:
					case ExtensionBlock\IndentedCode::class:
						$this->push_block(
							'code',
							array(
								'content' => '<pre class="wp-block-code"><code>' . trim( str_replace( "\n", '<br>', htmlspecialchars( $node->getLiteral() ) ) ) . '</code></pre>',
							)
						);
						if ( $node->getInfo() ) {
							$this->current_block->attrs['language'] = preg_replace( '/[ \t\r\n\f].*/', '', $node->getInfo() );
						}
						break;

					case ExtensionBlock\HtmlBlock::class:
						$this->push_block(
							'html',
							array(
								'content' => $node->getLiteral(),
							)
						);
						break;

					case ExtensionBlock\ThematicBreak::class:
						$this->push_block( 'separator' );
						break;

					case Block\Paragraph::class:
						if ( $this->current_block->blockName === 'list-item' ) {
							break;
						}
						$this->push_block(
							'paragraph',
							array(
								'content' => '<p>',
							)
						);
						break;

					case Inline\Newline::class:
						$this->append_content( "\n" );
						break;

					case Inline\Text::class:
						$this->append_content( $node->getLiteral() );
						break;

					case ExtensionInline\Code::class:
						$this->append_content( '<code>' . htmlspecialchars( $node->getLiteral() ) . '</code>' );
						break;

					case ExtensionInline\Strong::class:
						$this->append_content( '<b>' );
						break;

					case ExtensionInline\Emphasis::class:
						$this->append_content( '<em>' );
						break;

					case ExtensionInline\HtmlInline::class:
						$this->append_content( htmlspecialchars( $node->getLiteral() ) );
						break;

					case ExtensionInline\Image::class:
						$html = new WP_HTML_Tag_Processor( '<img>' );
						$html->next_tag();
						if($node->getUrl()) {
							$html->set_attribute( 'src', $node->getUrl() );
						}
						if($node->getTitle()) {
							$html->set_attribute( 'title', $node->getTitle() );
						}
						$this->append_content( $html->get_updated_html() );
						break;

					case ExtensionInline\Link::class:
						$html = new WP_HTML_Tag_Processor( '<a>' );
						$html->next_tag();
						if($node->getUrl()) {
							$html->set_attribute( 'href', $node->getUrl() );
						}
						if($node->getTitle()) {
							$html->set_attribute( 'title', $node->getTitle() );
						}
						$this->append_content( $html->get_updated_html() );
						break;

					default:
						error_log( 'Unhandled node type: ' . get_class( $node ) );
						return null;
				}
			} else {
				switch ( get_class( $node ) ) {
					case ExtensionBlock\ListBlock::class:
						$this->append_content( '</ul>' );
						$this->pop_block();
						break;
					case ExtensionBlock\ListItem::class:
						$this->append_content( '</li>' );
						$this->pop_block();
						break;
					case ExtensionBlock\Heading::class:
						$this->append_content( '</h' . $node->getLevel() . '>' );
						$this->pop_block();
						break;
					case ExtensionInline\Strong::class:
						$this->append_content( '</b>' );
						break;
					case ExtensionInline\Emphasis::class:
						$this->append_content( '</em>' );
						break;
					case ExtensionInline\Link::class:
						$this->append_content( '</a>' );
						break;
					case TableSection::class:
						$table_section = $this->pop_block();
						$type          = $table_section->attrs['type'];
						$tag           = $type === 'head' ? 'th' : 'td';

						$parsed_rows = array();
						foreach ( $table_section->innerBlocks as $row ) {
							$parsed_row = array();
							foreach ( $row->innerBlocks as $cell ) {
								$parsed_row[] = array(
									'tag' => $tag,
									'content' => $cell->attrs['content'],
								);
							}
							$parsed_rows[] = $parsed_row;
						}

						$table = $this->current_block;
						if ( $type === 'head' ) {
							$table->attrs[ $type ] = $parsed_rows[0];
						} else {
							$table->attrs[ $type ] = $parsed_rows;
						}
						$table->innerBlocks = array();
						break;
					case Table::class:
						$table  = '<figure class="wp-block-table">';
						$table .= '<table class="has-fixed-layout">';
						$table .= '<thead><tr>';
						foreach ( $this->current_block->attrs['head'] as $cell ) {
							$table .= '<th>' . $cell['content'] . '</th>';
						}
						$table .= '</tr></thead><tbody>';
						foreach ( $this->current_block->attrs['body'] as $row ) {
							$table .= '<tr>';
							foreach ( $row as $cell ) {
								$table .= '<td>' . $cell['content'] . '</td>';
							}
							$table .= '</tr>';
						}
						$table                                .= '</tbody></table>';
						$table                                .= '</figure>';
						$this->current_block->attrs['content'] = $table;
						$this->pop_block();
						break;

					case Block\Paragraph::class:
						if ( $this->current_block->blockName === 'list-item' ) {
							break;
						}
						$this->append_content( '</p>' );
						$this->pop_block();
						break;

					case Inline\Text::class:
					case Inline\Newline::class:
					case Block\Document::class:
					case ExtensionInline\Code::class:
					case ExtensionInline\HtmlInline::class:
					case ExtensionInline\Image::class:
						// Ignore, don't pop any blocks.
						break;
					default:
						$this->pop_block();
						break;
				}
			}
		}
		return $this->root_block->innerBlocks;
	}

	private function append_content( $content ) {
		if ( ! isset( $this->current_block->attrs['content'] ) ) {
			$this->current_block->attrs['content'] = '';
		}
		$this->current_block->attrs['content'] .= $content;
	}

	private function push_block( $name, $attributes = array(), $innerBlocks = array() ) {
		$block                              = $this->create_block( $name, $attributes, $innerBlocks );
		$this->current_block->innerBlocks[] = $block;
		array_push( $this->block_stack, $block );
		$this->current_block = $block;
	}

	private function create_block( $name, $attributes = array(), $innerBlocks = array() ) {
		return new WP_Block_Object(
			$name,
			$attributes,
			$innerBlocks
		);
	}

	private function pop_block() {
		if ( ! empty( $this->block_stack ) ) {
			$popped              = array_pop( $this->block_stack );
			$this->current_block = end( $this->block_stack );
			return $popped;
		}
	}
}

class WP_Block_Object {
	public $blockName;
	public $attrs;
	public $innerBlocks;

	public function __construct( $blockName, $attrs = array(), $innerBlocks = array() ) {
		$this->blockName   = $blockName;
		$this->attrs       = $attrs;
		$this->innerBlocks = $innerBlocks;
	}

	public function as_array() {
		$attrs   = $this->attrs;
		$content = $attrs['content'] ?? null;
		unset( $attrs['content'] );
		return array(
			'blockName' => $this->blockName,
			'attrs' => $attrs,
			'innerBlocks' => array_map(
				function ( $block ) {
					return $block->as_array();
				},
				$this->innerBlocks
			),
			'innerHTML' => $this->attrs['content'] ?? null,
			'innerContent' => array(
				htmlspecialchars_decode( $this->attrs['content'] ?? '' ),
			),
		);
	}
}
