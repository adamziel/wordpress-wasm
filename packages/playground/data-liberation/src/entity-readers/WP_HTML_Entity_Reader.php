<?php

/**
 * Converts a single HTML file into a stream of WordPress entities.
 *
 * @TODO: Support post meta.
 */
class WP_HTML_Entity_Reader extends WP_Entity_Reader {

    protected $html;
    protected $entities;
    protected $finished = false;
    protected $post_id;

    public function __construct( $html, $post_id ) {
        $this->html = $html;
        $this->post_id = $post_id;
    }

    public function next_entity() {
        // If we're finished, we're finished.
        if( $this->finished ) {
            return false;
        }

        // If we've already read some entities, skip to the next one.
        if( null !== $this->entities ) {
            if(count($this->entities) <= 1) {
                $this->finished = true;
                return false;
            }
            array_shift( $this->entities );
            return true;
        }

        // We did not read any entities yet. Let's convert the HTML document into entities.
        $converter = new WP_HTML_To_Blocks( $this->html );
        if ( false === $converter->parse() ) {
            return false;
        }

        $all_metadata = $converter->get_all_metadata();
        $post_fields = array();
        $other_metadata = array();
        foreach ( $all_metadata as $key => $values ) {
            if ( in_array( $key, WP_Imported_Entity::POST_FIELDS ) ) {
                $post_fields[$key] = $values[0];
            } else {
                $other_metadata[$key] = $values[0];
            }
        }

        // Yield the post entity.
        $this->entities[] = new WP_Imported_Entity(
            'post',
            array_merge(
                $post_fields,
                [
                    'post_id' => $this->post_id,
                    'content' => $converter->get_block_markup(),
                ]
            )
        );

        // Yield all the metadata that don't belong to the post entity.
        foreach ( $other_metadata as $key => $value ) {
            $this->entities[] = new WP_Imported_Entity(
                'post_meta',
                [
                    'post_id' => $this->post_id,
                    'meta_key' => $key,
                    'meta_value' => $value,
                ]
            );
        }
        return true;
    }

    public function get_entity() {
        if( $this->is_finished() ) {
            return false;
        }
        return $this->entities[0];
    }

    public function is_finished(): bool {
        return $this->finished;
    }

    public function get_last_error(): ?string {
        return null;
    }

}
