<?php
/**
 * Plugin Name: Data Liberation
 * Description: Data parsing and importing primitives.
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

// Register admin menu
add_action('admin_menu', function() {
    add_menu_page(
        'Data Liberation',
        'Data Liberation',
        'manage_options',
        'data-liberation',
        'data_liberation_admin_page',
        'dashicons-database-import'
    );
});

add_action('admin_enqueue_scripts', 'enqueue_data_liberation_scripts');

function enqueue_data_liberation_scripts() {
    wp_register_script_module(
        '@data-liberation/import-screen',
        plugin_dir_url( __FILE__ ) . 'import-screen.js',
        array( '@wordpress/interactivity' )
    );
    wp_enqueue_script_module(
        '@data-liberation/import-screen',
        plugin_dir_url( __FILE__ ) . 'import-screen.js',
        array( '@wordpress/interactivity' )
    );
}
function data_liberation_add_minute_schedule( $schedules ) {
    // add a 'weekly' schedule to the existing set
    $schedules['data_liberation_minute'] = array(
        'interval' => 60,
        'display' => __('Once a Minute')
    );
    return $schedules;
}
add_filter( 'cron_schedules', 'data_liberation_add_minute_schedule' );

// Render admin page
function data_liberation_admin_page() {

    // Populates the initial global state values.
    wp_interactivity_state( 'dataLiberation', array(
        'selectedImportType' => 'wxr_file',
        'isImportTypeSelected' => function() {
            // @TODO Figure out why this function is not hiding the form rows
            $state   = wp_interactivity_state();
            $context = wp_interactivity_get_context();
            return $context['importType'] === $state['selectedImportType'];
        },
    ));
    ?>
    <div class="wrap">
        <h1>Data Liberation</h1>
    <?php

    $current_import = get_option('data_liberation_active_import');
    if(isset($_GET['run-step']) && $current_import) {
        echo '<h2>Next import step stdout output:</h2>';
        echo '<pre>';
        data_liberation_process_import();
        echo '</pre>';
    }
    
    ?>
        <h2>Active import</h2>
        <?php
        // Show import status if one is active
        $active_import = get_option('data_liberation_active_import');
        if ($active_import) {
            $progress = get_option('data_liberation_import_progress');
            ?>
            <div class="notice notice-info">
                <p>
                    <pre><?php echo json_encode($active_import, JSON_PRETTY_PRINT); ?></pre>
                    <strong>$progress array:</strong>
                    <pre><?php echo json_encode($progress, JSON_PRETTY_PRINT); ?></pre>
                    <br/>
                    <a href="<?php echo esc_url(add_query_arg('run-step', 'true', admin_url('admin.php?page=data-liberation'))); ?>">
                        Process the next import chunk
                    </a>
                </p>
                <h4>Decisions required</h4>
                <ul>
                    <li>
                        Image waterfall.png could not be download after 3 attempts.
                        <a href="#">Retry download</a>
                        <a href="#">Upload manually</a>
                        <a href="#">Fetch from another URL</a>
                        <a href="#">Delete attachment and remove its references from the imported posts</a>
                    </li>
                </ul>
                <h4>Progress details</h4>
                <p>
                    TODO: Provide actual progress details.
                </p>
                <table>
                    <tr>
                        <th scope="row">Images downloaded</th>
                        <td>0 / 48 </td>
                    </tr>
                    <tr>
                        <th scope="row">Posts imported</th>
                        <td>0 / 48 </td>
                    </tr>
                    <tr>
                        <th scope="row">Current status</th>
                        <td>Preparing import...</td>
                    </tr>
                </table>
            </div>
            <?php
        } else {
            echo '<p>No import is currently in progress.</p>';
        }
        ?>

        <form
            method="post"
            enctype="multipart/form-data"
            action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
            data-wp-interactive="dataLiberation"
        >
            <?php wp_nonce_field('data_liberation_import'); ?>
            <input type="hidden" name="action" value="data_liberation_import">
            
            <h2>Import Content</h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row">Import Type</th>
                    <td>
                        <label data-wp-context='{ "importType": "wxr_file" }'>
                            <input type="radio" name="import_type" value="wxr_file" checked
                                data-wp-bind--checked="state.isImportTypeSelected"
                                data-wp-on--change="actions.setImportType">
                            Upload WXR File
                        </label><br>
                        <label data-wp-context='{ "importType": "wxr_url" }'>
                            <input type="radio" name="import_type" value="wxr_url"
                                data-wp-bind--checked="state.isImportTypeSelected"
                                data-wp-on--change="actions.setImportType">
                            WXR File URL
                        </label><br>
                        <label data-wp-context='{ "importType": "markdown_zip" }'>
                            <input type="radio" name="import_type" value="markdown_zip"
                                data-wp-bind--checked="state.isImportTypeSelected"
                                data-wp-on--change="actions.setImportType">
                            Markdown ZIP Archive
                        </label>
                    </td>
                </tr>
                
                <tr data-wp-context='{ "importType": "wxr_file" }'
                    data-wp-class--hidden="!state.isImportTypeSelected">
                    <th scope="row">WXR File</th>
                    <td>
                        <input type="file" name="wxr_file" accept=".xml">
                        <p class="description">Upload a WordPress eXtended RSS (WXR) file</p>
                    </td>
                </tr>
                
                <tr data-wp-context='{ "importType": "wxr_url" }'
                data-wp-class--hidden="!state.isImportTypeSelected">
                    <th scope="row">WXR URL</th>
                    <td>
                        <input type="url" name="wxr_url" class="regular-text">
                        <p class="description">Enter the URL of a WXR file</p>
                    </td>
                </tr>
                
                <tr data-wp-context='{ "importType": "markdown_zip" }'
                    data-wp-class--hidden="!state.isImportTypeSelected">
                    <th scope="row">Markdown ZIP</th>
                    <td>
                        <input type="file" name="markdown_zip" accept=".zip">
                        <p class="description">Upload a ZIP file containing markdown files</p>
                    </td>
                </tr>
            </table>

            <?php submit_button('Start Import'); ?>
        </form>

        <h2>Previous Imports</h2>

        <p>TODO: Show a table of previous imports.</p>
            
        <table class="form-table">
            <tr>
                <th scope="row">Date</th>
                <th scope="row">Data source</th>
                <th scope="row">Time taken</th>
                <th scope="row">Entities imported</th>
                <th scope="row">Result</th>
            </tr>
            <tr>
                <td>2024-01-01</td>
                <td>WXR file</td>
                <td>10 minutes</td>
                <td>1000</td>
                <td>Success</td>
            </tr>
        </table>
    </div>
    <?php
}

// Handle form submission
add_action('admin_post_data_liberation_import', function() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    // @TODO: check nonce
    // check_admin_nonce('data_liberation_import');
    $import_type = $_POST['import_type'];
    $attachment_id = null;
    $file_name = '';

    switch ($import_type) {
        case 'wxr_file':
            if (empty($_FILES['wxr_file']['tmp_name'])) {
                wp_die('Please select a file to upload');
            }
            if (!in_array($_FILES['wxr_file']['type'], ['text/xml', 'application/xml'])) {
                wp_die('Invalid file type');
            }
            /**
             * @TODO: Reconsider storing the file in the media library where everyone
             *        can access it via a public URL.
             */
            $attachment_id = media_handle_upload(
                'wxr_file',
                0,
                array(),
                array(
                    'mimes' => array(
                        'xml' => 'text/xml',
                        'xml-application' => 'application/xml',
                    ),
                    // test_form checks:
                    // Whether to test that the $_POST['action'] parameter is as expected.
                    // It seems useless here and it causes cryptic error "Invalid form submission".
                    // Let's just disable it.
                    'test_form' => false,

                    // @TODO: Find a way to make this type check work.
                    'test_type' => false,
                )
            );
            if (is_wp_error($attachment_id)) {
                wp_die($attachment_id->get_error_message());
            }
            $file_name = $_FILES['wxr_file']['name'];
            break;

        case 'wxr_url':
            if (empty($_POST['wxr_url']) || !filter_var($_POST['wxr_url'], FILTER_VALIDATE_URL)) {
                wp_die('Please enter a valid URL');
            }
            // Don't download the file, it could be 300GB or so. The
            // import callback will stream it as needed.
            break;

        case 'markdown_zip':
            if (empty($_FILES['markdown_zip']['tmp_name'])) {
                wp_die('Please select a file to upload');
            }
            if ($_FILES['markdown_zip']['type'] !== 'application/zip') {
                wp_die('Invalid file type');
            }
            $attachment_id = media_handle_upload('markdown_zip', 0);
            if (is_wp_error($attachment_id)) {
                wp_die($attachment_id->get_error_message());
            }
            $file_name = $_FILES['markdown_zip']['name'];
            break;

        default:
            wp_die('Invalid import type');
    }

    // Store import info
    // @TODO: consider storing a history of imports instead of just the current one
    update_option('data_liberation_active_import', array(
        'type' => $import_type,
        'wxr_url' => $_POST['wxr_url'] ?? null,
        'attachment_id' => $attachment_id,
        'file_name' => $file_name,
        'started_at' => current_time('mysql')
    ));

    update_option('data_liberation_import_progress', array(
        'status' => 'Preparing import...',
        'current' => 0,
        'total' => 0
    ));

    // Schedule the next import step every minute, so 30 seconds more than the
    // default PHP max_execution_time.
    /**
     * @TODO: The schedule doesn't seem to be actually running.
     */
    // if(is_wp_error(wp_schedule_event(time(), 'data_liberation_minute', 'data_liberation_process_import'))) {
    //     wp_delete_attachment($attachment_id, true);
    //     // @TODO: More user friendly error message – maybe redirect back to the import screen and 
    //     //        show the error there.
    //     wp_die('Failed to schedule import – the "data_liberation_minute" schedule may not be registered.');
    // }

    wp_redirect(add_query_arg(
        'message', 'import-scheduled',
        admin_url('admin.php?page=data-liberation')
    ));
    exit;
});

// Process import in the background
function data_liberation_process_import() {
    $import = get_option('data_liberation_active_import');
    if (!$import) {
        return;
    }
    return data_liberation_import_step($import);
}
add_action('data_liberation_process_import', 'data_liberation_process_import');

function data_liberation_import_step($import) {
    $importer = data_liberation_create_importer($import);
    // @TODO: Save the last importer state so we can resume it later if interrupted.
    update_option('data_liberation_import_progress', [
        'status' => 'Downloading static assets...',
        'current' => 0,
        'total' => 0
    ]);
    $importer->frontload_assets();
    // @TODO: Keep track of multiple progress dimensions – posts, assets, categories, etc.
    update_option('data_liberation_import_progress', [
        'status' => 'Importing posts...',
        'current' => 0,
        'total' => 0
    ]);
    $importer->import_entities();
    delete_option('data_liberation_active_import');
    // @TODO: Do not echo things. Append to an import log where we can retrace the steps.
    //        Also, store specific import events in the database so the user can react and
    //        make decisions.
    echo '<br/>Import finished. TODO: Summary: Time taken, number of entities imported, etc. Also, preserve something tabular for the user to review the historical results.';
}

function data_liberation_create_importer($import) {
    switch($import['type']) {
        case 'wxr_file':
            $wxr_path = get_attached_file($import['attachment_id']);
            if(false === $wxr_path) {
                // @TODO: Save the error, report it to the user.
                return;
            }
            $entity_iterator_factory = function() use ($wxr_path) {
                $wxr = new WP_WXR_Reader();
                $wxr->connect_upstream(new WP_File_Reader($wxr_path));

                return $wxr;
            };
            return WP_Stream_Importer::create(
                $entity_iterator_factory
            );

        case 'wxr_url':
            $wxr_url = $import['wxr_url'];
            $entity_iterator_factory = function() use ($wxr_url) {
                $wxr = new WP_WXR_Reader();
                $wxr->connect_upstream(new WP_Remote_File_Reader($wxr_url));
                return $wxr;
            };
            return WP_Stream_Importer::create(
                $entity_iterator_factory
            );

        case 'markdown_zip':
            // @TODO: Don't unzip. Stream data directly from the ZIP file.
            $zip_path = get_attached_file($import['attachment_id']);
            $temp_dir = sys_get_temp_dir() . '/data-liberation-markdown-' . $import['attachment_id'];
            if (!file_exists($temp_dir)) {
                mkdir($temp_dir, 0777, true);
                $zip = new ZipArchive();
                if ($zip->open($zip_path) === TRUE) {
                    $zip->extractTo($temp_dir);
                    $zip->close();
                } else {
                    // @TODO: Save the error, report it to the user
                    return;
                }
            }
            $markdown_root = $temp_dir;
            $entity_iterator_factory = function() use ($markdown_root) {
                return new WP_Markdown_Directory_Tree_Reader(
                    $markdown_root,
                    1000
                );
            };
            return WP_Markdown_Importer::create(
                $entity_iterator_factory, [
                    'source_site_url' => 'file://' . $markdown_root,
                    'local_markdown_assets_root' => $markdown_root,
                    'local_markdown_assets_url_prefix' => '@site/',
                ]
            );
    }
}