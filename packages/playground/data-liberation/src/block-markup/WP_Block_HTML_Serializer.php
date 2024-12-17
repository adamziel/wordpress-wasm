<?php

/**
 * Serializes WordPress blocks and metadata to Block HTML.
 */
class WP_Block_HTML_Serializer {
    private $block_markup;
    private $metadata;
    private $result = '';

    public function __construct($block_markup, $metadata = []) {
        $this->block_markup = $block_markup;
        $this->metadata = $metadata;
    }

    public function convert() {
        foreach($this->metadata as $key => $value) {
            $p = new WP_HTML_Tag_Processor('<meta>');
            $p->next_tag();
            $p->set_attribute('name', $key);
            if(is_array($value) || is_object($value)) {
                $value = json_encode($value);
            }
            $p->set_attribute('content', $value);
            $this->result .= $p->get_updated_html() . "\n";
        }
        $this->result .= $this->block_markup;
        return true;
    }

    public function get_result() {
        return $this->result;
    }
}
