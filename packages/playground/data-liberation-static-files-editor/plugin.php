<?php
/**
 * Plugin Name: Data Liberation – WordPress Static files editor
 */

use WordPress\Filesystem\WP_Filesystem;

if ( ! defined( 'WP_STATIC_CONTENT_DIR' ) ) {
    define( 'WP_STATIC_CONTENT_DIR', WP_CONTENT_DIR . '/uploads/static-pages' );
}

if(isset($_GET['dump'])) {
    add_action('init', function() {
        WP_Static_Files_Editor_Plugin::import_static_pages();
    });
}

class WP_Static_Files_Editor_Plugin {

    private static $importing = false;

    static public function register_hooks() {
        register_activation_hook( __FILE__, array(self::class, 'import_static_pages') );
        add_action('save_post', array(self::class, 'on_save_post'), 10, 3);
        add_action('before_delete_post', array(self::class, 'on_delete_post'));
    }

    /**
     * Import static pages from a disk, if one exists.
     */
    static public function import_static_pages() {
        if ( ! is_dir( WP_STATIC_CONTENT_DIR ) ) {
            return;
        }

        if ( self::$importing ) {
            return;
        }
        self::$importing = true;

        // Prevent ID conflicts
        self::reset_db_data();

        $importer = WP_Stream_Importer::create(
            function () {
                return new WP_Filesystem_Entity_Reader(
                    new WP_Filesystem(),
                    WP_STATIC_CONTENT_DIR
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

        self::$importing = false;
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

    /**
     * Handle post deletion by removing associated files and directories
     */
    static public function on_delete_post($post_id) {
        if (get_post_type($post_id) !== 'page') {
            return;
        }

        $source_path = get_post_meta($post_id, 'source_path_relative', true);
        if (!empty($source_path)) {
            $full_path = WP_STATIC_CONTENT_DIR . '/' . $source_path;
            $dir_path = dirname($full_path);
            
            // Delete the file
            if (file_exists($full_path)) {
                unlink($full_path);
            }
            
            // If this was a parent page with index.md, delete its directory too
            if (basename($full_path) === 'index.md') {
                self::deltree($dir_path);
            }
        }
    }

    /**
     * Recursively delete a directory and its contents
     */
    static private function deltree($dir) {
        $files = array_diff(scandir($dir), array('.','..'));
        foreach ($files as $file) {
            $path = "$dir/$file";
            is_dir($path) ? self::deltree($path) : unlink($path);
        }
        return rmdir($dir);
    }

    /**
     * Handle post saving and file organization
     */
    static public function on_save_post($post_id, $post, $update) {
        if (self::$importing || get_post_type($post_id) !== 'page') {
            return;
        }

        $parent_id = wp_get_post_parent_id($post_id);
        $content_converter = get_post_meta($post_id, 'content_converter', true) ?: 'md';
        $old_relative_path = get_post_meta($post_id, 'source_path_relative', true);
        
        $new_relative_path = $old_relative_path;
        if (empty($new_relative_path)) {
            $new_relative_path = sanitize_title($post->post_title) . '.' . $content_converter;
        }

        // Determine the new relative path
        if ($parent_id) {
            $parent_file_path = get_post_meta($parent_id, 'source_path_relative', true);

            // If parent file exists but isn't in a subdirectory, move it
            if(!file_exists(WP_STATIC_CONTENT_DIR . '/' . $parent_file_path)) {
                // @TODO: error handling. Maybe just backfill the paths?
                throw new Exception('Parent file does not exist: ' . WP_STATIC_CONTENT_DIR . '/' . $parent_file_path);
            }

            $parent_filename = basename($parent_file_path, '.md');
            if('index' !== $parent_filename) {
                $swap_file = $parent_file_path . '.swap';
                rename(
                    WP_STATIC_CONTENT_DIR . '/' . $parent_file_path,
                    WP_STATIC_CONTENT_DIR . '/' . $swap_file
                );

                $parent_dir = dirname($parent_file_path) . '/' . basename($parent_file_path, '.md');
                mkdir(WP_STATIC_CONTENT_DIR . '/' . $parent_dir, 0777, true);

                $parent_file_path = $parent_dir . '/index.md';
                rename(
                    WP_STATIC_CONTENT_DIR . '/' . $swap_file,
                    WP_STATIC_CONTENT_DIR . '/' . $parent_file_path
                );
                update_post_meta($parent_id, 'source_path_relative', $parent_file_path);
                
                $new_relative_path = $parent_dir . '/' . $new_relative_path;
            }
        }

        // Handle file moves for existing pages
        if (!empty($old_relative_path) && $old_relative_path !== $new_relative_path) {
            $old_path = WP_STATIC_CONTENT_DIR . '/' . $old_relative_path;
            $new_path = WP_STATIC_CONTENT_DIR . '/' . $new_relative_path;
            
            // Create parent directory if needed
            if (!is_dir(dirname($new_path))) {
                mkdir(dirname($new_path), 0777, true);
            }

            // Move the file/directory
            if (file_exists($old_path)) {
                rename($old_path, $new_path);
            }
            
            // Clean up empty directories
            // $old_dir = dirname($old_path);
            // if (is_dir($old_dir) && !(new \FilesystemIterator($old_dir))->valid()) {
            //     rmdir($old_dir);
            // }
            // Update the source path meta
        }

        update_post_meta($post_id, 'source_path_relative', $new_relative_path);
        // Save the content
        self::save_page_content($post_id);
    }

    /**
     * Save a single page's content to file
     */
    static private function save_page_content($page_id) {
        $page = get_post($page_id);
        $content_converter = get_post_meta($page_id, 'content_converter', true) ?: 'md';
        
        $title_block = (
            WP_Import_Utils::block_opener('heading', array('level' => 1)) . 
            '<h1>' . esc_html(get_the_title($page_id)) . '</h1>' . 
            WP_Import_Utils::block_closer('heading')
        );
        $block_markup = $title_block . $page->post_content;

        switch($content_converter) {
            case 'html':
            case 'xhtml':
                // @TODO: Implement a Blocks to HTML converter – OR just render
                //        the blocks.
                break;
            case 'md':
            default:
                $converter = new WP_Blocks_To_Markdown(
                    $block_markup,
                    array(
                        'title' => get_the_title($page_id),
                    )
                );
                if(false === $converter->convert()) {
                    // @TODO: error handling.
                    return;
                }
                $content = $converter->get_result();
                break;
        }

        $source_path_relative = get_post_meta($page_id, 'source_path_relative', true);
        if($source_path_relative) {
            $source_file_path = WP_STATIC_CONTENT_DIR . '/' . $source_path_relative;

            // Ensure directory exists
            if (!is_dir(dirname($source_file_path))) {
                mkdir(dirname($source_file_path), 0777, true);
            }

            // Save the content
            file_put_contents($source_file_path, $content);
        }
    }
}

WP_Static_Files_Editor_Plugin::register_hooks();
