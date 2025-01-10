<?php

class WP_Data_Liberation_HTML_Processor extends WP_HTML_Processor {

	public function get_inner_html() {
		if ( '#tag' !== $this->get_token_type() ) {
			return false;
		}

		if ( $this->is_tag_closer() ) {
			return false;
		}

		if ( false === WP_HTML_Tag_Processor::set_bookmark( 'tag-start' ) ) {
			return false;
		}

		$this->skip_to_closer();

		if ( false === WP_HTML_Tag_Processor::set_bookmark( 'tag-end' ) ) {
			WP_HTML_Tag_Processor::release_bookmark( 'tag-start' );
			return false;
		}

		$inner_html_start = $this->bookmarks['tag-start']->start + $this->bookmarks['tag-start']->length;
		$inner_html_end   = $this->bookmarks['tag-end']->start - $inner_html_start;

		WP_HTML_Tag_Processor::seek( 'tag-start' );
		WP_HTML_Tag_Processor::release_bookmark( 'tag-start' );
		WP_HTML_Tag_Processor::release_bookmark( 'tag-end' );

		return substr(
			$this->html,
			$inner_html_start,
			$inner_html_end
		);
	}

	public function skip_to_closer() {
		$starting_depth = $this->get_current_depth();
		while ( $this->next_token() ) {
			if (
				$this->get_token_type() === '#tag' &&
				$this->is_tag_closer() &&
				$this->get_current_depth() === $starting_depth - 1
			) {
				return true;
			}
		}

		return false;
	}

	public function get_string_index_after_current_token() {
		$name = 'current_token';
		$this->set_bookmark( $name );
		$bookmark = $this->bookmarks[ '_' . $name ];
		$this->release_bookmark( $name );
		return $bookmark->start + $bookmark->length;
	}
}
