<?php
/**
 * Plugin Name: Data Liberation
 * Description: Data parsing and importing primitives.
 */

use Rowbot\URL\URL;

require_once __DIR__ . '/bootstrap.php';

add_action('init', function() {
    return;
    $hash = md5('docs-importer-test');
    if(file_exists('./.imported-' . $hash)) {
        return;
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
    $migrating_from_url = URL::parse('https://stylish-press.wordpress.org');
    $final_assets_url = 'http://127.0.0.1:9400/wp-content/plugins/data-liberation/attachments';

    $matched_asset_url = function(WP_Block_Markup_Url_Processor $p) {
        return (
            // When processing Markdown, we only want to download the images
            // referenced in the image tags.
            // @TODO: How can we process the videos?
            // @TODO: What other asset types are there?
            $p->get_tag() === 'IMG' &&
            $p->get_inspected_attribute_name() === 'src' &&
            url_matches( $p->get_parsed_url(), 'http://@site' )
        );
    };
    /**
     * The downloaded file name is based on the URL hash.
     * 
     * While using a content hash is tempting, it has two downsides:
     * * We may need to download the asset before computing the hash.
     * * It would de-duplicate the imported assets even if they have
     *   different URLs. This would cause subtle issues in the new sites.
     *   Imagine two users uploading the same image. Each user has
     *   different permissions. Just because Bob deletes his copy, doesn't
     *   mean we should delete Alice's copy.
     */
    $compute_asset_filename = function(URL $asset_url) {
        $filename = md5($asset_url->toString());
        $extension = pathinfo($asset_url->pathname, PATHINFO_EXTENSION);
        if(!empty($extension)) {
            $filename .= '.' . $extension;
        }
        return $filename;
    };
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

                /**
                 * Infer the attachments URLs from the post content.
                 * 
                 * Why not just emit the attachment URLs from WP_Markdown_Directory_Tree_Reader
                 * as other entities?
                 * 
                 * Whether it's Markdown, static HTML, or another static file format,
                 * we'll need to recover the attachment URLs from the We can either 
                 * have a separate pipeline step for that, or burden every format 
                 * reader with reimplementing the same logic. So let's just keep it
                 * separated.
                 */
                $p = new WP_Block_Markup_Url_Processor( $data['post_content'], $migrating_from_url->toString() );
                while ( $p->next_url() ) {
                    if ( ! $matched_asset_url( $p ) ) {
                        continue;
                    }

                    /**
                     * For Docusaurus docs, URLs starting with `http://@site` are referring
                     * to local files. Let's convert them to file:// URLs.
                     * 
                     * Also, we cannot just compare the host to `@site` because, after parsing,
                     * the host is actually just "site". The "@" symbol denotes an empty
                     * username and is present in the URL string.
                     */
                    $source_url = $p->get_raw_url();
                    if(str_starts_with($source_url, 'http://@site')) {
                        // @TODO: Use some form of joinPaths()
                        // @TODO: Source the file from the current input stream if we can.
                        //        This would allow stream-importing zipped Markdown and WXR directory
                        //        structures.
                        //        Maybe for v1 we could just support importing them from ZIP files
                        //        that are already downloaded and available in a local directory just
                        //        to avoid additional data transfer and the hurdle with implementing
                        //        multiple range requests.
                        $source_url = 'file://' . rtrim($docs_root, '/') . '/' . substr($source_url, strlen('http://@site/'));
                    }

                    /**
                     * Download the asset to a new path.
                     * 
                     * Note the path here is different than on the original site.
                     * There isn't an easy way to preserve the original assets paths on
                     * the new site.
                     * 
                     * * The assets may come from multiple domains
                     * * The paths may be outside of `/wp-content/uploads/`
                     * * The same path on multiple domains may point to different files
                     * 
                     * Even if we tried to preserve the paths starting with `/wp-content/uploads/`,
                     * we would run into race conditions where, in case of overlapping paths,
                     * the first downloaded asset would win.
                     * 
                     * The assets downloader is meant to be idempotent, deterministic, and re-entrant.
                     *
                     * Therefore, instead of trying to preserve the original paths, we'll just
                     * compute an idempotent and deterministic new path for each asset.
                     */
                    $enqueued = $downloader->enqueue_if_not_exists(
                        $source_url,
                        $compute_asset_filename($p->get_parsed_url())
                    );
                    if(false === $enqueued) {
                        // @TODO: Save the failure info somewhere so the user can review it later
                        //        and either retry or provide their own asset.
                        // Meanwhile, we may either halt the content import, or provide a placeholder
                        // asset.
                        error_log("Failed to enqueue attachment: $source_url");
                        continue;
                    }
                }
                break;
        }
    }

    while($downloader->poll()) {
        // Twiddle our thumbs until all the attachments are downloaded...
    }

    // We have all the assets downloaded now, yay!

    // Second pass: Import posts and rewrite URLs.
    // All the attachments are downloaded so we don't have to worry about missing
    // assets.
    $importer = new WP_Entity_Importer();
    $reader = new WP_Markdown_Directory_Tree_Reader(
        $docs_content_root,
        1000
    );
    /**
     * @TODO: Explore a way of making this idempotent. Maybe
     *        use GUIDs to detect whether a post or an attachment
     *        has already been imported? That would be slow on
     *        large datasets, but are we ever going to import
     *        a bunch of markdown files into a large site?
     */
    while($reader->next_entity()) {
        $attachments = [];
        $entity = $reader->get_entity();
        switch($entity->get_type()) {
            case 'post':
                // Create the post
                $data = $entity->get_data();

                $p = new WP_Block_Markup_Url_Processor( $data['post_content'], $migrating_from_url->toString() );
                while ( $p->next_url() ) {
                    if ( $matched_asset_url($p) ) {
                        $asset_url = WP_URL::parse($final_assets_url . '/' . $compute_asset_filename($p->get_parsed_url()));
                        $p->rewrite_url_components( $asset_url);

                        $filename = $compute_asset_filename($p->get_parsed_url());
                        $attachments[] = $final_assets_url . '/' . $filename;
                    } else if ( url_matches( $p->get_parsed_url(), $migrating_from_url ) ) {
                        $p->rewrite_url_components( WP_URL::parse('http://127.0.0.1:9400/') );
                    } else {
                        // Ignore other URLs.
                    }
                }
                $data['post_content'] = $p->get_updated_html();
                $entity->set_data($data);
                break;
        }
        $post_id = $importer->import_entity($entity);
        $reader->set_created_post_id($post_id);
        foreach($attachments as $filepath) {
            $importer->import_attachment($filepath, $post_id);
        }
    }
});


/**
 * @TODO: Model this after the Markdown importer above.
 *        Just don't infer the attachment URLs from the post content and
 *        instead use the attachments listed in the WXR file.
 *
 * Question: How would we know a specific image block refers to a specific
 *           attachment? We need to cross-correlate that to rewrite the URL.
 *           The image block could have query parameters, too, but presumably the
 *           path would be the same at least? What if the same file is referred
 *           to by two different URLs? e.g. assets.site.com and site.com/assets/ ?
 */
add_action('init', function() {
    $downloader = new WP_Attachment_Downloader(__DIR__ . '/attachments');
    // $wxr_path = __DIR__ . '/tests/fixtures/wxr-simple.xml';
    // $wxr_path = __DIR__ . '/tests/wxr/woocommerce-demo-products.xml';
    $wxr_path = __DIR__ . '/tests/wxr/a11y-unit-test-data.xml';
    $hash = md5($wxr_path.'a');
    if(file_exists('./.imported-' . $hash)) {
        return;
    }
    touch('./.imported-' . $hash);

    // Do two passes.
    // * First pass: Download attachments.
    $reader = WP_WXR_Reader::from_stream();
    $bytes = new WP_File_Byte_Stream($wxr_path);
    /**
     * The downloaded file name is based on the URL hash.
     * 
     * While using a content hash is tempting, it has two downsides:
     * * We may need to download the asset before computing the hash.
     * * It would de-duplicate the imported assets even if they have
     *   different URLs. This would cause subtle issues in the new sites.
     *   Imagine two users uploading the same image. Each user has
     *   different permissions. Just because Bob deletes his copy, doesn't
     *   mean we should delete Alice's copy.
     */
    $compute_asset_filename = function(URL $asset_url) {
        $filename = md5($asset_url->toString());
        $extension = pathinfo($asset_url->pathname, PATHINFO_EXTENSION);
        if(!empty($extension)) {
            $filename .= '.' . $extension;
        }
        return $filename;
    };
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
            $data = $entity->get_data();
            // Download attachments.
            if('post' === $entity->get_type() && isset($data['post_type']) && $data['post_type'] === 'attachment') {
                // /wp-content/uploads/2024/01/image.jpg
                // But what if the attachment path does not start with /wp-content/uploads?
                // Should we stick to the original path? Typically no. It might be `/index.php`,
                // which we don't want to accidentally overwrite. However, some imports might
                // need to preserve the original path.
                // So then, should we force the /wp-content/uploads prefix?
                // Most of the time, yes, unless an explicit parameter was set to
                // always preserve the original path.
                // $attachment_path = '@TODO';
                $url = WP_URL::parse($data['attachment_url']);
                $downloader->enqueue_if_not_exists(
                    $url->toString(),
                    $compute_asset_filename($url)
                );

                // @TODO: Should we detect <img src=""> in post content and
                //        download those assets as well?
            }
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

    // * Second pass: Import posts and rewrite URLs.
    $reader = WP_WXR_Reader::from_stream();
    $bytes = new WP_File_Byte_Stream($wxr_path);
    $importer = new WP_Entity_Importer();
    $migrating_from_url = 'https://playground.internal/';
    $final_site_url = 'http://127.0.0.1:9400';
    $final_assets_url = $final_site_url . '/wp-content/plugins/data-liberation/attachments';

    while(true) {
        if(false === $bytes->next_bytes()) {
            $reader->input_finished();
        } else {
            $reader->append_bytes($bytes->get_bytes());
        }
        while($reader->next_entity()) {
            $entity = $reader->get_entity();

            /**
             * @TODO:
             * * What if the attachment is not found? Error out? Ignore? In a UI-based
             *   importer scenario, this is the time to log a failure to let the user
             *   fix it later on. In a CLI-based Blueprint step importer scenario, we
             *   might want to provide an "image not found" placeholder OR ignore the
             *   failure.
             */

            $attachments = [];
            // Rewrite the URLs in the post.
            switch($entity->get_type()) {
                case 'post':
                    $data = $entity->get_data();
                    foreach(['guid', 'post_content', 'post_excerpt'] as $key) {
                        $p = new WP_Block_Markup_Url_Processor( $data['post_content'], $migrating_from_url );
                        while ( $p->next_url() ) {
                            // @TODO: How do we know if the URL is for an attachment?
                            if ( 
                                $p->get_tag() === 'IMG' &&
                                $p->get_inspected_attribute_name() === 'src'
                            ) {
                                $p->rewrite_url_components(
                                    WP_URL::parse($final_assets_url . '/' . $compute_asset_filename($p->get_parsed_url()))
                                );
        
                                $filename = $compute_asset_filename($p->get_parsed_url());
                                $attachments[] = $final_assets_url . '/' . $filename;
                            } else if ( url_matches( $p->get_parsed_url(), $migrating_from_url ) ) {
                                $p->rewrite_url_components( WP_URL::parse($final_site_url) );
                            } else {
                                // Ignore other URLs.
                            }
                        }
                        $data[$key] = $p->get_updated_html();
                    }
                    $entity->set_data($data);
                    break;
            }
            $post_id = $importer->import_entity($entity);
            foreach($attachments as $filepath) {
                $importer->import_attachment($filepath, $post_id);
            }
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
