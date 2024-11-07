<?php
/**
 * Plugin Name: Data Liberation
 * Description: Data parsing and importing primitives.
 */

require_once __DIR__ . '/bootstrap.php';

add_action('init', function() {
    $hash = md5('docs-importer-test');
    if(file_exists('./.imported-' . $hash)) {
        // return;
    }
    touch('./.imported-' . $hash);

    $all_posts = get_posts(array('numberposts' => -1, 'post_type' => 'any', 'post_status' => 'any'));
    foreach ($all_posts as $post) {
        wp_delete_post($post->ID, true);
    }

    $importer = new WP_Entity_Importer();
    $reader = new WP_Markdown_Directory_Tree_Reader(
        __DIR__ . '/../../docs/site/docs',
        1000
    );

    while($reader->next_entity()) {
        $post_id = $importer->import_entity($reader->get_entity());
        $reader->set_created_post_id($post_id);
    }
    return;

    $wxr_reader = WP_WXR_Reader::from_stream();
    $bytes = new WP_File_Byte_Stream($wxr_path);
    while($bytes->next_bytes() && !$wxr_reader->is_finished()) {
        $wxr_reader->append_bytes($bytes->get_bytes());
        if($bytes->is_finished()) {
            $wxr_reader->input_finished();
        }
        while($wxr_reader->next_entity()) {
            $importer->import_entity($reader->get_entity());
        }
        if($wxr_reader->get_last_error()) {
            var_dump($wxr_reader->get_last_error());
            die();
        }
    }

    // $wxr_path = __DIR__ . '/tests/fixtures/wxr-simple.xml';
    // $wxr_path = __DIR__ . '/tests/wxr/woocommerce-demo-products.xml';
    $wxr_path = __DIR__ . '/tests/wxr/a11y-unit-test-data.xml';
    $hash = md5($wxr_path);
    if(file_exists('./.imported-' . $hash)) {
        return;
    }
    touch('./.imported-' . $hash);

    $importer = new WP_Entity_Importer();

    echo '<plaintext>';
    $reader = WP_WXR_Reader::from_stream();
    $chain = new WP_Stream_Chain([
        new WP_File_Byte_Stream($wxr_path),
        new ProcessorByteStream($reader, function(WP_Byte_Stream_State $state, WP_WXR_Reader $reader) use($importer) {
            $new_input = $state->consume_input_bytes();
            // @TODO Why would this ever be null? Don't invoke this function if there's
            //       no new input.
            if(null === $new_input) {
                return false;
            }

            $reader->append_bytes($new_input);
            // @TODO Handle at the stream chain level,
            //       errors mean we can no longer continue.
            // if($reader->get_last_error()) {
            //     var_dump($reader->get_last_error());
            //     die();
            // }
            while($reader->next_entity()) {
                $importer->import_entity($reader->get_entity());
            }
            return ! $reader->is_finished();
        })
    ]);
    // echo '<plaintext>';
    $chain->run_to_completion();
});
