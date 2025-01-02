<?php
/**
 * Plugin Name: Data Liberation – WordPress Static files editor
 * 
 * @TODO: Page metadata editor in Gutenberg
 * @TODO: A special "filename" field in wp-admin and in Gutenberg. Either source from the page title or
 *        pin it to a specific, user-defined value.
 * @TODO: Choose the local file storage format (MD, HTML, etc.) in Gutenberg page options.
 * @TODO: HTML, XHTML, and Blocks renderers
 * @TODO: Integrity check – is the database still in sync with the files?
 *        If not, what should we do?
 *        * Overwrite the database with the local files? This is a local files editor after all.
 *        * Display a warning in wp-admin and let the user decide what to do?
 * @TODO: Consider tricky scenarios – moving a parent to trash and then restoring it.
 * @TODO: Consider using hierarchical taxonomy to model the directory/file structure – instead of
 *        using the post_parent field. Could be more flexible (no need for index.md files) and require
 *        less complex operations in the code (no need to update a subtree of posts when moving a post,
 *        no need to periodically "flatten" the parent directory).
 * @TODO: Maybe use Playground's FilePickerTree React component? Or re-implement it with interactivity API?
 */

use WordPress\Filesystem\WP_Local_Filesystem;
use WordPress\Filesystem\WP_Filesystem_Visitor;
use WordPress\Filesystem\WP_Uploaded_Directory_Tree_Filesystem;

if ( ! defined( 'WP_STATIC_PAGES_DIR' ) ) {
    define( 'WP_STATIC_PAGES_DIR', WP_CONTENT_DIR . '/uploads/static-pages' );
}

if ( ! defined( 'WP_STATIC_MEDIA_DIR' ) ) {
    define( 'WP_STATIC_MEDIA_DIR', WP_STATIC_PAGES_DIR . '/media' );
}

if( ! defined( 'WP_LOCAL_FILE_POST_TYPE' )) {
    define( 'WP_LOCAL_FILE_POST_TYPE', 'local_file' );
}

if( ! defined( 'WP_AUTOSAVES_DIRECTORY' )) {
    define( 'WP_AUTOSAVES_DIRECTORY', '.autosaves' );
}

if(isset($_GET['dump'])) {
    add_action('init', function() {
        WP_Static_Files_Editor_Plugin::import_static_pages();
    });
}

if(file_exists(__DIR__ . '/secrets.php')) {
    require_once __DIR__ . '/secrets.php';
}

class WP_Static_Files_Editor_Plugin {

    static private $fs;
    static private $client;

    static private function get_fs() {
        if(!self::$fs) {
            if(!is_dir(WP_STATIC_PAGES_DIR)) {
                mkdir(WP_STATIC_PAGES_DIR, 0777, true);
            }
            $local_fs = new WP_Local_Filesystem(WP_STATIC_PAGES_DIR);
            $repo = new WP_Git_Repository($local_fs);
            $repo->add_remote('origin', GIT_REPO_URL);
            $repo->set_ref_head('HEAD', 'refs/heads/' . GIT_BRANCH);
            $repo->set_config_value('user.name', GIT_USER_NAME);
            $repo->set_config_value('user.email', GIT_USER_EMAIL);

            self::$client = new WP_Git_Client($repo);
            
            // Only force pull at most once every 10 minutes
            $last_pull_time = get_transient('wp_git_last_pull_time');
            if (!$last_pull_time) {
                if (false === self::$client->force_pull()) {
                    _doing_it_wrong(__METHOD__, 'Failed to pull from remote repository', '1.0.0');
                }
                set_transient('wp_git_last_pull_time', time(), 10 * MINUTE_IN_SECONDS);
            }

            self::$fs = new WP_Git_Filesystem(
                $repo,
                [
                    'root' => GIT_DIRECTORY_ROOT,
                    'auto_push' => true,
                    'client' => self::$client,
                ]
            );
            if(!self::$fs->is_dir('/' . WP_AUTOSAVES_DIRECTORY)) {
                self::$fs->mkdir('/' . WP_AUTOSAVES_DIRECTORY);
            }
        }
        return self::$fs;
    }

    static public function menu_item_callback() {
        // Get first post or create new one
        $posts = get_posts(array(
            'post_type' => WP_LOCAL_FILE_POST_TYPE,
            'posts_per_page' => 2,
            'orderby' => 'ID',
            'order' => 'ASC',
        ));

        if (empty($posts)) {
            try {
                if(!self::acquire_synchronization_lock()) {
                    die('There are no local files yet and we could not acquire a synchronization lock to create one.');
                }
                // Create a new draft post if none exists
                $post_id = wp_insert_post(array(
                    'post_title' => 'My first note',
                    'post_type' => WP_LOCAL_FILE_POST_TYPE,
                    'post_status' => 'publish',
                    'meta_input' => array(
                        'local_file_path' => '/my-first-note.md',
                    ),
                ));
            } finally {
                self::release_synchronization_lock();
            }
        } else {
            // Look for the first post that's not the default "my-first-note.md"
            $post_id = null;
            foreach ($posts as $post) {
                $path = get_post_meta($post->ID, 'local_file_path', true);
                if ($path !== '/my-first-note.md') {
                    $post_id = $post->ID;
                    break;
                }
            }
            // Fallback to first post if no other found
            if ($post_id === null) {
                $post_id = $posts[0]->ID;
            }
        }

        $edit_url = admin_url('post.php?post=' . $post_id . '&action=edit');
        wp_redirect($edit_url);
        exit;
    }

    static public function initialize() {
        // Register hooks
        register_activation_hook( __FILE__, array(self::class, 'import_static_pages') );

        add_action('init', function() {
            self::get_fs();
            self::register_post_type();

            // Redirect menu page to custom route
            global $pagenow;
            if ($pagenow === 'admin.php' && isset($_GET['page']) && $_GET['page'] === 'static_files_editor') {
                self::menu_item_callback();
            }
        });

        // Register the admin page
        add_action('admin_menu', function() {
            add_menu_page(
                'Static Files Editor',
                'Static Files Editor',
                'manage_options',
                'static_files_editor',
                function() {
                    // No callback needed, we're handling this in the init hook
                    // to redirect before any HTML is output.
                },
                'dashicons-media-text',
                30
            );
        });

        add_action('admin_enqueue_scripts', function($hook) {
            $screen = get_current_screen();
            $enqueue_script = $screen && $screen->base === 'post' && $screen->post_type === WP_LOCAL_FILE_POST_TYPE;
            if (!$enqueue_script) {
                return;
            }

            wp_register_script(
                'static-files-editor',
                plugins_url('ui/build/index.tsx.js', __FILE__),
                array('wp-element', 'wp-components', 'wp-block-editor', 'wp-edit-post', 'wp-plugins', 'wp-editor', 'wp-api-fetch'),
                '1.0.0',
                true
            );

            wp_add_inline_script(
                'static-files-editor',
                'window.WP_LOCAL_FILE_POST_TYPE = ' . json_encode(WP_LOCAL_FILE_POST_TYPE) . ';',
                'before'
            );

            wp_register_style(
                'static-files-editor',
                plugins_url('ui/build/style-index.tsx.css', __FILE__),
                array('wp-components', 'wp-block-editor', 'wp-edit-post'),
                '1.0.0'
            );
            
            wp_enqueue_script('static-files-editor');
            wp_enqueue_style('static-files-editor');

            // Preload the initial files tree
            wp_add_inline_script('wp-api-fetch', 'wp.apiFetch.use(wp.apiFetch.createPreloadingMiddleware({
                "/static-files-editor/v1/get-files-tree": {
                    body: '.json_encode(WP_Static_Files_Editor_Plugin::get_files_tree_endpoint()).',
                }
            }));', 'after');
        });

        add_action('rest_api_init', function() {
            register_rest_route('static-files-editor/v1', '/get-or-create-post-for-file', array(
                'methods' => 'POST',
                'callback' => array(self::class, 'get_or_create_post_for_file'),
                'permission_callback' => function() {
                    return current_user_can('edit_posts');
                },
            ));

            register_rest_route('static-files-editor/v1', '/get-files-tree', array(
                'methods' => 'GET',
                'callback' => array(self::class, 'get_files_tree_endpoint'),
                'permission_callback' => function() {
                    return current_user_can('edit_posts');
                },
            ));

            register_rest_route('static-files-editor/v1', '/move-file', array(
                'methods' => 'POST',
                'callback' => array(self::class, 'move_file_endpoint'),
                'permission_callback' => function() {
                    return current_user_can('edit_posts');
                },
            ));

            register_rest_route('static-files-editor/v1', '/create-files', array(
                'methods' => 'POST',
                'callback' => array(self::class, 'create_files_endpoint'),
                'permission_callback' => function() {
                    return current_user_can('edit_posts');
                },
            ));

            register_rest_route('static-files-editor/v1', '/delete-path', array(
                'methods' => 'POST',
                'callback' => array(self::class, 'delete_path_endpoint'),
                'permission_callback' => function() {
                    return current_user_can('edit_posts');
                },
            ));

            register_rest_route('static-files-editor/v1', '/download-file', array(
                'methods' => 'GET',
                'callback' => array(self::class, 'download_file_endpoint'),
                'permission_callback' => function() {
                    return current_user_can('edit_posts');
                },
                'args' => array(
                    'path' => array(
                        'required' => true,
                        'type' => 'string',
                        'sanitize_callback' => function($param) {
                            return '/' . ltrim($param, '/');
                        }
                    )
                )
            ));
        });

        // @TODO: the_content and rest_prepare_local_file filters run twice for REST API requests.
        //        find a way of only running them once.

        // Add the filter for 'the_content'
        add_filter('the_content', function($content, $post = null) {
            // If no post is provided, try to get it from the global scope
            if (!$post) {
                global $post;
            }
            
            // Check if this post is of type "local_file"
            if ($post && $post->post_type === WP_LOCAL_FILE_POST_TYPE) {
                // Get the latest content from the database first
                $content = $post->post_content;
                
                // Then refresh from file if needed
                $new_content = self::refresh_post_from_local_file($post);
                if(false !== $new_content && !is_wp_error($new_content)) {
                    $content = $new_content;
                }
                return $content;
            }
            
            // Return original content for all other post types
            return $content;
        }, 10, 2);

        // Add filter for REST API responses
        add_filter('rest_prepare_' . WP_LOCAL_FILE_POST_TYPE, function($response, $post, $request) {
            $new_content = self::refresh_post_from_local_file($post);
            if(!is_wp_error($new_content)) {
                $response->data['content']['raw'] = $new_content;
                $response->data['content']['rendered'] = '';
            }
            return $response;
        }, 10, 3);

        // Delete the associated file when a post is deleted
        // @TODO: Rethink this. We have a separate endpoint for deleting an entire path.
        //        Do we need a separate hook at all?
        // add_action('before_delete_post', function($post_id) {
        //     $post = get_post($post_id);
        //     if ($post && $post->post_type === WP_LOCAL_FILE_POST_TYPE) {
        //         $fs = self::get_fs();
        //         $path = get_post_meta($post_id, 'local_file_path', true);
        //         if ($path && $fs->is_file($path)) {
        //             $fs->rm($path);
        //         }
        //     }
        // });

        // Update the file after post is saved
        add_action('save_post_' . WP_LOCAL_FILE_POST_TYPE, function($post_id, $post, $update) {
            try {
                if(!self::acquire_synchronization_lock()) {
                    return;
                }
                $post_id = $post->ID;
                if (
                    empty($post->ID) ||
                    $post->post_status !== 'publish' ||
                    $post->post_type !== WP_LOCAL_FILE_POST_TYPE
                ) {
                    return;
                }

                $path = get_post_meta($post_id, 'local_file_path', true);
                $content = self::convert_post_to_string($path, $post);

                $fs = self::get_fs();
                $fs->put_contents($path, $content, [
                    'message' => 'User saved ' . $post->post_title,
                ]);
            } finally {
                self::release_synchronization_lock();
            }
        }, 10, 3);

        // Also update file when autosave occurs
        add_action('wp_creating_autosave', function($autosave) {
            try {
                if(!self::acquire_synchronization_lock()) {
                    return;
                }
                $autosave = (object)$autosave;
                if (
                    empty($autosave->ID) ||
                    $autosave->post_status !== 'inherit' ||
                    $autosave->post_type !== 'revision'
                ) {
                    return;
                }
                $parent_post = get_post($autosave->post_parent);
                if ($parent_post->post_type !== WP_LOCAL_FILE_POST_TYPE) {
                    return;
                }

                $path = wp_join_paths(
                    '/' . WP_AUTOSAVES_DIRECTORY . '/',
                    get_post_meta($parent_post->ID, 'local_file_path', true)
                );
                $fs = self::get_fs();
                $content = self::convert_post_to_string($path, $autosave);
                $fs->put_contents($path, $content, [
                    'amend' => true,
                ]);
            } finally {
                self::release_synchronization_lock();
            }
        }, 10, 1);
    }

    static public function download_file_endpoint($request) {
        $path = $request->get_param('path');
        $fs = self::get_fs();

        if($fs->is_dir($path)) {
            return new WP_Error('file_error', 'Directory download is not supported yet.');
        }

        // Get file info
        $filename = basename($path);
        $filesize = $fs->get_streamed_file_length($path);

        // Set headers for file download
        header('Content-Type: application/octet-stream');
        header("Content-Disposition: attachment; filename=UTF-8''" . urlencode($filename));
        header('Content-Length: ' . $filesize);
        header('Cache-Control: no-cache');

        // Stream file contents
        if(!$fs->open_read_stream($path)) {
            return new WP_Error('file_error', 'Could not read file');
        }

        echo $fs->get_file_chunk();

        while($fs->next_file_chunk()) {
            echo $fs->get_file_chunk();
        }

        $fs->close_read_stream();

        if(!$fs->get_last_error()) {
            exit;
        }

        return new WP_Error('file_error', 'Could not read file');
    }

    static private $synchronizing = false;
    static private function acquire_synchronization_lock() {
        // Skip if in maintenance mode
        if (wp_is_maintenance_mode()) {
            return false;
        }

        if (defined('WP_IMPORTING') && WP_IMPORTING) {
            return false;
        }

        // @TODO: Synchronize between threads
        if(self::$synchronizing) {
            return false;
        }
        self::$synchronizing = true;
        return true;
    }

    static private function release_synchronization_lock() {
        self::$synchronizing = false;
    }

    static private function refresh_post_from_local_file($post) {
        try {
            if(!self::acquire_synchronization_lock()) {
                return false;
            }

            $post_id = $post->ID;
            $fs = self::get_fs();
            $path = get_post_meta($post_id, 'local_file_path', true);
            if(!$fs->is_file($path)) {
                // @TODO: Log the error outside of this method.
                //        This happens naturally when the underlying file is deleted.
                //        It's annoying to keep seeing this error when developing
                //        the plugin so I'm commenting it out.
                //
                //        Really, this may not even be an error. The caller must
                //        decide whether to log the error or handle the failure
                //        gracefully.
                //
                //        This method only needs to bubble the error information up,
                //        e.g. by throwing, returning WP_Error, or setting self::$last_error.
                return false;
            }
            $content = $fs->get_contents($path);
            if(!is_string($content)) {
                // @TODO: Ditto the previous comment.
                return false;
            }
            $extension = pathinfo($path, PATHINFO_EXTENSION);
            switch($extension) {
                case 'xhtml':
                    $converter = new WP_HTML_To_Blocks( WP_XML_Processor::create_from_string( $content ) );
                    break;
                case 'html':
                    $converter = new WP_HTML_To_Blocks( WP_HTML_Processor::create_fragment( $content ) );
                    break;
                case 'md':
                default:
                    $converter = new WP_Markdown_To_Blocks( $content );
                    break;
            }
            $converter->convert();

            $metadata = [];
            foreach($converter->get_all_metadata() as $key => $value) {
                $metadata[$key] = $value[0];
            }
            $new_content = $converter->get_block_markup();

            $updated = wp_update_post(array(
                'ID' => $post_id,
                'post_content' => $new_content,
                'post_title' => $metadata['post_title'] ?? '',
                'post_date_gmt' => $metadata['post_date_gmt'] ?? '',
                'menu_order' => $metadata['menu_order'] ?? '',
                // 'meta_input' => $metadata,
            ));
            if(is_wp_error($updated)) {
                return $updated;
            }

            return $new_content;            
        } finally {
            self::release_synchronization_lock();
        }
    }

    static private function convert_post_to_string($path, $post) {
        $post_id = $post->ID;

        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $metadata = [];
        foreach(['post_date_gmt', 'post_title', 'menu_order'] as $key) {
            $metadata[$key] = get_post_field($key, $post->ID);
        }
        // @TODO: Also include actual post_meta. Which ones? All? The
        //        ones explicitly set by the user in the editor?

        $content = get_post_field('post_content', $post_id);
        $content = self::unwordpressify_static_assets_urls($content);

        switch($extension) {
            // @TODO: Add support for HTML and XHTML
            case 'html':
            case 'xhtml':
            case 'md':
            default:
                $converter = new WP_Blocks_To_Markdown( $content, $metadata );
                break;
        }
        $converter->convert();
        return $converter->get_result();
    }

    /**
     * Convert references to files served via download_file_endpoint
     * to an absolute path referring to the corresponding static files
     * in the local filesystem.
     */
    static private function unwordpressify_static_assets_urls($content) {
        $site_url = WP_URL::parse(get_site_url());
        $expected_endpoint_path = '/wp-json/static-files-editor/v1/download-file';
        $p = WP_Block_Markup_Url_Processor::create_from_html($content, $site_url);
        while($p->next_url()) {
            $url = $p->get_parsed_url();
            if(!is_child_url_of($url, get_site_url())) {
                continue;
            }

            // Account for sites with no nice permalink structure
            if($url->searchParams->has('rest_route')) {
                $url = WP_URL::parse($url->searchParams->get('rest_route'), $site_url);
            }

            // Naively check for the endpoint that serves the file.
            // WordPress can use a custom REST API prefix which this
            // check doesn't account for. It assumes the endpoint path
            // is unique enough to not conflict with other paths.
            //
            // It may need to be revisited if any conflicts arise in
            // the future.
            if(!str_ends_with($url->pathname, $expected_endpoint_path)) {
                continue;
            }

            // At this point we're certain the URL intends to download
            // a static file managed by this plugin.

            // Let's replace the URL in the content with the relative URL.
            $original_url = $url->searchParams->get('path');
            $p->set_raw_url($original_url);
        }

        return $p->get_updated_html();
    }

    static public function get_local_files_tree($subdirectory = '') {
        $tree = [];
        $fs = self::get_fs();
        
        // Get all file paths and post IDs in one query
        $file_posts = get_posts(array(
            'post_type' => WP_LOCAL_FILE_POST_TYPE,
            'meta_key' => 'local_file_path',
            'posts_per_page' => -1,
            'fields' => 'id=>meta'
        ));

        $path_to_post_id = array();
        foreach($file_posts as $post) {
            $file_path = get_post_meta($post->ID, 'local_file_path', true);
            if ($file_path) {
                $path_to_post_id[$file_path] = $post->ID;
            }
        }
        
        $base_dir = $subdirectory ? $subdirectory : '/';
        self::build_local_file_tree_recursive($fs, $base_dir, $tree, $path_to_post_id);
        
        return $tree;
    }
    
    static private function build_local_file_tree_recursive($fs, $dir, &$tree, $path_to_post_id) {
        $items = $fs->ls($dir);
        if ($items === false) {
            return;
        }
        
        foreach ($items as $item) {
            // Exclude the autosaves directory from the files tree
            if($dir === '/' && $item === WP_AUTOSAVES_DIRECTORY) {
                continue;
            }
            // Exclude the .gitkeep file from the files tree.
            // WP_Git_Filesystem::mkdir() creates an empty .gitkeep file in each created
            // directory since Git doesn't support empty directories.
            if($item === '.gitkeep') {
                continue;
            }

            $path = $dir === '/' ? "/$item" : "$dir/$item";
            
            if ($fs->is_dir($path)) {
                $node = array(
                    'type' => 'folder',
                    'name' => $item,
                    'children' => []
                );
                $tree[] = $node;
                
                // Recursively build children
                $last_index = count($tree) - 1;
                self::build_local_file_tree_recursive($fs, $path, $tree[$last_index]['children'], $path_to_post_id);
            } else {
                $node = array(
                    'type' => 'file',
                    'name' => $item,
                );

                if (isset($path_to_post_id[$path])) {
                    $node['post_id'] = $path_to_post_id[$path];
                }

                $tree[] = $node;
            }
        }
    }

    /**
     * Import static pages from a disk, if one exists.
     * 
     * @TODO: Error handling
     */
    static public function import_static_pages() {
        if ( ! is_dir( WP_STATIC_PAGES_DIR ) ) {
            return;
        }

        if ( defined('WP_IMPORTING') && WP_IMPORTING ) {
            return;
        }
        define('WP_IMPORTING', true);

        // Make sure the post type is registered even if we're
        // running before the init hook.
        self::register_post_type();

        // Prevent ID conflicts
        self::reset_db_data();

        self::do_import_static_pages();
    }

    static private function do_import_static_pages($options = array()) {
        $importer = WP_Stream_Importer::create(
            function () use ($options) {
                return new WP_Filesystem_Entity_Reader(
                    self::get_fs(),
                    array(
                        'post_type' => WP_LOCAL_FILE_POST_TYPE,
                        'post_tree_options' => $options['post_tree_options'] ?? array(),
                    )
                );
            }
        );

        $import_session = WP_Import_Session::create(
            array (
                'data_source' => 'static_pages'
            )
        );

        data_liberation_import_step( $import_session, $importer );
    }

    static private function register_post_type() {
        register_post_type(WP_LOCAL_FILE_POST_TYPE, array(
            'labels' => array(
                'name' => 'Local Files',
                'singular_name' => 'Local File',
                'add_new' => 'Add New',
                'add_new_item' => 'Add New Local File',
                'edit_item' => 'Edit Local File',
                'new_item' => 'New Local File',
                'view_item' => 'View Local File',
                'search_items' => 'Search Local Files',
                'not_found' => 'No local files found',
                'not_found_in_trash' => 'No local files found in Trash',
            ),
            'public' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'hierarchical' => true,
            'supports' => array(
                'title',
                'editor',
                'page-attributes',
                'revisions',
                'custom-fields'
            ),
            'has_archive' => false,
            'show_in_rest' => true,
        ));

        // Register the meta field for file paths
        register_post_meta(WP_LOCAL_FILE_POST_TYPE, 'local_file_path', array(
            'type' => 'string',
            'description' => 'Path to the local file',
            'single' => true,
            'show_in_rest' => true,
        ));
    }

    /**
     * Resets the database to a clean state.
     * 
     * @TODO: Make it work with MySQL, right now it uses SQLite-specific code.
     */
    static private function reset_db_data() {
        $GLOBALS['@pdo']->query('DELETE FROM wp_posts WHERE id > 0');
        $GLOBALS['@pdo']->query("UPDATE SQLITE_SEQUENCE SET SEQ=0 WHERE NAME='wp_posts'");
        
        $GLOBALS['@pdo']->query('DELETE FROM wp_postmeta WHERE post_id > 1');
        $GLOBALS['@pdo']->query("UPDATE SQLITE_SEQUENCE SET SEQ=20 WHERE NAME='wp_postmeta'");

        $GLOBALS['@pdo']->query('DELETE FROM wp_comments');
        $GLOBALS['@pdo']->query("UPDATE SQLITE_SEQUENCE SET SEQ=0 WHERE NAME='wp_comments'");

        $GLOBALS['@pdo']->query('DELETE FROM wp_commentmeta');
        $GLOBALS['@pdo']->query("UPDATE SQLITE_SEQUENCE SET SEQ=0 WHERE NAME='wp_commentmeta'");
    }

    static public function get_or_create_post_for_file($request) {
        try {   
            if(!self::acquire_synchronization_lock()) {
                return new WP_Error('synchronization_lock_failed', 'Failed to acquire synchronization lock');
            }

            $file_path = $request->get_param('path');
            $file_path = '/' . ltrim($file_path, '/');
            $create_file = $request->get_param('create_file');
                
            if (!$file_path) {
                return new WP_Error('missing_path', 'File path is required');
            }

            // Create the file if requested and it doesn't exist
            $fs = self::get_fs();
            if ($create_file) {
                if (!$fs->put_contents($file_path, '')) {
                    return new WP_Error('file_creation_failed', 'Failed to create file');
                }
            }

            // Check if a post already exists for this file path
            $existing_posts = get_posts(array(
                'post_type' => WP_LOCAL_FILE_POST_TYPE,
                'meta_key' => 'local_file_path',
                'meta_value' => $file_path,
                'posts_per_page' => 1
            ));

            if (!empty($existing_posts)) {
                // Update existing post
                $post_data = array(
                    'ID' => $existing_posts[0]->ID,
                    'post_content' => ''
                );
                $post_id = wp_update_post($post_data);
            } else {
                // Create new post
                $post_data = array(
                    'post_title' => basename($file_path),
                    'post_type' => WP_LOCAL_FILE_POST_TYPE,
                    'post_status' => 'publish',
                    'post_content' => '',
                    'meta_input' => array(
                        'local_file_path' => $file_path
                    )
                );
                $post_id = wp_insert_post($post_data);
            }

            if (is_wp_error($post_id)) {
                return $post_id;
            }

        } finally {
            self::release_synchronization_lock();
        }

        $refreshed_post = self::refresh_post_from_local_file(get_post($post_id));
        if (is_wp_error($refreshed_post)) {
            return $refreshed_post;
        }

        return array(
            'post_id' => $post_id
        );
    }

    static public function get_files_tree_endpoint() {
        return self::get_local_files_tree();
    }

    static public function move_file_endpoint($request) {
        try {
            if(!self::acquire_synchronization_lock()) {
                return;
            }
            $from_path = $request->get_param('fromPath');
            $to_path = $request->get_param('toPath');
            
            if (!$from_path || !$to_path) {
                return new WP_Error('missing_path', 'Both source and target paths are required');
            }

            // Find and update associated post
            $existing_posts = get_posts(array(
                'post_type' => WP_LOCAL_FILE_POST_TYPE,
                'meta_key' => 'local_file_path',
                'meta_value' => $from_path,
                'posts_per_page' => 1
            ));

            $fs = self::get_fs();
            if (!$fs->rename($from_path, $to_path)) {
                return new WP_Error('move_failed', 'Failed to move file');
            }

            if (!empty($existing_posts)) {
                update_post_meta($existing_posts[0]->ID, 'local_file_path', $to_path);
                wp_update_post(array(
                    'ID' => $existing_posts[0]->ID,
                    'post_title' => basename($to_path)
                ));
            }

            // Pull new changes from the remote repository after
            // performing a write operation.
            self::$client->force_pull();

            return array('success' => true);
        } finally {
            self::release_synchronization_lock();
        }
    }

    /**
     * Imports files from the HTTP request into WordPress.
     *
     * This method:
     * * Creates the uploaded files in the filesystem managed by this plugin.
     * * Imports the uploaded files into WordPress as posts and attachments.
     *
     * @TODO: Rethink the attachments handling. Right now, we're creating two copies
     *        of each static asset. One in the managed filesystem (which could be a Git repo)
     *        and one in the WordPress uploads directory. Perhaps this is the way to go,
     *        but let's have a discussion about it.
     */
    static public function create_files_endpoint($request) {
        try {
            if(!self::acquire_synchronization_lock()) {
                return;
            }
            $uploaded_fs = WP_Uploaded_Directory_Tree_Filesystem::create($request, 'nodes');

            // Copy the uploaded files to the main filesystem
            $main_fs = self::get_fs();
            $create_in_dir = wp_canonicalize_path($request->get_param('path'));
            $uploaded_fs->copy('/', $create_in_dir, [
                'recursive' => true,
                'to_fs' => $main_fs,
            ]);

            // Import the uploaded files into WordPress
            $parent_id = null;
            if($create_in_dir) {
                $parent_post = get_posts(array(
                    'post_type' => WP_LOCAL_FILE_POST_TYPE,
                    'meta_key' => 'local_file_path',
                    'meta_value' => $create_in_dir,
                    'posts_per_page' => 1
                ));
                if(!empty($parent_post)) {
                    $parent_id = $parent_post[0]->ID;
                }
            }

            $importer = WP_Stream_Importer::create(
                function () use ($parent_id, $uploaded_fs) {
                    return new WP_Filesystem_Entity_Reader(
                        $uploaded_fs,
                        array(
                            'post_type' => WP_LOCAL_FILE_POST_TYPE,
                            'post_tree_options' => array(
                                'root_parent_id' => $parent_id,
                                'create_index_pages' => false,
                            ),
                        )
                    );
                },
                array(
                    'attachment_downloader_options' => array(
                        'source_from_filesystem' => $uploaded_fs,
                    ),
                )
            );
            
            $import_session = WP_Import_Session::create(
                array (
                    'data_source' => 'static_pages'
                )
            );

            $result = data_liberation_import_step( $import_session, $importer );
            if(is_wp_error($result)) {
                return $result;
            }

            /**
             * @TODO: A method such as $import_session->get_imported_entities()
             *        that iterates over the imported entities would be highly
             *        useful here. We don't have one, so we need the clunky
             *        inference below to get the imported posts.
             */
            $created_files = [];
            $visitor = new WP_Filesystem_Visitor($uploaded_fs);
            while($visitor->next()) {
                $event = $visitor->get_event();
                if(!$event->is_entering()) {
                    continue;
                }
                foreach($event->files as $file) {
                    $created_path = wp_join_paths($create_in_dir, $event->dir, $file);
                    $created_post = get_posts(array(
                        'post_type' => WP_LOCAL_FILE_POST_TYPE,
                        'meta_key' => 'local_file_path',
                        'meta_value' => $created_path,
                        'posts_per_page' => 1
                    ));
                    $created_files[] = array(
                        'path' => $created_path,
                        'post_id' => $created_post ? $created_post[0]->ID : null
                    );
                }
            }

            // Pull new changes from the remote repository after
            // performing a write operation.
            self::$client->force_pull();

            return array(
                'created_files' => $created_files
            );
        } finally {
            self::release_synchronization_lock();
        }
    }

    static public function delete_path_endpoint($request) {
        $path = $request->get_param('path');
        if (!$path) {
            return new WP_Error('missing_path', 'File path is required');
        }

        try {
            if(!self::acquire_synchronization_lock()) {
                return new WP_Error('synchronization_lock_failed', 'Failed to acquire synchronization lock');
            }
            // Find and delete associated post
            $existing_posts = get_posts(array(
                'post_type' => WP_LOCAL_FILE_POST_TYPE,
                'meta_key' => 'local_file_path',
                'meta_value' => $path,
                'posts_per_page' => 1
            ));

            if (!empty($existing_posts)) {
                wp_delete_post($existing_posts[0]->ID, true);
            }

            // Delete the actual file
            $fs = self::get_fs();
            if($fs->is_dir($path)) {
                if (!$fs->rmdir($path, ['recursive' => true])) {
                    return new WP_Error('delete_failed', 'Failed to delete directory');
                }
            } else {
                if (!$fs->rm($path)) {
                    return new WP_Error('delete_failed', 'Failed to delete file');
                }
            }

            // Pull new changes from the remote repository after
            // performing a write operation.
            self::$client->force_pull();

            return array('success' => true);
        } finally {
            self::release_synchronization_lock();
        }
    }

}

WP_Static_Files_Editor_Plugin::initialize();
