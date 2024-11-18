<?php
/**
 * Plugin Name: Data Liberation
 * Description: Data parsing and importing primitives.
 */

require_once __DIR__ . '/bootstrap.php';

/**
 * Don't run KSES on the attribute values during the import.
 * 
 * Without this filter, WP_HTML_Tag_Processor::set_attribute() will
 * assume the value is a URL and run KSES on it, which will incorrectly
 * prefix relative paths with http://.
 * 
 * For example:
 * 
 * > $html = new WP_HTML_Tag_Processor( '<img>' );
 * > $html->next_tag();
 * > $html->set_attribute( 'src', './_assets/log-errors.png' );
 * > echo $html->get_updated_html();
 * <img src="http://./_assets/log-errors.png">
 */
add_filter('wp_kses_uri_attributes', function() {
    return [];
});

// echo '<plaintext>';
// var_dump(glob('/wordpress/wp-content/uploads/*'));
// var_dump(glob('/wordpress/wp-content/plugins/data-liberation/../../docs/site/static/img/*'));
// die("X");
add_action('init', function() {
    // echo '<plaintext>';
    // $wxr_path = __DIR__ . '/tests/fixtures/wxr-simple.xml';
    // $wxr_path = __DIR__ . '/tests/wxr/woocommerce-demo-products.xml';
    $wxr_path = __DIR__ . '/tests/wxr/a11y-unit-test-data.xml';
    $hash = md5($wxr_path);
    if(file_exists('./.imported-' . $hash)) {
        return;
    }
    touch('./.imported-' . $hash);

    $all_posts = get_posts(array('numberposts' => -1, 'post_type' => 'any', 'post_status' => 'any'));
    foreach ($all_posts as $post) {
        wp_delete_post($post->ID, true);
    }

    $mode = 'wxr';
    // $mode = 'markdown';

    if('markdown' === $mode) {
        $docs_root = __DIR__ . '/../../docs/site';
        $docs_content_root = $docs_root . '/docs';
        $entity_iterator_factory = function() use ($docs_content_root) {
            return new WP_Markdown_Directory_Tree_Reader(
                $docs_content_root,
                1000
            );
        };
        $markdown_importer = WP_Markdown_Importer::create(
            $entity_iterator_factory, [
                'source_site_url' => 'file://' . $docs_content_root,
                'local_markdown_assets_root' => $docs_root,
                'local_markdown_assets_url_prefix' => '@site/',
            ]
        );
        $markdown_importer->frontload_assets();
        $markdown_importer->import_posts();
    } else {
        $entity_iterator_factory = function() use ($wxr_path) {
            $wxr = new WP_WXR_Reader();
            $wxr->connect_upstream(new WP_File_Reader($wxr_path));
            return $wxr;
        };
        $wxr_importer = WP_Stream_Importer::create(
            $entity_iterator_factory
        );
        $wxr_importer->frontload_assets();
        $wxr_importer->import_posts();
    }
});
