<?php
/**
 * Plugin Name: Data Liberation
 * Description: Data parsing and importing primitives.
 *
 * @TODO
 * - Get nonces to work
 * - Visually appealing UI with smooth transitions between state updates. Right
 *   now the UI is jerky. We could have beautiful CSS transitions that would
 *   make the import process feel much smoother.
 * - Delete frontloading placeholders that have been successfully downloaded.
 *   Still keep track of the number of total and successful downloads.
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
add_filter(
	'wp_kses_uri_attributes',
	function () {
		return array();
	}
);

add_action(
	'init',
	function () {
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

		register_post_status(
			'error',
			array(
				'label' => _x( 'Error', 'post' ), // Label name
				'public' => false,
				'exclude_from_search' => false,
				'show_in_admin_all_list' => false,
				'show_in_admin_status_list' => false,
				// translators: %s is the number of errors
				'label_count' => _n_noop( 'Error <span class="count">(%s)</span>', 'Error <span class="count">(%s)</span>' ),
			)
		);
	}
);

// Register admin menu
add_action(
	'admin_menu',
	function () {
		add_menu_page(
			'Data Liberation',
			'Data Liberation',
			'manage_options',
			'data-liberation',
			'data_liberation_admin_page',
			'dashicons-database-import'
		);
	}
);

add_action( 'admin_enqueue_scripts', 'enqueue_data_liberation_scripts' );

function enqueue_data_liberation_scripts() {
	wp_register_script_module(
		'@data-liberation/import-screen',
		plugin_dir_url( __FILE__ ) . 'import-screen.js',
		array( '@wordpress/interactivity', '@wordpress/interactivity-router', 'wp-api-fetch' )
	);
	wp_enqueue_script( 'wp-api-fetch' );
	wp_enqueue_script_module(
		'@data-liberation/import-screen',
		plugin_dir_url( __FILE__ ) . 'import-screen.js',
		array( '@wordpress/interactivity', '@wordpress/interactivity-router' )
	);
}
function data_liberation_add_minute_schedule( $schedules ) {
	// add a 'weekly' schedule to the existing set
	$schedules['data_liberation_minute'] = array(
		'interval' => 30,
		'display' => __( 'Twice a Minute' ),
	);
	return $schedules;
}
add_filter( 'cron_schedules', 'data_liberation_add_minute_schedule' );

// Render admin page
function data_liberation_admin_page() {
	$import_session = WP_Import_Session::get_active();
	?>
	<script>
		(function() {
			const originalUrl = new URL(window.location.href);
			const currentUrl = new URL(window.location.href);
			currentUrl.searchParams.delete("continue");
			if(currentUrl.searchParams.size !== originalUrl.searchParams.size) {
				history.replaceState({}, "", currentUrl.toString());
			}
		})();
	</script>
	<style>
		/**
		 * Hide the output.
		 */
		<?php if ( ! WP_DEBUG ) : ?>
		#import-output {
			display: none;
		}
		<?php endif; ?>
		#import-output:has(> h2) {
			height: 250px;
			overflow-y: auto;
			padding: 10px;
			border: 1px solid #ccc;
			margin: 10px;
		}
	</style>
	<div id="import-output">
	<?php
	if ( $import_session ) {
		if ( isset( $_GET['archive'] ) ) {
			$import_session->archive();
			?>
			<script>
				(function() {
					const currentUrl = new URL(window.location.href);
					currentUrl.searchParams.delete("archive");
					window.location.href = currentUrl.toString();
				})();
			</script>
			<?php
			exit;
		} elseif (
			isset( $_GET['continue'] ) &&
			WP_Stream_Importer::STAGE_FINISHED !== $import_session->get_stage()
		) {
			?>
			<h2>Last importer output (for debugging):</h2>
			<pre><?php data_liberation_process_import(); ?></pre>
			<?php
		}
	}
	?>
	</div>
	<?php

	wp_interactivity_state(
		'dataLiberation',
		array_merge(
			data_liberation_get_interactivity_state(),
			array(
				'frontloadingFailed' => function () {
					$context = wp_interactivity_get_context();
					return $context['item']->post_status === 'error';
				},

				'isCurrentImportAtStage' => function () {
					$context = wp_interactivity_get_context();
					$state = wp_interactivity_state( 'dataLiberation' );
					return $context['stage']['id'] === $state['currentImport']['stage'];
				},
				'isImportTypeSelected' => function () {
					$context = wp_interactivity_get_context();
					$state = wp_interactivity_state( 'dataLiberation' );
					return $context['importType'] === $state['selectedImportType'];
				},
			)
		)
	);

	ob_start();
	include __DIR__ . '/data-liberation-page.php';
	$html = ob_get_clean();
	echo wp_interactivity_process_directives( $html );
}

// Handle form submission
add_action(
	'admin_post_data_liberation_import',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		// @TODO: check nonce
		// check_admin_nonce('data_liberation_import');
		$data_source   = $_POST['data_source'];
		$attachment_id = null;
		$file_name     = '';

		switch ( $data_source ) {
			case 'wxr_file':
				if ( empty( $_FILES['wxr_file']['tmp_name'] ) ) {
					wp_die( 'Please select a file to upload' );
				}
				if ( ! in_array( $_FILES['wxr_file']['type'], array( 'text/xml', 'application/xml' ), true ) ) {
					wp_die( 'Invalid file type' );
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
				if ( is_wp_error( $attachment_id ) ) {
					wp_die( $attachment_id->get_error_message() );
				}
				$file_name      = $_FILES['wxr_file']['name'];
				$import_session = WP_Import_Session::create(
					array(
						'data_source' => 'wxr_file',
						'attachment_id' => $attachment_id,
						'file_name' => $file_name,
					)
				);
				break;

			case 'wxr_url':
				if ( empty( $_POST['wxr_url'] ) || ! filter_var( $_POST['wxr_url'], FILTER_VALIDATE_URL ) ) {
					wp_die( 'Please enter a valid URL' );
				}
				// Don't download the file, it could be 300GB or so. The
				// import callback will stream it as needed.
				$import_session = WP_Import_Session::create(
					array(
						'data_source' => 'wxr_url',
						'source_url' => $_POST['wxr_url'],
					)
				);
				break;

			case 'markdown_zip':
				if ( empty( $_FILES['markdown_zip']['tmp_name'] ) ) {
					wp_die( 'Please select a file to upload' );
				}
				if ( $_FILES['markdown_zip']['type'] !== 'application/zip' ) {
					wp_die( 'Invalid file type' );
				}
				$attachment_id = media_handle_upload( 'markdown_zip', 0 );
				if ( is_wp_error( $attachment_id ) ) {
					wp_die( $attachment_id->get_error_message() );
				}
				$file_name      = $_FILES['markdown_zip']['name'];
				$import_session = WP_Import_Session::create(
					array(
						'data_source' => 'markdown_zip',
						'attachment_id' => $attachment_id,
						'file_name' => $file_name,
					)
				);
				break;

			default:
				wp_die( 'Invalid import type' );
		}

		if ( false === $import_session ) {
			// @TODO: More user friendly error message – maybe redirect back to the import screen and
			//        show the error there.
			wp_die( 'Failed to create an import session' );
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

		wp_redirect(
			add_query_arg(
				'message',
				'import-scheduled',
				admin_url( 'admin.php?page=data-liberation' )
			)
		);
		exit;
	}
);

// Process import in the background
function data_liberation_process_import() {
	$session = WP_Import_Session::get_active();
	if ( ! $session ) {
		_doing_it_wrong(
			__METHOD__,
			'No active import session',
			'1.0.0'
		);
		return false;
	}
	return data_liberation_import_step( $session );
}
add_action( 'data_liberation_process_import', 'data_liberation_process_import' );

function data_liberation_import_step( $session ) {
	$metadata = $session->get_metadata();
	$importer = data_liberation_create_importer( $metadata );
	if ( ! $importer ) {
		return;
	}

	/**
	 * @TODO: Fix this error we get after a few steps:
	 * Notice:  Function WP_XML_Processor::step_in_element was called incorrectly. A tag was not closed. Please see Debugging in WordPress for more information. (This message was added in version WP_VERSION.) in /wordpress/wp-includes/functions.php on line 6114
	 */
	$soft_time_limit_seconds = 5;
	$hard_time_limit_seconds = 25;
	$start_time              = microtime( true );
	$fetched_files           = 0;
	while ( true ) {
		$time_taken = microtime( true ) - $start_time;
		if ( $time_taken >= $soft_time_limit_seconds ) {
			// If we're frontloading and don't have any files fetched yet,
			// we need to give it more time. Otherwise every time we retry,
			// we'll start from the beginning and never advance past the
			// frontloading stage.
			if ( $importer->get_stage() === WP_Stream_Importer::STAGE_FRONTLOAD_ASSETS ) {
				if ( $fetched_files > 0 ) {
					break;
				}
			} else {
				break;
			}
		}
		if ( $time_taken >= $hard_time_limit_seconds ) {
			// No negotiation, we're done.
			// @TODO: Make it easily configurable
			// @TODO: Bump the number of download attempts for the placeholders,
			//        set the status to `error` in each interrupted download.
			break;
		}

		if ( true !== $importer->next_step() ) {
			$session->set_reentrancy_cursor( $importer->get_reentrancy_cursor() );

			$should_advance_to_next_stage = null !== $importer->get_next_stage();
			if ( $should_advance_to_next_stage ) {
				if ( WP_Stream_Importer::STAGE_FRONTLOAD_ASSETS === $importer->get_stage() ) {
					$resolved_all_failures = $session->count_unfinished_frontloading_placeholders() === 0;
					if ( ! $resolved_all_failures ) {
						break;
					}
				}
			}
			if ( ! $importer->advance_to_next_stage() ) {
				break;
			}
			$session->set_stage( $importer->get_stage() );
			$session->set_reentrancy_cursor( $importer->get_reentrancy_cursor() );
			continue;
		}

		switch ( $importer->get_stage() ) {
			case WP_Stream_Importer::STAGE_INDEX_ENTITIES:
				// Bump the total number of entities to import.
				$session->create_frontloading_placeholders( $importer->get_indexed_assets_urls() );
				$session->bump_total_number_of_entities(
					$importer->get_indexed_entities_counts()
				);
				break;
			case WP_Stream_Importer::STAGE_FRONTLOAD_ASSETS:
				$session->bump_frontloading_progress(
					$importer->get_frontloading_progress(),
					$importer->get_frontloading_events()
				);
				break;
			case WP_Stream_Importer::STAGE_IMPORT_ENTITIES:
				$session->bump_imported_entities_counts(
					$importer->get_imported_entities_counts()
				);
				break;
		}

		$session->set_reentrancy_cursor( $importer->get_reentrancy_cursor() );
	}
}

function data_liberation_create_importer( $import ) {
	switch ( $import['data_source'] ) {
		case 'wxr_file':
			$wxr_path = get_attached_file( $import['attachment_id'] );
			if ( false === $wxr_path ) {
				// @TODO: Save the error, report it to the user.
				return;
			}
			$importer = WP_Stream_Importer::create_for_wxr_file(
				$wxr_path,
				array(),
				$import['cursor'] ?? null
			);
			break;

		case 'wxr_url':
			$importer = WP_Stream_Importer::create_for_wxr_url(
				$import['wxr_url'],
				array(),
				$import['cursor'] ?? null
			);
			break;

		case 'markdown_zip':
			// @TODO: Don't unzip. Stream data directly from the ZIP file.
			$zip_path = get_attached_file( $import['attachment_id'] );
			$temp_dir = sys_get_temp_dir() . '/data-liberation-markdown-' . $import['attachment_id'];
			if ( ! file_exists( $temp_dir ) ) {
				mkdir( $temp_dir, 0777, true );
				$zip = new ZipArchive();
				if ( $zip->open( $zip_path ) === true ) {
					$zip->extractTo( $temp_dir );
					$zip->close();
				} else {
					// @TODO: Save the error, report it to the user
					return;
				}
			}
			$markdown_root = $temp_dir;
			$importer      = WP_Markdown_Importer::create_for_markdown_directory(
				$markdown_root,
				array(
					'default_source_site_url' => 'file://' . $markdown_root,
					'local_markdown_assets_root' => $markdown_root,
					'local_markdown_assets_url_prefix' => '@site/',
				),
				$import['cursor'] ?? null
			);
			break;
	}
	// @TODO: Consider moving this to the importer constructor.
	$retries_iterator = new WP_Retry_Frontloading_Iterator( $import['post_id'] );
	$importer->set_frontloading_retries_iterator( $retries_iterator );
	return $importer;
}

function data_liberation_get_interactivity_state() {
	// Populates the initial global state values.
	$import_history = array_map(
		function ( $post ) {
			$import_session = new WP_Import_Session( $post->ID );
			return array(
				'date' => $post->post_date,
				'dataSource' => $import_session->get_data_source(),
				'timeTaken' => human_time_diff( $import_session->get_started_at(), $import_session->is_finished() ? $import_session->get_finished_at() : time() ),
				'entitiesImported' => $import_session->count_all_imported_entities(),
				'totalEntities' => $import_session->count_all_total_entities(),
				'status' => $import_session->get_stage(),
			);
		},
		get_posts(
			array(
				'post_type' => WP_Import_Session::POST_TYPE,
				'post_status' => array( 'archived' ),
				'posts_per_page' => -1,
				'orderby' => 'date',
				'order' => 'DESC',
			)
		)
	);

	$import_session = WP_Import_Session::get_active();

	$stages = array();
	foreach ( WP_Stream_Importer::STAGES_IN_ORDER as $stage ) {
		$stages[] = array(
			'id' => $stage,
			'label' => ucfirst( str_replace( '_', ' ', $stage ) ),
			'completed' => $import_session ? $import_session->is_stage_completed( $stage ) : false,
		);
	}

	$frontloading_progress     = array_map(
		function ( $progress, $url ) {
			$progress['url'] = $url;
			return $progress;
		},
		$import_session ? $import_session->get_frontloading_progress() : array(),
		array_keys( $import_session ? $import_session->get_frontloading_progress() : array() )
	);
	$frontloading_placeholders = $import_session ? $import_session->get_frontloading_placeholders() : array();
	return array(
		// Current import state:
		'currentImport' => $import_session
			? array(
				'active' => true,
				'stage' => $import_session->get_stage(),
				'dataSource' => $import_session->get_data_source(),
				'fileReference' => $import_session->get_human_readable_file_reference(),
				'entityCounts' => $import_session->count_imported_entities(),
				'frontloadingProgress' => $frontloading_progress,
				'hasFrontloadingProgress' => count( $frontloading_progress ) > 0,
				'frontloadingPlaceholders' => $frontloading_placeholders,
				'hasFrontloadingPlaceholders' => count( $frontloading_placeholders ) > 0,
			)
			/**
			 * We need an empty default state that still has all the right data types
			 * to avoid "undefined index" errors in PHP and ".map is not a function"
			 * errors in JS.
			 */
			: array(
				'active' => false,
				'stage' => null,
				'dataSource' => null,
				'fileReference' => null,
				'entityCounts' => array(),
				'frontloadingProgress' => array(),
				'hasFrontloadingProgress' => false,
				'frontloadingPlaceholders' => array(),
				'hasFrontloadingPlaceholders' => false,
			),

		'stages' => $stages,

		// Import form state:
		'selectedImportType' => 'wxr_file',

		// Past imports table:
		'importHistory' => array(
			'entities' => $import_history,
			'numEntities' => count( $import_history ),
			'page' => 1,
		),
	);
}

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'data-liberation/v1',
			'/retry-download',
			array(
				'methods' => 'POST',
				'callback' => function ( $request ) {
					$post_id     = intval( $request->get_param( 'post_id' ) );
					$retry_limit = get_post_meta( $post_id, 'retry_limit', true );
					if ( ! $retry_limit ) {
						$retry_limit = 3;
					}
					update_post_meta( $post_id, 'retry_limit', $retry_limit + 1 );

					return new WP_REST_Response( array( 'success' => true ), 200 );
				},
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			)
		);

		register_rest_route(
			'data-liberation/v1',
			'/ignore-download',
			array(
				'methods' => 'POST',
				'callback' => function ( $request ) {
					$post_id = intval( $request->get_param( 'post_id' ) );
					wp_update_post(
						array(
							'ID' => $post_id,
							'post_status' => WP_Import_Session::FRONTLOAD_STATUS_IGNORED,
						)
					);

					return new WP_REST_Response( array( 'success' => true ), 200 );
				},
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			)
		);

		register_rest_route(
			'data-liberation/v1',
			'/change-download-url',
			array(
				'methods' => 'POST',
				'callback' => function ( $request ) {
					$post_id = intval( $request->get_param( 'post_id' ) );
					$new_url = esc_url_raw( $request->get_param( 'new_url' ) );

					update_post_meta( $post_id, 'current_url', $new_url );
					update_post_meta( $post_id, 'attempts', 0 );
					update_post_meta( $post_id, 'retry_limit', 3 );

					return new WP_REST_Response( array( 'success' => true ), 200 );
				},
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			)
		);

		register_rest_route(
			'data-liberation/v1',
			'/interactivity-state',
			array(
				'methods' => 'GET',
				'callback' => function () {
					return new WP_REST_Response( data_liberation_get_interactivity_state(), 200 );
				},
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			)
		);
	}
);
