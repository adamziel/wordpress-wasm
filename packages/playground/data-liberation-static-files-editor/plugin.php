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
        add_action('save_post_page', array(self::class, 'on_save_post'));
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
                return WP_Directory_Tree_Entity_Reader::create(
                    new WP_Filesystem(),
                    array (
                        'root_dir' => WP_STATIC_CONTENT_DIR,
                        'create_root_page' => true,
                        'first_post_id' => 2,
                        'allowed_extensions' => array( 'md' ),
                        'index_file_patterns' => array( '#^index\.md$#' ),
                        'markup_converter_factory' => function( $content ) {
                            return new WP_Markdown_To_Blocks( $content );
                        },
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
     * Recreate the entire file structure when any post is saved.
     * 
     * Why recreate?
     * 
     * It's easier to recreate the entire file structure than to keep track of
     * which files have been added, deleted, renamed and moved under
     * another parent, or changed via a direct SQL query.
     */
    static public function on_save_post($post_id) {
        // Prevent collisions between the initial create_db_pages_from_html_files call
        // process and the save_post_page hook.
        if (self::$importing) {
            return;
        }
        
        self::deltree(WP_STATIC_CONTENT_DIR);
        mkdir(WP_STATIC_CONTENT_DIR);
        self::save_db_pages_as_html(WP_STATIC_CONTENT_DIR);
    }

    static private function save_db_pages_as_html($path, $parent_id = 0) {
        if (!file_exists($path)) {
            mkdir($path, 0777, true);
        }

        $args = array(
            'post_type'      => 'page',
            'posts_per_page' => -1,
            'post_parent'    => $parent_id,
            'post_status'    => 'publish',
        );
        $pages = new WP_Query($args);

        if ($pages->have_posts()) {
            while ($pages->have_posts()) {
                $pages->the_post();
                $page_id = get_the_ID();
                $page = get_post($page_id);
                $title = sanitize_title(get_the_title());
                
                // $content = '<h1>' . esc_html(get_the_title()) . "</h1>\n\n" . get_the_content();

                $converter = new WP_Blocks_To_Markdown(
                    $page->post_content,
                    array(
                        'title' => get_the_title(),
                    )
                );
                $converter->convert();
                $content = $converter->get_markdown();

                $child_pages = get_pages(array('child_of' => $page_id, 'post_type' => 'page'));

                if (!file_exists($path)) {
                    mkdir($path, 0777, true);
                }

                if (!empty($child_pages)) {
                    $new_parent = $path . '/' . $page->menu_order . '_' . $title;
                    if (!file_exists($new_parent)) {
                        mkdir($new_parent, 0777, true);
                    }
                    // file_put_contents($new_parent . '/index.html', $content);
                    file_put_contents($new_parent . '/index.md', $content);
                    self::save_db_pages_as_html($new_parent, $page_id);
                } else {
                    // file_put_contents($path . '/' . $page->menu_order . '_' . $title . '.html', $content);
                    file_put_contents($path . '/' . $page->menu_order . '_' . $title . '.md', $content);
                }
            }
        }
        wp_reset_postdata();
    }

    static private function deltree($path) {
        if (!file_exists($path)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $file) {
            /** @var SplFileInfo $file */
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else if($file->isFile()) {
                unlink($file->getRealPath());
            }
        }

        rmdir($path);
    }
}

WP_Static_Files_Editor_Plugin::register_hooks();
