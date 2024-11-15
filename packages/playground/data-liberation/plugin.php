<?php
/**
 * Plugin Name: Data Liberation
 * Description: Data parsing and importing primitives.
 * 
 * @TODO:
 * * Re-entrant import via storing state on error, pausing, and resuming.
 * * Disable anything remotely related to KSES during the import. KSES
 *   modifies and often corrupts the content, and it also slows down the
 *   import. If we don't trust the imported content, we have larger problems
 *   than some escaping.
 * * Research which other filters are also worth disabling during the import.
 *   What would be a downside of disabling ALL the filters except the ones
 *   registered by WordPress Core? The upside would be preventing plugins from
 *   messing with the imported content. The downside would be the same. What else?
 *   Perhaps that could be a choice and left up to the API consumer?
 * * Potentially – idempotent import.
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

echo '<plaintext>';
// var_dump(glob('/wordpress/wp-content/uploads/*'));
// var_dump(glob('/wordpress/wp-content/plugins/data-liberation/../../docs/site/static/img/*'));
// die("X");
add_action('init', function() {
    // return;
    // echo '<plaintext>';
    // $wxr_path = __DIR__ . '/tests/fixtures/wxr-simple.xml';
    // $wxr_path = __DIR__ . '/tests/wxr/woocommerce-demo-products.xml';
    $wxr_path = __DIR__ . '/tests/wxr/a11y-unit-test-data.xml';
    $hash = md5($wxr_path);
    if(file_exists('./.imported-' . $hash)) {
        // return;
    }
    touch('./.imported-' . $hash);
    // return;

    $all_posts = get_posts(array('numberposts' => -1, 'post_type' => 'any', 'post_status' => 'any'));
    foreach ($all_posts as $post) {
        wp_delete_post($post->ID, true);
    }

    $docs_root = __DIR__ . '/../../docs/site';
    $docs_content_root = $docs_root . '/docs';
    $markdown_entities_factory = function() use ($docs_content_root) {
        $reader = new WP_Markdown_Directory_Tree_Reader(
            $docs_content_root,
            1000
        );
        return $reader->generator();
    };
    $markdown_importer = WP_Markdown_Importer::create(
        $markdown_entities_factory, [
            // 'source_site_url' => 'http://@site',
            'local_markdown_assets_root' => $docs_root,
            'local_markdown_assets_url_prefix' => '@site',
        ]
    );
    $markdown_importer->frontload_assets();
    $markdown_importer->import_posts();
    // die("X");

    // $wxr_entities_factory = function() use ($wxr_path) {
    //     return WP_WXR_Reader::stream_from(
    //         new WP_File_Byte_Stream($wxr_path)
    //     );
    // };
    // $wxr_importer = WP_Stream_Importer::create(
    //     $wxr_entities_factory
    // );
    // $wxr_importer->frontload_assets();
    // $wxr_importer->import_posts();
});

/**
 * Idea:
 * * Stream-process the WXR file.
 * * Frontload all the assets before processing the posts – in an idempotent
 *   and re-entrant way.
 * * Import the posts, rewrite the URLs and IDs before inserting anything.
 * * Never do any post-processing at the database level after inserting. That's
 *   too slow for large datasets.
 */
class WP_Stream_Importer {

    /**
     * Populated from the WXR file's <wp:base_blog_url> tag.
     */
    protected $source_site_url;
    private $entity_iterator_factory;
    /**
	 * @param array|string|null $query {
	 *     @type string      $uploads_path  The directory to download the media attachments to.
	 *                                      E.g. WP_CONTENT_DIR . '/uploads'
     *     @type string      $uploads_url   The URL where the media attachments will be accessible
     *                                      after the import. E.g. http://127.0.0.1:9400/wp-content/uploads/
	 * }
     */
    protected $options;
    protected $downloader;

    static public function create(
        $entity_iterator_factory,
        $options = []
    ) {
        if(!isset($options['new_site_url'])) {
            $options['new_site_url'] = get_site_url();
        }

        if(!isset($options['uploads_path'])) {
            $options['uploads_path'] = WP_CONTENT_DIR . '/uploads';
        }
        // Remove the trailing slash to make concatenation easier later.
        $options['uploads_path'] = rtrim($options['uploads_path'], '/');

        if(!isset($options['uploads_url'])) {
            $options['uploads_url'] = $options['new_site_url'] . '/wp-content/uploads';
        }
        // Remove the trailing slash to make concatenation easier later.
        $options['uploads_url'] = rtrim($options['uploads_url'], '/');

        return new static($entity_iterator_factory, $options);
    }

    private function __construct(
        $entity_iterator_factory,
        $options = []
    ) {
        $this->entity_iterator_factory = $entity_iterator_factory;
        $this->options = $options;
        if(isset($options['source_site_url'])) {
            $this->source_site_url = $options['source_site_url'];
        }
    }

    /**
     * Downloads all the assets referenced in the imported entities.
     * 
     * This method is idempotent, re-entrant, and should be called
     * before import_posts() so that every inserted post already has
     * all its attachments downloaded.
     */
    public function frontload_assets() {
        $factory = $this->entity_iterator_factory;
        $entities = $factory();
        $this->downloader = new WP_Attachment_Downloader($this->options['uploads_path']);
        foreach($entities as $entity) {
            if($this->downloader->queue_full()) {
                $this->downloader->poll();
                continue;
            }
    
            $data = $entity->get_data();
            if('site_option' === $entity->get_type() && $data['option_name'] === 'home') {
                $this->source_site_url = $data['option_value'];
            } else if('post' === $entity->get_type()) {
                if(isset($data['post_type']) && $data['post_type'] === 'attachment') {
                    // Download media attachment entities.
                    $this->enqueue_attachment_download(
                        $data['attachment_url']
                    );
                } else if(isset($data['post_content'])) {
                    $this->enqueue_attachments_referenced_in_post_content(
                        $data['post_content']
                    );
                }
            }
        }
    
        while($this->downloader->poll()) {
            // Twiddle our thumbs as the downloader processes the requests...
            /**
             * @TODO:
             * * Process and store failures.
             *   E.g. what if the attachment is not found? Error out? Ignore? In a UI-based
             *   importer scenario, this is the time to log a failure to let the user
             *   fix it later on. In a CLI-based Blueprint step importer scenario, we
             *   might want to provide an "image not found" placeholder OR ignore the
             *   failure.
             */
        }
    }

    /**
     * @TODO: Explore a way of making this idempotent. Maybe
     *        use GUIDs to detect whether a post or an attachment
     *        has already been imported? That would be slow on
     *        large datasets, but are we ever going to import
     *        a bunch of markdown files into a large site?
     */
    public function import_posts() {
        $importer = new WP_Entity_Importer();
        $factory = $this->entity_iterator_factory;
        $entities = $factory();
        foreach($entities as $entity) {
            $attachments = [];
            // Rewrite the URLs in the post.
            switch($entity->get_type()) {
                case 'post':
                    $data = $entity->get_data();
                    foreach(['guid', 'post_content', 'post_excerpt'] as $key) {
                        if(!isset($data[$key])) {
                            continue;
                        }
                        $p = new WP_Block_Markup_Url_Processor( $data[$key], $this->source_site_url );
                        while ( $p->next_url() ) {
                            if ( $this->url_processor_matched_asset_url( $p ) ) {
                                $filename = $this->new_asset_filename($p->get_raw_url());
                                $new_asset_url = $this->options['uploads_url'] . '/' . $filename;
                                // rewrite_url_components doesn't play well with 
                                // src="@site/image.png" that's sometimes encountered
                                // in imported Docusaurus files.
                                //
                                // $p->rewrite_url_components(WP_URL::parse($new_asset_url));
                                $p->set_raw_url($new_asset_url);
                                $attachments[] = $new_asset_url;
                                /**
                                 * @TODO: How would we know a specific image block refers to a specific
                                 *        attachment? We need to cross-correlate that to rewrite the URL.
                                 *        The image block could have query parameters, too, but presumably the
                                 *        path would be the same at least? What if the same file is referred
                                 *        to by two different URLs? e.g. assets.site.com and site.com/assets/ ?
                                 *        A few ideas: GUID, block attributes, fuzzy matching. Maybe a configurable
                                 *        strategy? And the API consumer would make the decision?
                                 */
                            } else if ( 
                                $this->source_site_url &&
                                $p->get_parsed_url() &&
                                url_matches( $p->get_parsed_url(), $this->source_site_url )
                            ) {
                                $p->rewrite_url_components( WP_URL::parse($this->options['new_site_url']) );
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
    }

    /**
     * The downloaded file name is based on the URL hash.
     * 
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
     * 
     * While using a content hash is tempting, it has two downsides:
     * * We may need to download the asset before computing the hash.
     * * It would de-duplicate the imported assets even if they have
     *   different URLs. This would cause subtle issues in the new sites.
     *   Imagine two users uploading the same image. Each user has
     *   different permissions. Just because Bob deletes his copy, doesn't
     *   mean we should delete Alice's copy.
     */
    private function new_asset_filename(string $asset_url) {
        $filename = md5($asset_url);
        $parsed_url = WP_URL::parse($asset_url);
        if(false !== $parsed_url) {
            $pathname = $parsed_url->pathname;
        } else {
            // $asset_url is not an absolute URL – perhaps it's a relative path.
            $pathname = $asset_url;
        }
        $extension = pathinfo($pathname, PATHINFO_EXTENSION);
        if(!empty($extension)) {
            $filename .= '.' . $extension;
        }
        return $filename;
    }

    /**
     * Infers and enqueues the attachments URLs from the post content.
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
    protected function enqueue_attachments_referenced_in_post_content($post_content) {
        $p = new WP_Block_Markup_Url_Processor( $post_content, $this->source_site_url );
        while ( $p->next_url() ) {
            if ( ! $this->url_processor_matched_asset_url( $p ) ) {
                continue;
            }

            $enqueued = $this->enqueue_attachment_download( $p->get_raw_url() );
            if(false === $enqueued) {
                continue;
            }
        }
    }

    protected function enqueue_attachment_download(string $attachment_url) {
        if ( ! WP_URL::canParse($attachment_url) ) {
            /**
             * This is not an absolute URL and we cannot process it.
             * 
             * If this is a relative path, we don't know whether it's relative to the page URL
             * or to a local directory from which the imported data is being loaded.
             * 
             * If this is not a relative path, it's unclear whether we can do anything at all here.
             * 
             * @TODO: Do not log an error here. This is called as a part of enqueue_attachments_referenced_in_post_content()
             *        which may yield a few false positives. Let's just write it somewhere and allow the user to review all
             *        such failures later on.
             */
            _doing_it_wrong(__METHOD__, "The attachment URL '$attachment_url' must be an absolute file://, http://, or https:// URL.", '__WP_VERSION__');
            return false;
        }
        $success = $this->downloader->enqueue_if_not_exists(
            $attachment_url,
            $this->new_asset_filename($attachment_url)
        );
        if(false === $success) {
            // @TODO: Save the failure info somewhere so the user can review it later
            //        and either retry or provide their own asset.
            // Meanwhile, we may either halt the content import, or provide a placeholder
            // asset.
            _doing_it_wrong(__METHOD__, "Failed to enqueue attachment: " . $attachment_url, '__WP_VERSION__');
        }
        return $success;
    }

    /**
     * By default, we want to download all the assets referenced in the
     * posts that are hosted on the source site.
     *
     * @TODO: How can we process the videos?
     * @TODO: What other asset types are there?
     */
    protected function url_processor_matched_asset_url(WP_Block_Markup_Url_Processor $p) {
        return (
            $p->get_tag() === 'IMG' &&
            $p->get_inspected_attribute_name() === 'src' &&
            (!$this->source_site_url || url_matches( $p->get_parsed_url(), $this->source_site_url ))
        );
    }

}

/**
 * Assume markdown importer is not fully re-entrant. We're unlikely to see 100GB of
 * markdown files so let's specialize in managable data amounts for now. The only
 * re-entrant part would be pre-fetching the static assets. If the asset already exists,
 * there's no need to re-fetch it.
 */
class WP_Markdown_Importer extends WP_Stream_Importer {

    static public function create(
        $entity_iterator_factory,
        $options = []
    ) {
        if(!isset($options['local_markdown_assets_root'])) {
            _doing_it_wrong(__METHOD__, 'The markdown_assets_root option is required.', '__WP_VERSION__');
            return false;
        }
        if(!is_dir($options['local_markdown_assets_root'])) {
            _doing_it_wrong(__METHOD__, 'The markdown_assets_root option must point to a directory.', '__WP_VERSION__');
            return false;
        }
        $options['local_markdown_assets_root'] = rtrim($options['local_markdown_assets_root'], '/');
        if(!isset($options['local_markdown_assets_url_prefix'])) {
            _doing_it_wrong(__METHOD__, 'The local_markdown_assets_url_prefix option is required.', '__WP_VERSION__');
            return null;
        }

        return parent::create($entity_iterator_factory, $options);
    }

    protected function enqueue_attachment_download(string $attachment_url) {
        /**
         * For Docusaurus docs, URLs starting with `@site` are referring
         * to local files. Let's convert them to file:// URLs.
         * 
         * Also, we cannot just compare the host to `@site` because, after parsing,
         * the host is actually just "site". The "@" symbol denotes an empty
         * username and is present in the URL string.
         */
        var_dump($attachment_url);
        if(str_starts_with($attachment_url, $this->options['local_markdown_assets_url_prefix'])) {
            // @TODO: Source the file from the current input stream if we can.
            //        This would allow stream-importing zipped Markdown and WXR directory
            //        structures.
            //        Maybe for v1 we could just support importing them from ZIP files
            //        that are already downloaded and available in a local directory just
            //        to avoid additional data transfer and the hurdle with implementing
            //        multiple range requests.
            $attachment_url = implode('', [
                'file://',
                $this->options['local_markdown_assets_root'],
                substr($attachment_url, strlen($this->options['local_markdown_assets_url_prefix']))
            ]);
        } else if(!WP_URL::canParse($attachment_url)) {
            /**
             * This is not an absolute URL, but it could be relative path.
             * 
             * If so, it can be either relative to the page URL or to the local directory
             * from which the imported data is being loaded.
             * 
             * Let's try treating it as a local path first.
             */
            $local_path = $this->options['local_markdown_assets_root'] . '/' . $attachment_url;
            if(file_exists($local_path)) {
                /**
                 * It seems to be a local path.
                 * We don't know that for sure, since we may accidentally have a local file
                 * with a coinciding name, but that's the assumption we're making.
                 *
                 * @TODO: Make the asset resolution strategy configurable by the API consumer.
                 */
                $attachment_url = 'file://' . $local_path;
            } else {
                /**
                 * If it's not a local path, let's treat it as a path that's relative to the
                 * source site URL. It may not be that at all, and that's fine. The request
                 * will fail and the user will get a chance to review it in the UI later on.
                 * 
                 * @TODO: save the failure info somewhere so the user can review it later.
                 */
                $attachment_url = $this->source_site_url . '/' . $attachment_url;
            }
        }
        return parent::enqueue_attachment_download($attachment_url);
    }

    /**
     * When processing Markdown, we want to download all the images
     * referenced in the image tags.
     *
     * @TODO: How can we process the videos?
     * @TODO: What other asset types are there?
     */
    protected function url_processor_matched_asset_url(WP_Block_Markup_Url_Processor $p) {
        return (
            $p->get_tag() === 'IMG' &&
            $p->get_inspected_attribute_name() === 'src'
        );
    }

}
