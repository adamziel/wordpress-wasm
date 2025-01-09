<?php
/**
 * Converts a WP_Blocks_With_Metadata object into a stream of WordPress post and post meta entities.
 *
 * Outputs a single post and a number of post meta entities.
 */
class WP_Blocks_With_Metadata_Entity_Reader extends WP_Entity_Reader {

	protected $block_markup;
	protected $metadata;
	protected $enqueued_entities = null;
	protected $current_entity;
	protected $finished = false;
	protected $post_id;
	protected $last_error;

	public function __construct( WP_Blocks_With_Metadata $blocks_with_meta, $post_id ) {
		$this->block_markup = $blocks_with_meta->get_block_markup();
		$this->metadata     = $blocks_with_meta->get_all_metadata();
		$this->post_id      = $post_id;
	}

	public function next_entity() {
		if ( $this->finished ) {
			return false;
		}

		$this->current_entity = null;

		if ( null !== $this->enqueued_entities ) {
			if ( count( $this->enqueued_entities ) === 0 ) {
				$this->finished = true;
				return false;
			} else {
				$this->current_entity = array_shift( $this->enqueued_entities );
				return true;
			}
		}

		$all_metadata   = $this->metadata;
		$post_fields    = array();
		$other_metadata = array();
		foreach ( $all_metadata as $key => $values ) {
			if ( in_array( $key, WP_Imported_Entity::POST_FIELDS, true ) ) {
				$post_fields[ $key ] = $values[0];
			} else {
				$other_metadata[ $key ] = $values[0];
			}
		}

		$post_fields['post_id']      = $this->post_id;
		$post_fields['post_content'] = $this->block_markup;

		// In Markdown, the frontmatter title can be a worse title candidate than
		// the first H1 block. In block markup exports, it will be the opposite.
		//
		// @TODO: Enable the API consumer to customize the title resolution.
		if ( ! isset( $post_fields['post_title'] ) ) {
			$removed_title = WP_Import_Utils::remove_first_h1_block_from_block_markup( $post_fields['post_content'] );
			if ( false !== $removed_title ) {
				$post_fields['post_title']   = $removed_title['h1_content'];
				$post_fields['post_content'] = $removed_title['remaining_html'];
			}
		}

		// Yield the post entity.
		$this->enqueued_entities[] = new WP_Imported_Entity( 'post', $post_fields );

		// Yield all the metadata that don't belong to the post entity.
		foreach ( $other_metadata as $key => $value ) {
			$this->enqueued_entities[] = new WP_Imported_Entity(
				'post_meta',
				array(
					'post_id' => $this->post_id,
					'key' => $key,
					'value' => $value,
				)
			);
		}

		$this->current_entity = array_shift( $this->enqueued_entities );
		return true;
	}

	public function get_entity() {
		if ( $this->is_finished() ) {
			return false;
		}
		return $this->current_entity;
	}

	public function is_finished(): bool {
		return $this->finished;
	}

	public function get_last_error(): ?string {
		return $this->last_error;
	}
}
