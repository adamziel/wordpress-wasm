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

if(file_exists(__DIR__ . '/secrets.php')) {
    require_once __DIR__ . '/secrets.php';
}

class WP_Static_Files_Editor_Plugin {

    static private $fs;

    static private function get_fs() {
        if(!self::$fs) {
            $dot_git_path = WP_CONTENT_DIR . '/.static-pages.git';
            $local_fs = new WP_Local_Filesystem($dot_git_path);
            $repo = new WP_Git_Repository($local_fs);
            $repo->add_remote('origin', GIT_REPO_URL);
            $repo->set_ref_head('HEAD', 'refs/heads/' . GIT_BRANCH);
            $repo->set_config_value('user.name', GIT_USER_NAME);
            $repo->set_config_value('user.email', GIT_USER_EMAIL);
            self::$fs = new WP_Git_Filesystem(
                $repo,
                [
                    'root' => GIT_DIRECTORY_ROOT,
                    'auto_push' => true,
                ]
            );
        }
        return self::$fs;
    }

    static public function initialize() {
        // Register hooks
        register_activation_hook( __FILE__, array(self::class, 'import_static_pages') );

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

            register_rest_route('static-files-editor/v1', '/create-directory', array(
                'methods' => 'POST',
                'callback' => array(self::class, 'create_directory_endpoint'),
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
                if(!is_wp_error($new_content)) {
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
            if(!is_string($content)) {
                _doing_it_wrong(__METHOD__, 'File not found: ' . $path, '1.0.0');
                return;
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

    static private function save_post_data_to_local_file($post) {
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
            $metadata = [];
            foreach(['post_date_gmt', 'post_title', 'menu_order'] as $key) {
                $metadata[$key] = get_post_field($key, $post_id);
            }
            // @TODO: Also include actual post_meta. Which ones? All? The
            //        ones explicitly set by the user in the editor?

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
                    new WP_Local_Filesystem(WP_STATIC_CONTENT_DIR),
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

    static public function move_file_endpoint($request) {
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

        return array('success' => true);
    }

    static public function create_files_endpoint($request) {
        $path = $request->get_param('path');
        $nodes_json = $request->get_param('nodes');
        
        if(!$path) {
            $path = '/';
        }

        if (!$nodes_json) {
            return new WP_Error('invalid_tree', 'Invalid file tree structure');
        }

        $nodes = json_decode($nodes_json, true);
        if (!$nodes) {
            return new WP_Error('invalid_json', 'Invalid JSON structure');
        }

        $created_files = [];

        try {
            $fs = self::get_fs();
            foreach ($nodes as $node) {
                $result = self::process_node($node, $path, $fs, $request);
                if (is_wp_error($result)) {
                    return $result;
                }
                $created_files = array_merge($created_files, $result);
            }

            return array(
                'created_files' => $created_files
            );
        } catch (Exception $e) {
            return new WP_Error('creation_failed', $e->getMessage());
        }
    }

    static private function process_node($node, $parent_path, $fs, $request) {
        if (!isset($node['name']) || !isset($node['type'])) {
            return new WP_Error('invalid_node', 'Invalid node structure');
        }

        $path = rtrim($parent_path, '/') . '/' . ltrim($node['name'], '/');
        $created_files = [];

        if ($node['type'] === 'folder') {
            if (!$fs->mkdir($path)) {
                return new WP_Error('mkdir_failed', "Failed to create directory: $path");
            }
            
            if (!empty($node['children'])) {
                foreach ($node['children'] as $child) {
                    $result = self::process_node($child, $path, $fs, $request);
                    if (is_wp_error($result)) {
                        return $result;
                    }
                    $created_files = array_merge($created_files, $result);
                }
            }
        } else {
            $content = '';
            if (isset($node['content']) && is_string($node['content']) && strpos($node['content'], '@file:') === 0) {
                $file_key = substr($node['content'], 6);
                $uploaded_file = $request->get_file_params()[$file_key] ?? null;
                if ($uploaded_file && $uploaded_file['error'] === UPLOAD_ERR_OK) {
                    $content = file_get_contents($uploaded_file['tmp_name']);
                }
            }

            if (!$fs->put_contents($path, $content)) {
                return new WP_Error('write_failed', "Failed to write file: $path");
            }

            /*
            // @TODO: Should we create posts here?
            //        * We'll reindex the data later anyway and create those posts on demand.
            //        * ^ yes, but this means we don't have these posts in the database right after the upload.
            //        * But if we do create them, how do we know which files need a post, and which ones are
            //          images, videos, etc?
            $post_data = array(
                'post_title' => basename($path),
                'post_type' => WP_LOCAL_FILE_POST_TYPE,
                'post_status' => 'publish',
                'meta_input' => array(
                    'local_file_path' => $path
                )
            );
            $post_id = wp_insert_post($post_data);

            if (is_wp_error($post_id)) {
                return $post_id;
            }
            */

            $created_files[] = array(
                'path' => $path,
                // 'post_id' => $post_id
            );
        }

        return $created_files;
    }

    static public function delete_path_endpoint($request) {
        $path = $request->get_param('path');
        if (!$path) {
            return new WP_Error('missing_path', 'File path is required');
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

        return array('success' => true);
    }

}

WP_Static_Files_Editor_Plugin::initialize();
