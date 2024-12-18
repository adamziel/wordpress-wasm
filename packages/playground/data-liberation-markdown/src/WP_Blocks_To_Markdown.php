<?php

/**
 * Experimental Blocks to Markdown converter.
 */
class WP_Blocks_To_Markdown {
	private $blocks;
	private $markdown = '';
	private $metadata = array();
    private $context_breadcrumbs = array();

	public function __construct($block_markup, $metadata = array(), $context_breadcrumbs = array()) {
		$this->blocks = WP_Block_Markup_Processor::create_fragment($block_markup);
		$this->metadata = $metadata;
        $this->context_breadcrumbs = $context_breadcrumbs;
	}

	public function convert() {
		$this->blocks_to_markdown();
		return true;
	}

	public function get_markdown() {
		return $this->markdown;
	}

	private function blocks_to_markdown() {
        if($this->metadata) {
            $this->markdown .= "---\n";
            foreach($this->metadata as $key => $value) {
                // @TODO: Apply correct YAML value escaping
                $value = json_encode($value);
                $this->markdown .= "$key: $value\n";
            }
            $this->markdown .= "---\n\n";
        }

        while($this->blocks->next_token()) {
            switch($this->blocks->get_token_type()) {
                case '#block-comment':
                    $this->handle_block_comment();
                    break;
                case '#tag':
                    $this->handle_tag();
                    break;
                case '#text':
                    $this->markdown .= ltrim(preg_replace('/ +/', ' ', $this->blocks->get_modifiable_text()));
                    break;
            }
        }
	}

    private function handle_block_comment() {
        if ( $this->blocks->is_block_closer() ) {
            return;
        }
        switch($this->blocks->get_block_name()) {
            case 'wp:quote':
                $markdown = $this->skip_and_convert_inner_html();
                $lines = explode("\n", $markdown);
                foreach($lines as $line) {
                    $this->markdown .= "> $line\n";
                }
                $this->markdown .= ">\n";
                break;
            case 'wp:list':
                $markdown = $this->skip_and_convert_inner_html();
                $lines = explode("\n", $markdown);
                foreach($lines as $line) {
                    if($line) {
                        $this->markdown .= "* $line\n";
                    }
                }
                break;
            case 'wp:list-item':
                $this->markdown .= $this->skip_and_convert_inner_html() . "\n";
                break;
            case 'wp:code':
                $code = $this->skip_and_convert_inner_html();
                $language = $this->blocks->get_block_attribute('language') ?? '';
                $fence = str_repeat('`', max(3, $this->longest_sequence_of($code, '`') + 1));
                $this->markdown .= "$fence$language\n$code\n$fence\n\n";
                break;
            case 'wp:image':
                $alt = $this->blocks->get_block_attribute('alt') ?? '';
                $url = $this->blocks->get_block_attribute('url');
                $this->markdown .= "![$alt]($url)\n\n";
                break;
            case 'wp:heading':
                $level = $this->blocks->get_block_attribute('level') ?? 1;
                $content = $this->skip_and_convert_inner_html();
                $this->markdown .= str_repeat('#', $level) . ' ' . $content . "\n\n";
                break;
            case 'wp:paragraph':
                $this->markdown .= $this->skip_and_convert_inner_html() . "\n\n";
                break;
            case 'wp:separator':
                $this->markdown .= "\n---\n\n";
                break;
            default:
                $code = '';
                $code .= '<!-- ' . $this->blocks->get_modifiable_text() . ' -->';
                $code .= $this->skip_and_convert_inner_html();
                $code .= '<!-- ' . $this->blocks->get_modifiable_text() . ' -->';
                $language = 'block';
                $fence = str_repeat('`', max(3, $this->longest_sequence_of($code, '`') + 1));
                $this->markdown .= "$fence$language\n$code\n$fence\n\n";
                break;
        }
    }

    private function handle_tag() {
        $prefix = $this->blocks->is_tag_closer() ? '-' : '+';
        $event = $prefix . $this->blocks->get_tag();
        switch($event) {
            case '+B':
            case '-B':
            case '+STRONG':
            case '-STRONG':
                $this->markdown .= '**';
                break;
            case '+I':
            case '-I':
            case '+EM':
            case '-EM': 
                $this->markdown .= '*';
                break;
            case '+U':
            case '-U':
                $this->markdown .= '_';
                break;
            case '+CODE':
            case '-CODE':
                if(!in_array('wp:code', $this->get_block_breadcrumbs(), true)) {
                    $this->markdown .= '`';
                }
                break;
            case '+A':
                $href = $this->blocks->get_attribute('href');
                $this->markdown .= '[';
                break;
            case '-A':
                $href = $this->blocks->get_attribute('href');
                $this->markdown .= "]($href)";
                break;
            case '+BR':
                $this->markdown .= "\n";
                break;
            case '+IMG':
                $alt = $this->blocks->get_attribute('alt') ?? '';
                $url = $this->blocks->get_attribute('src');
                $this->markdown .= "![$alt]($url)\n\n";
                break;
        }
    }

    private function skip_and_convert_inner_html() {
        $html = $this->blocks->skip_and_get_block_inner_html();
        $converter = new WP_Blocks_To_Markdown($html, [], $this->get_block_breadcrumbs());
        $converter->convert();
        return $converter->get_markdown();
    }

	private function longest_sequence_of($input, $substring) {
        $at = 0;
        $sequence_length = 0;
        while($at < strlen($input)) {
            $at += strcspn($input, $substring, $at);
            $current_sequence_length = strspn($input, $substring, $at);
            if($current_sequence_length > $sequence_length) {
                $sequence_length = $current_sequence_length;
            }
            $at += $current_sequence_length;
        }
		return $sequence_length;
	}

    private function get_block_breadcrumbs() {
        return array_merge($this->context_breadcrumbs, $this->blocks->get_block_breadcrumbs());
    }

}
