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

    /**
     * Assume markdown importer is not fully re-entrant. We're unlikely to see 100GB of
     * markdown files so let's specialize in managable data amounts for now. The only
     * re-entrant part would be pre-fetching the static assets. If the asset already exists,
     * there's no need to re-fetch it.
     */

    // Do two passes.

    // First pass: Download all the attachments
    $docs_root = __DIR__ . '/../../docs/site';
    $docs_content_root = $docs_root . '/docs';
    $reader = new WP_Markdown_Directory_Tree_Reader(
        $docs_content_root,
        1000
    );
    $downloader = new WP_Attachment_Downloader(__DIR__ . '/attachments');
    $base_site_url = 'https://stylish-press.wordpress.org/';
    while($reader->next_entity()) {
        if($downloader->queue_full()) {
            echo 'Queue full, polling...';
            $downloader->poll();
            continue;
        }

        $entity = $reader->get_entity();
        switch($entity->get_type()) {
            case 'post':
                $data = $entity->get_data();

                // @TODO: What should $base_url be?
                $base_url = null;

                // @TODO: Should we parse the post here? Or should the reader emit
                //        the URLs as other entities?
                // Well, doing it in the reader would require every reader to implemet
                // the same repetitive logic and also reason about domains, so maybe
                // it's better to do it here.
                $p = new WP_Block_Markup_Url_Processor( $data['post_content'], $base_url );
                while ( $p->next_url() ) {
                    if ( ! url_matches( $p->get_parsed_url(), 'http://@site' ) ) {
                        continue;
                    }

                    // When processing Markdown, we only want to download the images
                    // referenced in the image tags.
                    $raw_url = $p->get_raw_url();
                    if ( $p->get_tag() !== 'IMG' || $p->get_inspected_attribute_name() !== 'src' ) {
                        continue;
                    }

                    // If the host is @site, we're dealing with a local file.
                    // Let's use a file:// URL, then.
                    // We're not comparing the host to @site because the host is
                    // actually just `site` and there's an empty username in the URL.
                    if(str_starts_with($raw_url, 'http://@site')) {
                        $raw_url = 'file://' . rtrim($docs_root, '/') . '/' . substr($raw_url, strlen('http://@site/'));
                    }

                    // @TODO: figure out whether there's a good reason to stick to the
                    //        same assets paths on the new site. Maybe it's fine to
                    //        compute a new path for each asset?
                    $enqueued = $downloader->enqueue_if_not_exists($raw_url, $p->get_parsed_url()->pathname);
                    if(false === $enqueued) {
                        // @TODO: Save the failure info somewhere so the user can review it later
                        //        and either retry or provide their own asset.
                        // Meanwhile, we may either halt the content import, or provide a placeholder
                        // asset.
                        error_log("Failed to enqueue attachment: $raw_url");
                        continue;
                    }
                }
                break;
        }
    }

    while($downloader->poll()) {
        // Twiddle our thumbs until all the attachments are downloaded...
    }

    // Second pass: Import posts and rewrite URLs.
    // All the attachments are downloaded so we don't have to worry about missing
    // assets.
    $importer = new WP_Entity_Importer();
    $reader = new WP_Markdown_Directory_Tree_Reader(
        $docs_content_root,
        1000
    );
    while($reader->next_entity()) {
        $entity = $reader->get_entity();
        switch($entity->get_type()) {
            case 'post':
                // Create the post
                $data = $entity->get_data();

                // @TODO: Do a single pass to rewrite all the URLs
                $data['post_content'] = wp_rewrite_urls(array(
                    'block_markup' => $data['post_content'],
                    'from-url' => $base_site_url,
                    'to-url' => 'http://127.0.0.1:9400/',
                ));

                // Rewrite attachments URLs. Point them to the location we downloaded
                // them to.
                // @TODO: Contain these to wp-content/uploads/. Do not migrate attachments
                //        to other locations.
                $data['post_content'] = wp_rewrite_urls(array(
                    'block_markup' => $data['post_content'],
                    'from-url' => 'http://@site/',
                    'to-url' => 'http://127.0.0.1:9400/wp-content/plugins/data-liberation/attachments/',
                ));
                $entity->set_data($data);

                // @TODO: Also create the attachments and connect them to the post.
                break;
        }
        $post_id = $importer->import_entity($entity);
        $reader->set_created_post_id($post_id);
    }
});


add_action('init', function() {
    $downloader = new WP_Attachment_Downloader(__DIR__ . '/attachments');
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

    // @TODO: Do two passes.
    // * First pass: Download attachments.
    // * Second pass: Import posts and rewrite URLs.
    $bytes = new WP_File_Byte_Stream($wxr_path);
    while(true) {
        if($downloader->queue_full()) {
            $downloader->poll();
            continue;
        }

        if(false === $bytes->next_bytes()) {
            $reader->input_finished();
        } else {
            $reader->append_bytes($bytes->get_bytes());
        }
        while($reader->next_entity()) {
            $entity = $reader->get_entity();
            // Download attachments.
            switch($entity->get_type()) {
                case 'post':
                    if($post['post_type'] === 'attachment') {
                        // /wp-content/uploads/2024/01/image.jpg
                        // But what if the attachment path does not start with /wp-content/uploads?
                        // Should we stick to the original path? Typically no. It might be `/index.php`,
                        // which we don't want to accidentally overwrite. However, some imports might
                        // need to preserve the original path.
                        // So then, should we force the /wp-content/uploads prefix?
                        // Most of the time, yes, unless an explicit parameter was set to
                        // always preserve the original path.
                        // $attachment_path = '@TODO';
                        $downloader->enqueue($post['attachment_url'], $attachment_path);
                    }
                    // @TODO: Should we detect <img src=""> in post content and
                    //        download those assets as well?
                    break;
            }

            /**
             * @TODO:
             * * What if the attachment is downloaded to a different path? How do we
             *   approach rewriting the URLs in the post content? We may have already
             *   inserted some posts into the database. Perhaps we need to do one pass
             *   to download attachments, and a second pass to import the posts?
             * * What if the attachment is not found? Error out? Ignore? In a UI-based
             *   importer scenario, this is the time to log a failure to let the user
             *   fix it later on. In a CLI-based Blueprint step importer scenario, we
             *   might want to provide an "image not found" placeholder OR ignore the
             *   failure.
             */

            // Rewrite the URLs in the post.
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

    while($downloader->poll()) {
        // Twiddle our thumbs...
    }
});
