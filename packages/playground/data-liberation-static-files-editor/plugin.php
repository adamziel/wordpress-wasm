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

use WordPress\Filesystem\WP_Filesystem;

if ( ! defined( 'WP_STATIC_CONTENT_DIR' ) ) {
    define( 'WP_STATIC_CONTENT_DIR', WP_CONTENT_DIR . '/uploads/static-pages' );
}

if( ! defined( 'WP_LOCAL_FILE_POST_TYPE' )) {
    define( 'WP_LOCAL_FILE_POST_TYPE', 'local_file' );
}

if(isset($_GET['dump'])) {
    add_action('init', function() {
        WP_Static_Files_Editor_Plugin::import_static_pages();
    });
}

require_once __DIR__ . '/WP_Static_File_Sync.php';

class WP_Static_Files_Editor_Plugin {

    static private $fs;

    static private function get_fs() {
        if(!self::$fs) {
            // self::$fs = new WP_Filesystem( WP_STATIC_CONTENT_DIR );
            self::$fs = new WP_Git_Filesystem(
                new WP_Git_Client('https://github.com/WordPress/gutenberg'),
                '/docs/how-to-guides/data-basics'
            );
        }
        return self::$fs;
    }

    static public function initialize() {
        // Register hooks
        // $static_sync = new WP_Static_File_Sync( self::get_fs() );
        // $static_sync->initialize_sync();

        // register_activation_hook( __FILE__, array(self::class, 'import_static_pages') );

        add_action('init', function() {
            self::register_post_type();
        });

        // Register the admin page
        add_action('admin_menu', function() {
            // Get first post or create new one
            $posts = get_posts(array(
                'post_type' => WP_LOCAL_FILE_POST_TYPE,
                'posts_per_page' => 1,
                'orderby' => 'ID',
                'order' => 'ASC'
            ));

            if (empty($posts)) {
                // Create a new draft post if none exists
                $post_id = wp_insert_post(array(
                    'post_title' => 'My first note',
                    'post_type' => WP_LOCAL_FILE_POST_TYPE,
                    'post_status' => 'draft'
                ));
            } else {
                $post_id = $posts[0]->ID;
            }

            $edit_url = admin_url('post.php?post=' . $post_id . '&action=edit');

            add_menu_page(
                'Edit Local Files',
                'Edit Local Files',
                'manage_options',
                $edit_url, // Direct link to edit page
                '', // No callback needed
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
                    body: '.json_encode(WP_Static_Files_Editor_Plugin::get_files_tree_endpoint()).'
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

            register_rest_route('static-files-editor/v1', '/create-directory', array(
                'methods' => 'POST',
                'callback' => array(self::class, 'create_directory_endpoint'),
                'permission_callback' => function() {
                    return current_user_can('edit_posts');
                },
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
            if ($post && $post->post_type === 'local_file') {
                // Get the latest content from the database first
                $content = $post->post_content;
                
                // Then refresh from file if needed
                $new_content = self::refresh_post_from_local_file($post);
                if(!is_wp_error($new_content)) {
                    $content = $new_content;
                }
                return $content;
            }
            
            // Return original content for all other post types
            return $content;
        }, 10, 2);

        // Add filter for REST API responses
        add_filter('rest_prepare_local_file', function($response, $post, $request) {
            $new_content = self::refresh_post_from_local_file($post);
            if(!is_wp_error($new_content)) {
                $response->data['content']['raw'] = $new_content;
                $response->data['content']['rendered'] = '';
            }
            return $response;
        }, 10, 3);

        // Update the file after post is saved
        add_action('save_post_' . WP_LOCAL_FILE_POST_TYPE, function($post_id, $post, $update) {
            self::save_post_data_to_local_file($post);
        }, 10, 3);
    }

    static private $synchronizing = false;
    static private function acquire_synchronization_lock() {
        // Ignore auto-saves or revisions
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            // return false;
        }

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
                return;
            }

            $post_id = $post->ID;
            $fs = self::get_fs();
            $path = get_post_meta($post_id, 'local_file_path', true);
            if(!$fs->is_file($path)) {
                _doing_it_wrong(__METHOD__, 'File not found: ' . $path, '1.0.0');
                return;
            }
            $content = $fs->read_file($path);
            $extension = pathinfo($path, PATHINFO_EXTENSION);
            switch($extension) {
                case 'md':
                    $converter = new WP_Markdown_To_Blocks( $content );
                    break;
                case 'xhtml':
                    $converter = new WP_HTML_To_Blocks( WP_XML_Processor::create_from_string( $content ) );
                    break;
                case 'html':
                default:
                    $converter = new WP_HTML_To_Blocks( WP_HTML_Processor::create_fragment( $content ) );
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

    static private function save_post_data_to_local_file($post) {
        return;
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

            $fs = self::get_fs();
            $path = get_post_meta($post_id, 'local_file_path', true);
            $extension = pathinfo($path, PATHINFO_EXTENSION);
            $metadata = get_post_meta($post_id);

            // @TODO: Include specific post fields in the stored metadata
            // foreach(WP_Imported_Entity::POST_FIELDS as $field) {
            //     $metadata[$field] = get_post_field($field, $post_id);
            // }
            $content = get_post_field('post_content', $post_id);
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
            $fs->put_contents($path, $converter->get_result());
        } finally {
            self::release_synchronization_lock();
        }

    }

    static public function get_local_files_tree($subdirectory = '') {
        $tree = [];
        $fs = self::get_fs();
        
        $base_dir = $subdirectory ? $subdirectory : '/';
        self::build_local_file_tree_recursive($fs, $base_dir, $tree);
        
        return $tree;
    }
    
    static private function build_local_file_tree_recursive($fs, $dir, &$tree) {
        $items = $fs->ls($dir);
        if ($items === false) {
            return;
        }
        
        foreach ($items as $item) {
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
                self::build_local_file_tree_recursive($fs, $path, $tree[$last_index]['children']);
            } else {
                $tree[] = array(
                    'type' => 'file',
                    'name' => $item,
                );
            }
        }
    }

    /**
     * Import static pages from a disk, if one exists.
     */
    static public function import_static_pages() {
        if ( ! is_dir( WP_STATIC_CONTENT_DIR ) ) {
            return;
        }

        if ( defined('WP_IMPORTING') && WP_IMPORTING ) {
            return;
        }
        define('WP_IMPORTING', true);

        self::register_post_type();

        // Prevent ID conflicts
        self::reset_db_data();

        $importer = WP_Stream_Importer::create(
            function () {
                return new WP_Filesystem_Entity_Reader(
                    new WP_Filesystem(WP_STATIC_CONTENT_DIR),
                    array(
                        'post_type' => WP_LOCAL_FILE_POST_TYPE,
                    )
                );
            },
            array(),
            null
        );

        $import_session = WP_Import_Session::create(
            array(
                'data_source' => 'static_pages',
                'importer' => $importer,
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
                return;
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

    static public function create_directory_endpoint($request) {
        $path = $request->get_param('path');
        if (!$path) {
            return new WP_Error('missing_path', 'Directory path is required');
        }
        $path = '/' . ltrim($path, '/');

        $fs = self::get_fs();
        if (!$fs->mkdir($path)) {
            return new WP_Error('mkdir_failed', 'Failed to create directory');
        }

        return array('success' => true);
    }

}

WP_Static_Files_Editor_Plugin::initialize();
