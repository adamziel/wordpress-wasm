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
        add_action('save_post', array(self::class, 'on_save_post'));
        add_action('init', function() {
            $converter = new WP_Blocks_To_Markdown(<<<HTML
<!-- wp:heading {"level":1} -->
<h1>WordPress 6.8 was released</h1>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Last week, WordPress 6.8 <b>was released</b>. This release includes a new default theme, a new block editor experience, and a new block library. It also includes a new block editor experience, and a new block library.</p>
<!-- /wp:paragraph -->

<!-- wp:list -->
<ul>
    <!-- wp:list-item -->
    <li>Major Features
        <!-- wp:list -->
        <ul>
            <!-- wp:list-item -->
            <li>Block Editor Updates
                <!-- wp:list -->
                <ul>
                    <!-- wp:list-item -->
                    <li>New <code>block patterns</code> added</li>
                    <!-- /wp:list-item -->
                     
                    <!-- wp:list-item -->
                    <li>Improved performance

                Hey Hey

                <b>More lines</b>
                        <!-- wp:list -->
                        <ul>
                            <!-- wp:list-item -->
                            <li>New <code>block
                Hey Hey

                <b>More lines</b> patterns</code> added</li>
                            <!-- /wp:list-item -->
                            <!-- wp:list-item -->
                            <li>Improved performance</li>
                            <!-- /wp:list-item -->
                        </ul>
                        <!-- /wp:list -->
                    </li>
                    <!-- /wp:list-item -->
                    <!-- wp:list-item -->
                    <li>Improved performance</li>
                    <!-- /wp:list-item -->
                </ul>
                <!-- /wp:list -->
            </li>
            <!-- /wp:list-item -->
                    <!-- wp:list-item -->
                    <li>Improved performance</li>
                    <!-- /wp:list-item -->
                    <!-- wp:list-item -->
                    <li>Improved performance</li>
                    <!-- /wp:list-item -->
        </ul>
        <!-- /wp:list -->
    </li>
    <!-- /wp:list-item -->
</ul>
<!-- /wp:list -->

<!-- wp:quote -->
<blockquote class="wp-block-quote">
<!-- wp:code -->
<pre class="wp-block-code"><code>function hello() {
    console.log("Hello world!");
}</code></pre>
<!-- /wp:code -->
</blockquote>
<!-- /wp:quote -->

<!-- wp:table -->
<figure class="wp-block-table"><table class="has-fixed-layout">
<thead><tr><th>Header 1</th><th>Header 2</th></tr></thead>
<tbody><tr><td>Cell 1</td><td>Cell 2</td></tr><tr><td>Cell 3</td><td>Cell 4</td></tr></tbody>
</table></figure>
<!-- /wp:table -->

HTML);
            echo '<plaintext>';
            $converter->convert();
            var_dump($converter->get_result());
            die();
        }); 
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
        
        // self::deltree(WP_STATIC_CONTENT_DIR);
        mkdir(WP_STATIC_CONTENT_DIR, 0777, true);
        self::save_db_pages_as_html(WP_STATIC_CONTENT_DIR);
    }

    static private function save_db_pages_as_html($path, $parent_id = null) {
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

                $content_converter = get_post_meta($page_id, 'content_converter', true);
                if(empty($content_converter)) {
                    $content_converter = 'md';
                }

                $title_block = (
                    WP_Import_Utils::block_opener('heading', array('level' => 1)) . 
                    '<h1>' . esc_html(get_the_title()) . '</h1>' . 
                    WP_Import_Utils::block_closer('heading')
                );
                $block_markup = $title_block . $page->post_content;

                switch($content_converter) {
                    case 'html':
                    case 'xhtml':
                        // @TODO: Implement a Blocks to HTML converter.
                        break;
                    case 'md':
                    default:
                        $converter = new WP_Blocks_To_Markdown(
                            $block_markup,
                            array(
                                'title' => get_the_title(),
                            )
                        );
                        if(false === $converter->convert()) {
                            // @TODO: error handling.
                        }
                        $content = $converter->get_result();
                        break;
                }


                $child_pages = get_pages(array('child_of' => $page_id, 'post_type' => 'page'));

                if (!file_exists($path)) {
                    mkdir($path, 0777, true);
                }

                $source_path_relative = get_post_meta($page_id, 'source_path_relative', true);
                if(empty($source_path_relative)) {
                    $title = sanitize_title(get_the_title());
                    $source_path_relative = $page->menu_order . '_' . $title . '.' . $content_converter;
                }
                $source_file_path = WP_STATIC_CONTENT_DIR . '/' . $source_path_relative;
                if (!empty($child_pages)) {
                    if(is_dir($source_file_path)) {
                        $dirname = $source_file_path;
                    } else {
                        $dirname = dirname($source_file_path);
                        mkdir($dirname, 0777, true);
                    }
                    file_put_contents($source_file_path . '/index.' . $content_converter, $content);
                    self::save_db_pages_as_html($dirname, $page_id);
                } else {
                    file_put_contents($source_file_path, $content);
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
