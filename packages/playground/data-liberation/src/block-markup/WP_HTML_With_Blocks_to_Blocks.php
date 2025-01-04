<?php

class WP_HTML_With_Blocks_to_Blocks implements WP_Block_Markup_Converter {
	const STATE_READY    = 'STATE_READY';
	const STATE_COMPLETE = 'STATE_COMPLETE';

	private $state         = self::STATE_READY;
	private $block_markup  = '';
	private $metadata      = array();
	private $last_error    = null;
    private $original_html;
    private $parsed_blocks;

	public function __construct( $original_html ) {
		$this->original_html = $original_html;
	}

	public function convert() {
		if ( self::STATE_READY !== $this->state ) {
			return false;
		}

        if( null === $this->parsed_blocks ) {
            $this->parsed_blocks = parse_blocks( $this->original_html );
        }

        foreach($this->parsed_blocks as $block) {
            if($block['blockName'] === NULL) {
                $html_converter = new WP_HTML_To_Blocks(WP_HTML_Processor::create_fragment($block['innerHTML']));
                $html_converter->convert();
                $this->block_markup .= $html_converter->get_block_markup() . "\n";
                $this->metadata = array_merge($this->metadata, $html_converter->get_all_metadata());
            } else {
                $this->block_markup .= serialize_block($block) . "\n";
            }
        }

        $this->state = self::STATE_COMPLETE;

		return true;
	}

	public function get_meta_value( $key ) {
		if ( ! array_key_exists( $key, $this->metadata ) ) {
			return null;
		}
		return $this->metadata[ $key ][0];
	}

	public function get_all_metadata($options=[]) {
        $metadata = $this->metadata;
		if(isset($options['first_value_only']) && $options['first_value_only']) {
			$metadata = array_map(function($value) {
				return $value[0];
			}, $metadata);
		}
		return $metadata;
	}

	public function get_block_markup() {
		return $this->block_markup;
	}

	public function get_last_error() {
		return $this->last_error;
	}
}
