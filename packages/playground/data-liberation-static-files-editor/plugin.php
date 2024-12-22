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

    static public function register_hooks() {
        $fs = new WP_Filesystem( WP_STATIC_CONTENT_DIR );
        $static_sync = new WP_Static_File_Sync( $fs );
        $static_sync->initialize_sync();

        register_activation_hook( __FILE__, array(self::class, 'import_static_pages') );

        add_action('init', function() {
            self::register_post_type();
        });

        add_filter('manage_local_file_posts_columns', function($columns) {
            $columns['local_file_path'] = 'Local File Path';
            return $columns;
        });

        add_action('manage_local_file_posts_custom_column', function($column_name, $post_id) use ($fs) {
            if ($column_name === 'local_file_path') {
                $local_file_path = get_post_meta($post_id, 'local_file_path', true);
                echo esc_html($local_file_path);
                if(!$fs->is_file($local_file_path)) {
                    echo ' <span style="color: red;">(missing)</span>';
                }
            }
        }, 10, 2);

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

}

WP_Static_Files_Editor_Plugin::register_hooks();
