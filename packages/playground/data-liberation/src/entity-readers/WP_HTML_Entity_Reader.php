<?php

/**
 * Converts a single HTML file into a stream of WordPress entities.
 */
class WP_HTML_Entity_Reader extends WP_Entity_Reader {

	protected $block_markup;
	protected $metadata;
	protected $entities;
	protected $finished = false;
	protected $post_id;

	public function __construct( $block_markup, $metadata, $post_id ) {
		$this->block_markup = $block_markup;
		$this->metadata = $metadata;
		$this->post_id = $post_id;
	}

	public function next_entity() {
		// If we're finished, we're finished.
		if ( $this->finished ) {
			return false;
		}

		// If we've already read some entities, skip to the next one.
		if ( null !== $this->entities ) {
			array_shift( $this->entities );
			if ( count( $this->entities ) === 0 ) {
				$this->finished = true;
				return false;
			}
			return true;
		}

		$post_fields    = array();
		$other_metadata = array();
		foreach ( $this->metadata as $key => $values ) {
			if ( in_array( $key, WP_Imported_Entity::POST_FIELDS, true ) ) {
				$post_fields[ $key ] = $values[0];
			} else {
				$other_metadata[ $key ] = $values[0];
			}
		}

		// Yield the post entity.
		$this->entities[] = new WP_Imported_Entity(
			'post',
			array_merge(
				$post_fields,
				array(
					'post_id' => $this->post_id,
					'content' => $this->block_markup,
				)
			)
		);

		// Yield all the metadata that don't belong to the post entity.
		foreach ( $other_metadata as $key => $value ) {
			$this->entities[] = new WP_Imported_Entity(
				'post_meta',
				array(
					'post_id' => $this->post_id,
					'key' => $key,
					'value' => $value,
				)
			);
		}
		return true;
	}

	/**
	 * Returns the current entity.
	 *
	 * @return WP_Imported_Entity|false The current entity, or false if there are no entities left.
	 */
	public function get_entity() {
		if ( $this->is_finished() ) {
			return false;
		}
		return $this->entities[0];
	}

	/**
	 * Checks if this reader has finished yet.
	 *
	 * @return bool Whether the reader has finished.
	 */
	public function is_finished(): bool {
		return $this->finished;
	}

	/**
	 * Returns the last error that occurred when processing the HTML.
	 *
	 * @return string|null The last error, or null if there was no error.
	 */
	public function get_last_error(): ?string {
		return null;
	}
}
