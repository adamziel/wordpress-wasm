<?php

/**
 * Converts WordPress blocks to Markdown.
 */
class WP_Blocks_To_Markdown {
    private $block_markup;
    private $state;
    private $parents = [];

    public function __construct($block_markup) {
        $this->block_markup = $block_markup;
        $this->state = array(
            'indent' => array(),
            'listStyle' => array()
        );
    }

    private $markdown;

    public function convert() {
        $this->markdown = $this->blocks_to_markdown(parse_blocks($this->block_markup));
    }

    public function get_result() {
        return $this->markdown;
    }

    private function blocks_to_markdown($blocks) {
        $output = '';
        foreach ($blocks as $block) {
            array_push($this->parents, $block['blockName']);
            $output .= $this->block_to_markdown($block);
            array_pop($this->parents);
        }
        return $output;
    }

    private function block_to_markdown($block) {
        $block_name = $block['blockName'];
        $attributes = $block['attrs'] ?? array();
        $inner_html = $block['innerHTML'] ?? '';
        $inner_blocks = $block['innerBlocks'] ?? array();

        switch ($block_name) {
            case 'core/paragraph':
                return $this->html_to_markdown($inner_html) . "\n\n";

            case 'core/quote':
                $content = $this->blocks_to_markdown($inner_blocks);
                $lines = explode("\n", $content);
                return implode("\n", array_map(function($line) {
                    return "> $line"; 
                }, $lines)) . "\n\n";

            case 'core/code':
                $code = $this->html_to_markdown($inner_html);
                $language = $attributes['language'] ?? '';
                $fence = str_repeat('`', max(3, $this->longest_sequence_of($code, '`') + 1));
                return "{$fence}{$language}\n{$code}\n{$fence}\n\n";

            case 'core/image':
                return "![" . ($attributes['alt'] ?? '') . "](" . ($attributes['url'] ?? '') . ")\n\n";

            case 'core/heading':
                $level = $attributes['level'] ?? 1;
                $content = $this->html_to_markdown($inner_html);
                return str_repeat('#', $level) . ' ' . $content . "\n\n";

            case 'core/list':
                array_push($this->state['listStyle'], array(
                    'style' => isset($attributes['ordered']) ? ($attributes['type'] ?? 'decimal') : '-',
                    'count' => $attributes['start'] ?? 1
                ));
                $list = $this->blocks_to_markdown($inner_blocks);
                array_pop($this->state['listStyle']);
                if($this->has_parent('core/list-item')){
                    return $list;
                }
                return $list . "\n";

            case 'core/list-item':
                if (empty($this->state['listStyle'])) {
                    return '';
                }

                $item = end($this->state['listStyle']);
                $bullet = $this->get_list_bullet($item);
                $bullet_indent = str_repeat(' ', strlen($bullet) + 1);

                $content = $this->html_to_markdown($inner_html);
                $content_parts = explode("\n", $content, 2);
                $content_parts = array_map('trim', $content_parts);
                $first_line = $content_parts[0];
                $rest_lines = $content_parts[1] ?? '';

                $item['count']++;

                if (empty($inner_html)) {
                    $output = implode('', $this->state['indent']) . "$bullet $first_line\n";
                    array_push($this->state['indent'], $bullet_indent);
                    if ($rest_lines) {
                        $output .= $this->indent($rest_lines, $bullet_indent);
                    }
                    array_pop($this->state['indent']);
                    return $output;
                }

                $markdown = $this->indent("$bullet $first_line\n");

                array_push($this->state['indent'], $bullet_indent);
                if($rest_lines){
                    $markdown .= $this->indent($rest_lines) . "\n";
                }
                $inner_blocks_markdown = $this->blocks_to_markdown(
                    $inner_blocks
                );
                if($inner_blocks_markdown){
                    $markdown .= $inner_blocks_markdown . "\n";
                }
                array_pop($this->state['indent']);

                $markdown = rtrim($markdown, "\n");
                if($this->has_parent('core/list-item')){
                    $markdown .= "\n";
                } else {
                    $markdown .= "\n\n";
                }

                return $markdown;

            case 'core/separator':
                return "\n---\n\n";

            default:
                return '';
        }
    }

    private function html_to_markdown($html, $parents = []) {
        $processor = WP_HTML_Processor::create_fragment($html);
        $markdown = '';
        
        while ($processor->next_token()) {
            if ($processor->get_token_type() === '#text') {
                $markdown .= $processor->get_modifiable_text();
                continue;
            } else if ($processor->get_token_type() !== '#tag') {
                continue;
            }
            
            $last_href = null;
            $tag_name = $processor->get_tag();
            $sign = $processor->is_tag_closer() ? '-' : (
                $processor->expects_closer() ? '+' : ''
            );
            $event = $sign . $tag_name;
            switch ($event) {
                case '+B':
                case '-B':
                case '+STRONG':
                case '-STRONG':
                    $markdown .= '**';
                    break;
                    
                case '+I':
                case '-I':
                case '+EM':
                case '-EM':
                    $markdown .= '*';
                    break;
                    
                case '+CODE':
                case '-CODE':
                    if(!$this->has_parent('core/code')){
                        $markdown .= '`';
                    }
                    break;
                    
                case '+A':
                    $last_href = $processor->get_attribute('href') ?? '';
                    $markdown .= '[';
                    break;

                case '-A':
                    $markdown .= "]($last_href)";
                    break;
                    
                case 'BR':
                    $markdown .= "\n";
                    break;
            }
        }
        
        $markdown = trim($markdown, "\n ");
        $markdown = preg_replace('/ +/', ' ', $markdown);
        $markdown = preg_replace('/\n+/', "\n", $markdown);
        return $markdown;
    }

    private function has_parent($parent) {
        return in_array($parent, $this->parents, true);
    }

    private function get_list_bullet($item) {
        if ($item['style'] === '-') {
            return '-';
        }
        return $item['count'] . '.';
    }

    private function indent($string) {
        if (empty($this->state['indent'])) {
            return $string;
        }

        $indent = implode('', $this->state['indent']);
        $lines = explode("\n", $string);
        return implode("\n", array_map(function($line) use ($indent) {
            return empty($line) ? $line : $indent . $line;
        }, $lines));
    }

    private function longest_sequence_of($input, $substring) {
        $longest = 0;
        $current = 0;
        $len = strlen($input);
        
        for ($i = 0; $i < $len; $i++) {
            if ($input[$i] === $substring) {
                $current++;
                $longest = max($longest, $current);
            } else {
                $current = 0;
            }
        }
        
        return $longest;
    }
}
