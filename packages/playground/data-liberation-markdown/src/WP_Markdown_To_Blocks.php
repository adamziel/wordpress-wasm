<?php
/**
 * @TODO
 * * Transform images to image blocks, not inline <img> tags. Otherwise their width
 *   exceeds that of the paragraph block they're in.
 * * Consider implementing a dedicated markdown parser – similarly how we have
 *   a small, dedicated, and fast XML, HTML, etc. parsers. It would solve for
 *   code complexity, bundle size, performance, PHP compatibility, etc.
 */

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

class WP_Markdown_To_Blocks implements WP_Block_Markup_Converter {
	const STATE_READY    = 'STATE_READY';
	const STATE_COMPLETE = 'STATE_COMPLETE';

	private $state = self::STATE_READY;
	private $root_block;
	private $block_stack   = array();
	private $current_block = null;

	private $frontmatter = array();
	private $markdown;
	private $parsed_blocks = array();
	private $block_markup  = '';

	public function __construct( $markdown ) {
		$this->markdown = $markdown;
	}

	public function convert() {
		if ( self::STATE_READY !== $this->state ) {
			return false;
		}
		$this->convert_markdown_to_blocks();
		$this->block_markup = WP_Import_Utils::convert_blocks_to_markup( $this->parsed_blocks );
		return true;
	}

	public function get_all_metadata() {
		return $this->frontmatter;
	}

	public function get_first_meta_value( $key ) {
		if ( ! array_key_exists( $key, $this->frontmatter ) ) {
			return null;
		}
		return $this->frontmatter[ $key ][0];
	}

	public function get_block_markup() {
		return $this->block_markup;
	}

	private function convert_markdown_to_blocks() {
		$this->root_block    = $this->create_block( 'post-content' );
		$this->block_stack[] = $this->root_block;
		$this->current_block = $this->root_block;

		$environment = new Environment( array() );
		$environment->addExtension( new CommonMarkCoreExtension() );
		$environment->addExtension( new GithubFlavoredMarkdownExtension() );
		$environment->addExtension(
			new \Webuni\FrontMatter\Markdown\FrontMatterLeagueCommonMarkExtension(
				new \Webuni\FrontMatter\FrontMatter()
			)
		);

		$parser = new MarkdownParser( $environment );

		$document          = $parser->parse( $this->markdown );
		$this->frontmatter = array();
		foreach ( $document->data as $key => $value ) {
			// Use an array as a value to comply with the WP_Block_Markup_Converter interface.
			$this->frontmatter[ $key ] = array( $value );
		}

		$walker = $document->walker();
		while ( true ) {
			$event = $walker->next();
			if ( ! $event ) {
				break;
			}
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
						if ( method_exists( $node, 'getInfo' ) && $node->getInfo() ) {
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
						if ( $this->current_block->block_name === 'list-item' ) {
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
						if ( $node->getUrl() ) {
							$html->set_attribute( 'src', $node->getUrl() );
						}
						if ( $node->getTitle() ) {
							$html->set_attribute( 'title', $node->getTitle() );
						}
						$this->append_content( $html->get_updated_html() );
						break;

					case ExtensionInline\Link::class:
						$html = new WP_HTML_Tag_Processor( '<a>' );
						$html->next_tag();
						if ( $node->getUrl() ) {
							$html->set_attribute( 'href', $node->getUrl() );
						}
						if ( $node->getTitle() ) {
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
						foreach ( $table_section->inner_blocks as $row ) {
							$parsed_row = array();
							foreach ( $row->inner_blocks as $cell ) {
								$parsed_row[] = array(
									'tag' => $tag,
									'content' => $cell->attrs['content'] ?? '',
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
						$table->inner_blocks = array();
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
						if ( $this->current_block->block_name === 'list-item' ) {
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
		$this->parsed_blocks = $this->root_block->inner_blocks;
	}

	private function append_content( $content ) {
		if ( ! isset( $this->current_block->attrs['content'] ) ) {
			$this->current_block->attrs['content'] = '';
		}
		$this->current_block->attrs['content'] .= $content;
	}

	private function push_block( $name, $attributes = array(), $inner_blocks = array() ) {
		$block                               = $this->create_block( $name, $attributes, $inner_blocks );
		$this->current_block->inner_blocks[] = $block;
		array_push( $this->block_stack, $block );
		$this->current_block = $block;
	}

	private function create_block( $name, $attributes = array(), $inner_blocks = array() ) {
		return new WP_Block_Object(
			$name,
			$attributes,
			$inner_blocks
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
