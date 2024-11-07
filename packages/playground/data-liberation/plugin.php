<?php
/**
 * Plugin Name: Data Liberation
 * Description: Data parsing and importing primitives.
 */

require_once __DIR__ . '/bootstrap.php';

add_action('init', function() {
    return;
    $hash = md5('docs-importer-test');
    if(file_exists('./.imported-' . $hash)) {
        // return;
    }
    touch('./.imported-' . $hash);

    $all_posts = get_posts(array('numberposts' => -1, 'post_type' => 'any', 'post_status' => 'any'));
    foreach ($all_posts as $post) {
        wp_delete_post($post->ID, true);
    }

    /**
     * Assume markdown importer is not fully re-entrant. We're unlikely to see 100GB of
     * markdown files so let's specialize in managable data amounts for now. The only
     * re-entrant part would be pre-fetching the static assets. If the asset already exists,
     * there's no need to re-fetch it.
     */

    $importer = new WP_Entity_Importer();
    $reader = new WP_Markdown_Directory_Tree_Reader(
        __DIR__ . '/../../docs/site/docs',
        1000
    );

    while($reader->next_entity()) {
        $entity = $reader->get_entity();
        switch($entity->get_type()) {
            case 'post':
                $data = $entity->get_data();
                $data['post_content'] = wp_rewrite_urls(array(
                    'block_markup' => $data['post_content'],
                    'from-url' => 'https://stylish-press.wordpress.org/',
                    'to-url' => 'https://playground.wordpress.net/scope:stylish-press/',
                ));
                $entity->set_data($data);
                break;
        }
        $post_id = $importer->import_entity($entity);
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
});


add_action('init', function() {
    $wxr_path = __DIR__ . '/tests/fixtures/wxr-simple.xml';
    // $wxr_path = __DIR__ . '/tests/wxr/woocommerce-demo-products.xml';
    // $wxr_path = __DIR__ . '/tests/wxr/a11y-unit-test-data.xml';
    $hash = md5($wxr_path.'a');
    if(file_exists('./.imported-' . $hash)) {
        return;
    }
    touch('./.imported-' . $hash);
    return;

    $importer = new WP_Entity_Importer();
    $reader = WP_WXR_Reader::from_stream();

    $bytes = new WP_File_Byte_Stream($wxr_path);
    while(true) {
        if(false === $bytes->next_bytes()) {
            $reader->input_finished();
        } else {
            $reader->append_bytes($bytes->get_bytes());
        }
        while($reader->next_entity()) {
            $entity = $reader->get_entity();
            switch($entity->get_type()) {
                case 'post':
                    $data = $entity->get_data();
                    $data['guid'] = wp_rewrite_urls(array(
                        'block_markup' => $data['guid'],
                        'from-url' => 'https://playground.internal/',
                        'to-url' => 'http://127.0.0.1:9400/scope:stylish-press/',
                    ));
                    $data['post_content'] = wp_rewrite_urls(array(
                        'block_markup' => $data['post_content'],
                        'from-url' => 'https://playground.internal/',
                        'to-url' => 'http://127.0.0.1:9400/scope:stylish-press/',
                    ));
                    $data['post_excerpt'] = wp_rewrite_urls(array(
                        'block_markup' => $data['post_excerpt'],
                        'from-url' => 'https://playground.internal/',
                        'to-url' => 'http://127.0.0.1:9400/scope:stylish-press/',
                    ));
                    $entity->set_data($data);
                    break;
            }
            $importer->import_entity($entity);
        }
        if($reader->get_last_error()) {
            var_dump($reader->get_last_error());
            die();
        }
        if($reader->is_finished()) {
            break;
        }
        if(null === $bytes->get_bytes()) {
            // @TODO: Why do we need this? Why is_finished() isn't enough?
            break;
        }
    }
});
