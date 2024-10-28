<?php

class WP_WXR_URL_Rewrite_Processor
{

    static public function stream($current_site_url, $new_site_url) {
        return WP_XML_Processor::stream(function ($processor) use($current_site_url, $new_site_url) {
            if (static::is_wxr_content_node($processor)) {
                $text = $processor->get_modifiable_text();
                $updated_text = wp_rewrite_urls([
                    'block_markup' => $text,
                    'current-site-url' => $current_site_url,
                    'new-site-url' => $new_site_url,
                ]);
                if($updated_text !== $text) {
                    $processor->set_modifiable_text($updated_text);
                }
            }
         });        
    }

    static private function is_wxr_content_node( WP_XML_Processor $processor ) {
        $breadcrumbs = $processor->get_breadcrumbs();
        if (
            ! in_array( 'excerpt:encoded', $breadcrumbs ) &&
            ! in_array( 'content:encoded', $breadcrumbs ) &&
            ! in_array( 'guid', $breadcrumbs ) &&
            ! in_array( 'link', $breadcrumbs ) &&
            ! in_array( 'wp:attachment_url', $breadcrumbs ) &&
            ! in_array( 'wp:comment_content', $breadcrumbs ) &&
            ! in_array( 'wp:base_site_url', $breadcrumbs ) &&
            ! in_array( 'wp:base_blog_url', $breadcrumbs )
            // Meta values are not supported yet. We'll need to support
            // WordPress core options that may be saved as JSON, PHP Deserialization, and XML,
            // and then provide extension points for plugins authors support
            // their own options.
            // !in_array('wp:postmeta', $processor->get_breadcrumbs())
        ) {
            return false;
        }
        return true;
    }

}
