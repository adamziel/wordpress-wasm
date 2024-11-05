<?php
/**
 * A copy of the WP_Interactivity_API_Directives_Processor class
 * from the Gutenberg plugin.
 *
 * @package WordPress
 * @subpackage Interactivity API
 * @since 6.5.0
 */

final class WP_Modifiable_HTML_Processor extends WP_HTML_Processor {

    public function get_string_index_after_current_token()
    {
        $name = 'current_token';
        $this->set_bookmark($name);
        $bookmark = $this->bookmarks['_'.$name];
        $this->release_bookmark($name);
        return $bookmark->start + $bookmark->length;
    }

}