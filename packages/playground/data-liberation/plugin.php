<?php
/**
 * Plugin Name: Data Liberation
 * Description: Data parsing and importing primitives.
 */

require_once __DIR__ . '/bootstrap.php';

/**
 * Don't run KSES on the attribute values during the import.
 *
 *
 * Without this filter, WP_HTML_Tag_Processor::set_attribute() will
 * assume the value is a URL and run KSES on it, which will incorrectly
 * prefix relative paths with http://.
 *
 *
 * For example:
 *
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

add_action('init', function() {
    if ( defined( 'WP_CLI' ) && WP_CLI ) {
        /**
         * Import a WXR file.
         *
         * <file>
         * : The WXR file to import.
         */
        $command = function ( $args, $assoc_args ) {
            $file = $args[0];
            data_liberation_import( $file );
        };

        // Register the WP-CLI import command.
		// Example usage: wp data-liberation /path/to/file.xml
        WP_CLI::add_command( 'data-liberation', $command );
    }
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
    $import_session = WP_Import_Session::get_active();
    if($import_session) {
        if(isset($_GET['archive'])) {
            $import_session->archive();
            echo '<script>
                const currentUrl = new URL(window.location.href);
                currentUrl.searchParams.delete("archive");
                window.location.href = currentUrl.toString();
            </script>';
            exit;
        } elseif(isset($_GET['continue'])) {
            echo '<h2>Next import step stdout output:</h2>';
            echo '<pre>';
            data_liberation_process_import();
            echo '</pre>';
        }
    }

    // Populates the initial global state values.
    wp_interactivity_state( 'dataLiberation', array(
        'importedEntities' => $import_session ? $import_session->count_imported_entities() : [],
        'totalEntities' => $import_session ? $import_session->get_total_number_of_entities() : [],
        'frontloadingPlaceholders' => $import_session ? $import_session->get_frontloading_placeholders() : [],

        'selectedImportType' => 'wxr_file',
        'isImportTypeSelected' => function() {
            // @TODO Figure out why this function is not hiding the form rows
            $state   = wp_interactivity_state();
            $context = wp_interactivity_get_context();
            return $context['importType'] === $state['selectedImportType'];
        },
    ));
    ?>
    <style>
        .import-stages-list li.current {
            font-weight: bold;
        }

        .current-import {
            display: flex;
            flex-direction: row;
            gap: 20px;
            margin: 20px 0 40px 0;

            .import-stages-list {
                margin: 0;
            }

            .import-stage-details {
                flex-grow: 1;
                h1:first-child,
                h2:first-child,
                h3:first-child,
                h4:first-child,
                h5:first-child,
                h6:first-child {
                    margin-top: 0;
                }
            }
        }
    </style>
    <div class="wrap" data-wp-interactive="dataLiberation">
        <h1>Data Liberation</h1>
        <?php if ($import_session): ?>
            <?php // Show import status if one is active ?>
            <?php
            $stage = $import_session->get_stage();
            $totals = $import_session->get_total_number_of_entities();
            $imported = $import_session->count_imported_entities();
            ?>
            <h2>Current Import</h2>
            <b><?php echo $import_session->get_data_source(); ?>:</b>
            <?php echo $import_session->get_human_readable_file_reference(); ?>
            <div class="current-import">
                <div class="import-stages">
                    <ul class="import-stages-list">
                        <li class="<?php echo $stage === WP_Stream_Importer::STAGE_INITIAL ? 'current' : ''; ?>">
                            <?php if ($import_session->is_stage_completed(WP_Stream_Importer::STAGE_INITIAL)): ?>
                                ☑
                            <?php else: ?>
                                ☐
                            <?php endif ?>
                            New Import Created
                        </li>
                        <li class="<?php echo $stage === WP_Stream_Importer::STAGE_INDEX_ENTITIES ? 'current' : ''; ?>">
                            <?php if ($import_session->is_stage_completed(WP_Stream_Importer::STAGE_INDEX_ENTITIES)): ?>
                                ☑
                            <?php else: ?>
                                ☐
                            <?php endif ?>
                            Index Entities
                        </li>
                        <li class="<?php echo $stage === WP_Stream_Importer::STAGE_TOPOLOGICAL_SORT ? 'current' : ''; ?>">
                            <?php if ($import_session->is_stage_completed(WP_Stream_Importer::STAGE_TOPOLOGICAL_SORT)): ?>
                                ☑
                            <?php else: ?>
                                ☐
                            <?php endif ?>
                            Sort Entities
                        </li>
                        <li class="<?php echo $stage === WP_Stream_Importer::STAGE_FRONTLOAD_ASSETS ? 'current' : ''; ?>">
                            <?php if ($import_session->is_stage_completed(WP_Stream_Importer::STAGE_FRONTLOAD_ASSETS)): ?>
                                ☑
                            <?php else: ?>
                                ☐
                            <?php endif ?>
                            Assets Download
                        </li>
                        <li class="<?php echo $stage === WP_Stream_Importer::STAGE_IMPORT_ENTITIES ? 'current' : ''; ?>">
                            <?php if ($import_session->is_stage_completed(WP_Stream_Importer::STAGE_IMPORT_ENTITIES)): ?>
                                ☑
                            <?php else: ?>
                                ☐
                            <?php endif ?>
                            Content Import
                        </li>
                    </ul>
                </div>
                <div class="import-stage-details">
                    <?php switch($stage): 
                        case WP_Stream_Importer::STAGE_INITIAL: ?>
                            <h3>New Import Created</h3>
                        <?php break; ?>
                        <?php case WP_Stream_Importer::STAGE_INDEX_ENTITIES: ?>
                            <h3>Indexing Entities</h3>
                        <?php break; ?>
                        <?php case WP_Stream_Importer::STAGE_TOPOLOGICAL_SORT: ?>
                            <h3>Sorting Entities</h3>
                            <p>Determining optimal import order...</p>
                        <?php break; ?>
                        <?php case WP_Stream_Importer::STAGE_FRONTLOAD_ASSETS: ?>
                            <h3>Downloading Assets</h3>
                            
                            <?php $frontloading_progress = $import_session->get_frontloading_progress();
                            if (!empty($frontloading_progress)): ?>
                                <progress value="<?php echo $imported['download'] ?? 0; ?>" max="<?php echo $totals['download'] ?? 0; ?>">
                                    <?php echo $imported['download'] ?? 0; ?> / <?php echo $totals['download'] ?? 0; ?> Files Downloaded
                                </progress>
                                <h4>Downloads in progress:</h4>
                                <table>
                                    <thead>
                                        <tr>
                                            <th>File</th>
                                            <th>Progress</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($frontloading_progress as $url => $progress): ?>
                                            <tr>
                                                <td><small><?php echo esc_html(basename($url)); ?></small></td>
                                                <td><progress value="<?php echo $progress['received']; ?>" max="<?php echo $progress['total'] ?? 100; ?>"></progress></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <p>Preparing to download assets...</p>
                            <?php endif; ?>
                        <?php break; ?>
                        <?php case WP_Stream_Importer::STAGE_IMPORT_ENTITIES: ?>
                            <h2>Importing Content</h2>
                        <?php break; ?>
                    <?php endswitch; ?>

                    <?php if( $stage === WP_Stream_Importer::STAGE_FRONTLOAD_ASSETS ): ?>
                        <h3>Downloaded Assets:</h3>
                        <div>
                            <progress data-wp-bind--value="state.importedEntities.download" data-wp-bind--max="state.totalEntities.download"></progress>
                            <span data-wp-text="state.importedEntities.download"></span> / <span data-wp-text="state.totalEntities.download"></span> Files Downloaded
                        </div>
                        <table data-wp-class--hidden="!state.frontloadingPlaceholders.length">
                            <thead>
                                <tr>
                                    <th>File</th>
                                    <th>Retries</th>
                                    <th>State</th>
                                    <th>Details</th>
                                    <th>Options</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template data-wp-each--item="state.frontloadingPlaceholders">
                                    <tr>
                                        <td data-wp-text="context.item.post_title"></td>
                                        <td data-wp-text="context.item.menu_order"></td>
                                        <td data-wp-text="context.item.post_status"></td>
                                        <td data-wp-text="context.item.post_content"></td>
                                        <td>
                                            <div data-wp-class--hidden="state.frontloadingFailed">
                                                @TODO: So many buttons! Replace with a dropdown menu?
                                                <button>Retry</button>
                                                <button>Ignore</button>
                                                <button>Use another URL</button>
                                                <button>Upload manually</button>
                                                <button>Use an existing attachment</button>
                                                <button>Remove from the imported content</button>
                                                <button>Generate a placeholder image</button>
                                            </div>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                        <!-- @TODO: Pagination -->
                    <?php endif; ?>

                    <?php if(
                        $import_session->is_stage_completed(WP_Stream_Importer::STAGE_FRONTLOAD_ASSETS)
                    ): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Entity</th>
                                    <th>Imported</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($imported as $field => $count): ?>
                                    <tr>
                                        <td><?php echo ucfirst($field); ?></td>
                                        <td><?php echo $count; ?></td>
                                        <td><?php echo $totals[$field] ?? 0; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                    <?php if($stage === WP_Stream_Importer::STAGE_FINISHED): ?>
                        <p>
                            Import finished!
                        </p>
                        <a href="<?php echo esc_url(add_query_arg('archive', 'true', admin_url('admin.php?page=data-liberation'))); ?>" class="button">
                            Archive the importing session
                        </a>
                    <?php else: ?>
                        <a href="<?php echo esc_url(add_query_arg('continue', 'true', admin_url('admin.php?page=data-liberation'))); ?>" class="button">
                            Continue importing
                        </a>
                        <a href="<?php echo esc_url(add_query_arg('archive', 'true', admin_url('admin.php?page=data-liberation'))); ?>" class="button">
                            Stop importing and archive the session
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="new-import-form">
            <h2>Start a new import session</h2>
            <form
                method="post"
                enctype="multipart/form-data"
                action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
            >
                <?php wp_nonce_field('data_liberation_import'); ?>
                <input type="hidden" name="action" value="data_liberation_import">
                <table class="form-table">
                    <tr>
                        <th scope="row">Import Type</th>
                        <td>
                            <label data-wp-context='{ "importType": "wxr_file" }'>
                                <input type="radio" name="data_source" value="wxr_file" checked
                                    data-wp-bind--checked="state.isImportTypeSelected"
                                    data-wp-on--change="actions.setImportType">
                                Upload WXR File
                            </label><br>
                            <label data-wp-context='{ "importType": "wxr_url" }'>
                                <input type="radio" name="data_source" value="wxr_url"
                                    data-wp-bind--checked="state.isImportTypeSelected"
                                    data-wp-on--change="actions.setImportType">
                                WXR File URL
                            </label><br>
                            <label data-wp-context='{ "importType": "markdown_zip" }'>
                                <input type="radio" name="data_source" value="markdown_zip"
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
        </div>

        <h2>Previous Import Sessions</h2>

        <table class="form-table">
            <tr>
                <th scope="row">Date</th>
                <th scope="row">Data source</th>
                <th scope="row">Time taken</th>
                <th scope="row">Entities imported</th>
                <th scope="row">Total entities</th>
                <th scope="row">Status</th>
            </tr>
            <?php
            // @TODO: Paginate.
            $import_session_posts = get_posts(array(
                'post_type' => WP_Import_Session::POST_TYPE,
                'post_status' => array('archived'),
                'posts_per_page' => -1,
                'orderby' => 'date',
                'order' => 'DESC',
            ));
            ?>
            <?php if(empty($import_session_posts)): ?>
                <tr>
                    <td colspan="6">No import sessions found</td>
                </tr>
            <?php endif; ?>
            <?php foreach($import_session_posts as $import_session_post): ?>
                <?php $import_session = new WP_Import_Session($import_session_post->ID); ?>
                <tr>
                    <td><?php echo $import_session_post->post_date; ?></td>
                    <td><?php echo $import_session->get_metadata()['data_source']; ?></td>
                    <td><?php echo human_time_diff($import_session->get_started_at(), $import_session->is_finished() ? $import_session->get_finished_at() : time()); ?></td>
                    <td><?php echo array_sum($import_session->count_imported_entities()); ?></td>
                    <td><?php echo array_sum($import_session->get_total_number_of_entities()); ?></td>
                    <td><?php echo $import_session->get_stage(); ?></td>
                </tr>
            <?php endforeach; ?>
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
    $data_source = $_POST['data_source'];
    $attachment_id = null;
    $file_name = '';

    switch ($data_source) {
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
            $import_session = WP_Import_Session::create(array(
                'data_source' => 'wxr_file',
                'attachment_id' => $attachment_id,
                'file_name' => $file_name,
            ));
            break;

        case 'wxr_url':
            if (empty($_POST['wxr_url']) || !filter_var($_POST['wxr_url'], FILTER_VALIDATE_URL)) {
                wp_die('Please enter a valid URL');
            }
            // Don't download the file, it could be 300GB or so. The
            // import callback will stream it as needed.
            $import_session = WP_Import_Session::create(array(
                'data_source' => 'wxr_url',
                'source_url' => $_POST['wxr_url'],
            ));
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
            $import_session = WP_Import_Session::create(array(
                'data_source' => 'markdown_zip',
                'attachment_id' => $attachment_id,
                'file_name' => $file_name,
            ));
            break;

        default:
            wp_die('Invalid import type');
    }

    if ( false === $import_session ) {
        // @TODO: More user friendly error message – maybe redirect back to the import screen and
        //        show the error there.
        wp_die('Failed to create an import session');
    }

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
    $session = WP_Import_Session::get_active();
    if (!$session) {
        _doing_it_wrong(
            __METHOD__,
            'No active import session',
            '1.0.0'
        );
        return false;
    }
    return data_liberation_import_step($session);
}
add_action('data_liberation_process_import', 'data_liberation_process_import');

function data_liberation_import_step($session) {
    $metadata = $session->get_metadata();
    $importer = data_liberation_create_importer($metadata);
    if(!$importer) {
        return;
    }
    /**
     * @TODO: Fix this error we get after a few steps:
     * Notice:  Function WP_XML_Processor::step_in_element was called incorrectly. A tag was not closed. Please see Debugging in WordPress for more information. (This message was added in version WP_VERSION.) in /wordpress/wp-includes/functions.php on line 6114
     */

    // At this moment, the importer knows where to resume from but
    // it hasn't actually pulled the first entity from the stream yet.
    // So let's do that now.
    if($importer->next_step()) {
        // var_dump("Stage: " . $importer->get_stage());
        switch($importer->get_stage()) {
            case WP_Stream_Importer::STAGE_INDEX_ENTITIES:
                // Bump the total number of entities to import.
                $session->store_indexed_assets_urls($importer->get_indexed_assets_urls());
                $session->bump_total_number_of_entities([
                    ...$importer->get_indexed_entities_counts(),
                ]);
                break;
            case WP_Stream_Importer::STAGE_IMPORT_ENTITIES:
                $session->bump_imported_entities_counts(
                    $importer->get_imported_entities_counts()
                );
                break;
        }
    }
    // Move to the next step before saving the cursor so that the next
    // import session resumes from the next step.
    if(WP_Stream_Importer::STAGE_FRONTLOAD_ASSETS === $importer->get_stage()) {
        // Define constraints for this run of the frontloading stage.
        // @TODO: Support these constraints at the importer level, not here.
        $min_files_downloaded = 3;
        $soft_time_limit = 10;
        $start_time = time();
        $files_downloaded = 0;
        while(true) {
            if(!$importer->next_step()) {
                break;
            }
            $frontloading_events = $importer->get_frontloading_events();
            foreach($frontloading_events as $event) {
                if($event->type === WP_Attachment_Downloader_Event::SUCCESS) {
                    ++$files_downloaded;
                }
            }

            $session->bump_frontloading_progress(
                $importer->get_frontloading_progress(),
                $importer->get_frontloading_events()
            );

            $time_taken = time() - $start_time;
            if($time_taken > $soft_time_limit) {
                if($files_downloaded >= $min_files_downloaded) {
                    break;
                }
            }
        }
    } else {
        $importer->next_step();
    }
    if($importer->advance_to_next_stage()) {
        $session->set_stage($importer->get_stage());
    }
    $cursor = $importer->get_reentrancy_cursor();
    if($cursor) {
        $session->set_reentrancy_cursor($cursor);
    }
}

function data_liberation_create_importer($import) {
    switch($import['data_source']) {
        case 'wxr_file':
            $wxr_path = get_attached_file($import['attachment_id']);
            if(false === $wxr_path) {
                // @TODO: Save the error, report it to the user.
                return;
            }
            return WP_Stream_Importer::create_for_wxr_file(
                $wxr_path,
                [],
                $import['cursor'] ?? null
            );

        case 'wxr_url':
            return WP_Stream_Importer::create_for_wxr_url(
                $import['wxr_url'],
                [],
                $import['cursor'] ?? null
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
            return WP_Markdown_Importer::create_for_markdown_directory(
                $markdown_root,
                [
                    'source_site_url' => 'file://' . $markdown_root,
                    'local_markdown_assets_root' => $markdown_root,
                    'local_markdown_assets_url_prefix' => '@site/',
                ],
                $import['cursor'] ?? null
            );
    }
}
