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
	private $block_stack   = array();
	private $table_stack   = array();

	private $frontmatter = array();
	private $markdown;
	private $block_markup  = '';

	public function __construct( $markdown ) {
		$this->markdown = $markdown;
	}

	public function convert() {
		if ( self::STATE_READY !== $this->state ) {
			return false;
		}
		$this->convert_markdown_to_blocks();
		return true;
	}

	public function get_all_metadata() {
		return $this->frontmatter;
	}

	public function get_meta_value( $key ) {
		if ( ! array_key_exists( $key, $this->frontmatter ) ) {
			return null;
		}
		return $this->frontmatter[ $key ][0];
	}

	public function get_block_markup() {
		return $this->block_markup;
	}

	private function convert_markdown_to_blocks() {
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
		foreach ( $document->data->export() as $key => $value ) {
			if ( 'attributes' === $key && empty( $value ) ) {
				// The Frontmatter extension adds an 'attributes' key to the document data
				// even when there is no actual "attributes" key in the frontmatter.
				//
				// Let's skip it when the value is empty.
				continue;
			}
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
							)
						);
						$this->append_content( '<h' . $node->getLevel() . '>' );
						break;

					case ExtensionBlock\ListBlock::class:
						$attrs = array(
							'ordered' => $node->getListData()->type === 'ordered',
						);
						if ( $node->getListData()->start && $node->getListData()->start !== 1 ) {
							$attrs['start'] = $node->getListData()->start;
						}
						$this->push_block(
							'list',
							$attrs
						);

                        $tag = $attrs['ordered'] ? 'ol' : 'ul';
                        $this->append_content( '<' . $tag . ' class="wp-block-list">' );
						break;

					case ExtensionBlock\ListItem::class:
						$this->push_block( 'list-item' );
						$this->append_content( '<li>' );
						break;

					case Table::class:
						$this->push_block( 'table' );
						$this->append_content( '<figure class="wp-block-table"><table class="has-fixed-layout">' );
						break;

					case TableSection::class:
						$is_head = $node->isHead();
						array_push( $this->table_stack, $is_head ? 'head' : 'body' );
						$this->append_content( $is_head ? '<thead>' : '<tbody>' );
						break;

					case TableRow::class:
						$this->append_content( '<tr>' );
						break;

					case TableCell::class:
						/** @var TableCell $node */
						$is_header = $this->current_block() && $this->current_block()->block_name === 'table' && end( $this->table_stack ) === 'head';
						$tag = $is_header ? 'th' : 'td';
						$this->append_content( '<' . $tag . '>' );
						break;

					case ExtensionBlock\BlockQuote::class:
						$this->push_block( 'quote' );
						$this->append_content( '<blockquote class="wp-block-quote">' );
						break;

					case ExtensionBlock\FencedCode::class:
					case ExtensionBlock\IndentedCode::class:
						$attrs = array(
							'language' => null,
						);
						if ( method_exists( $node, 'getInfo' ) && $node->getInfo() ) {
							$attrs['language'] = preg_replace( '/[ \t\r\n\f].*/', '', $node->getInfo() );
						}
						$this->push_block( 'code', $attrs );
						$this->append_content( '<pre class="wp-block-code"><code>' . trim( str_replace( "\n", '<br>', htmlspecialchars( $node->getLiteral() ) ) ) . '</code></pre>' );
						break;

					case ExtensionBlock\HtmlBlock::class:
						$this->push_block( 'html' );
						$this->append_content( $node->getLiteral() );
						break;

					case ExtensionBlock\ThematicBreak::class:
						$this->push_block( 'separator' );
						break;

					case Block\Paragraph::class:
						$current_block = $this->current_block();
						if ( $current_block && $current_block->block_name === 'list-item' ) {
							break;
						}
						$this->push_block( 'paragraph' );
						$this->append_content( '<p>' );
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

						$children = $node->children();
						if ( count( $children ) > 0 && $children[0] instanceof Inline\Text && $children[0]->getLiteral() ) {
							$html->set_attribute( 'alt', $children[0]->getLiteral() );
							// Empty the text node so it will not be rendered twice: once in as an alt="",
							// and once as a new paragraph block.
							$children[0]->setLiteral( '' );
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
					case ExtensionBlock\BlockQuote::class:
						$this->append_content( '</blockquote>' );
						$this->pop_block();
						break;
					case ExtensionBlock\ListBlock::class:
                        if($node->getListData()->type === 'unordered') {
                            $this->append_content( '</ul>' );
                        } else {
                            $this->append_content( '</ol>' );
                        }
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
						$is_head = $node->isHead();
						array_pop( $this->table_stack );
						$this->append_content( $is_head ? '</thead>' : '</tbody>' );
						break;
					case TableRow::class:
						$this->append_content( '</tr>' );
						break;
					case TableCell::class:
						$is_header = $this->current_block() && $this->current_block()->block_name === 'table' && end( $this->table_stack ) === 'head';
						$tag = $is_header ? 'th' : 'td';
						$this->append_content( '</' . $tag . '>' );
						break;
					case Table::class:
						$this->append_content( '</table></figure>' );
						$this->pop_block();
						break;

					case Block\Paragraph::class:
						if ( $this->current_block()->block_name === 'list-item' ) {
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
	}

	private function append_content( $content ) {
		$this->block_markup .= $content;
	}

	private function push_block( $name, $attributes = array() ) {
		$block = new WP_Block_Object(
			$name,
			$attributes,
		);
		array_push( $this->block_stack, $block );
		$this->block_markup .= WP_Import_Utils::block_opener( $block->block_name, $block->attrs ) . "\n";
	}

	private function pop_block() {
		if ( ! empty( $this->block_stack ) ) {
			$popped              = array_pop( $this->block_stack );
			$this->block_markup .= WP_Import_Utils::block_closer( $popped->block_name ) . "\n";
			return $popped;
		}
	}

	private function current_block() {
		return end( $this->block_stack );
	}
}
