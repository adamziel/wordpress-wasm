import { StepHandler, StepProgress } from '.';
import { writeFile } from './write-file';
import { phpVar } from '@php-wasm/util';
import { UniversalPHP } from '@php-wasm/universal';

/**
 * @inheritDoc importWxr
 * @example
 *
 * <code>
 * {
 * 		"step": "importWxr",
 * 		"file": {
 * 			"resource": "url",
 * 			"url": "https://your-site.com/starter-content.wxr"
 * 		}
 * }
 * </code>
 */
export interface ImportWxrStep<ResourceType> {
	step: 'importWxr';
	/** The file to import */
	file: ResourceType;
	/**
	 * The importer to use. Possible values:
	 *
	 * - `default`: The importer from https://github.com/humanmade/WordPress-Importer
	 * - `data-liberation`: The experimental Data Liberation WXR importer developed at
	 *                      https://github.com/WordPress/wordpress-playground/issues/1894
	 *
	 * This option is deprecated. The syntax will not be removed, but once the
	 * Data Liberation importer matures, it will become the only supported
	 * importer and the `importer` option will be ignored.
	 *
	 * @deprecated
	 */
	importer?: 'data-liberation' | 'default';
}

/**
 * Imports a WXR file into WordPress.
 *
 * @param playground Playground client.
 * @param file The file to import.
 */
export const importWxr: StepHandler<ImportWxrStep<File>> = async (
	playground,
	{ file, importer = 'default' },
	progress?
) => {
	if (importer === 'data-liberation') {
		await importWithDataLiberationImporter(playground, file, progress);
	} else {
		await importWithDefaultImporter(playground, file, progress);
	}
};

async function importWithDefaultImporter(
	playground: UniversalPHP,
	file: File,
	progress?: StepProgress | undefined
) {
	progress?.tracker?.setCaption('Importing content');
	await writeFile(playground, {
		path: '/tmp/import.wxr',
		data: file,
	});
	const docroot = await playground.documentRoot;
	await playground.run({
		code: `<?php
	require ${phpVar(docroot)} . '/wp-load.php';
	require ${phpVar(docroot)} . '/wp-admin/includes/admin.php';

	kses_remove_filters();
	$admin_id = get_users(array('role' => 'Administrator') )[0]->ID;
	wp_set_current_user( $admin_id );
	$importer = new WXR_Importer( array(
		'fetch_attachments' => true,
		'default_author' => $admin_id
	) );
	$logger = new WP_Importer_Logger_CLI();
	$importer->set_logger( $logger );
	// Slashes from the imported content are lost if we don't call wp_slash here.
	add_action( 'wp_insert_post_data', function( $data ) {
		return wp_slash($data);
	});
  
  // Ensure that Site Editor templates are associated with the correct taxonomy.
  add_filter( 'wp_import_post_terms', function ( $terms, $post_id ) {
    foreach ( $terms as $post_term ) {
      if ( 'wp_theme' !== $term['taxonomy'] ) continue;
      $post_term = get_term_by('slug', $term['slug'], $term['taxonomy'] );
      if ( ! $post_term ) {
        $post_term = wp_insert_term(
          $term['slug'],
          $term['taxonomy']
        );
        $term_id = $post_term['term_id'];
      } else {
        $term_id = $post_term->term_id;
      }
      wp_set_object_terms( $post_id, $term_id, $term['taxonomy']) ;
    }
    return $terms;
  }, 10, 2 );
	$result = $importer->import( '/tmp/import.wxr' );
	`,
	});
}

async function importWithDataLiberationImporter(
	playground: UniversalPHP,
	file: File,
	progress?: StepProgress | undefined
) {
	progress?.tracker?.setCaption('Preparing content import');
	await writeFile(playground, {
		path: '/tmp/import.wxr',
		data: file,
	});
	const docroot = await playground.documentRoot;
	/**
	 * Surface the import progress information in the Blueprint progress bar.
	 * This temporary message handler is cleared at the end of this step.
	 */
	const clearProgressListener = await playground.onMessage(
		(messageString) => {
			const message = JSON.parse(messageString) as any;
			if (message?.type === 'import-wxr-progress') {
				progress?.tracker?.setCaption(message.progress);
			}
		}
	);
	try {
		await playground.run({
			code: `<?php
	require ${phpVar(docroot)} . '/wp-load.php';
	require ${phpVar(docroot)} . '/wp-admin/includes/admin.php';

	// Defines the constants expected by the Box .phar stub when "cli" is used
	// as the SAPI name.
	// @TODO: Don't use the "cli" SAPI string and don't allow composer to run platform checks.
	if(!defined('STDERR')) define('STDERR', fopen('php://stderr', 'w'));
	if(!defined('STDIN'))  define('STDIN', fopen('php://stdin', 'r'));
	if(!defined('STDOUT')) define('STDOUT', fopen('php://stdout', 'w'));
	
	// Preloaded by the Blueprint compile() function
	require '/internal/shared/data-liberation-core.phar';

	$admin_id = get_users(array('role' => 'Administrator') )[0]->ID;
	wp_set_current_user( $admin_id );

	$new_site_url = get_site_url();
	$importer = WP_Stream_Importer::create_for_wxr_file(
		'/tmp/import.wxr',
		array(
			'new_site_url' => $new_site_url,
		)
	);
	$session = WP_Import_Session::create(
		array(
			'data_source' => 'wxr_file',
			'file_name' => '/tmp/import.wxr',
		)
	);
	while ( true ) {
		if ( true === $importer->next_step() ) {
			/**
			 * We're ignoring any importing errors.
			 * This script is a part of Blueprints and is expected to finish
			 * without stopping. We won't be gathering additional user input
			 * along the way. Instead, we'll just decide not to ignore the
			 * errors.
			 *
			 * @TODO: Consider extracting this code into a CLI script and
			 *        using it here instead of this custom script. Note it's
			 *        about a simple CLI script, not a WP-CLI command, as the
			 *        latter would require downloading 5MB of WP-CLI code.
			 */
			switch ( $importer->get_stage() ) {
				case WP_Stream_Importer::STAGE_INITIAL:
					$message = 'Preparing content import';
					break;
		
				case WP_Stream_Importer::STAGE_INDEX_ENTITIES:
					// Bump the total number of entities to import.
					$indexed = $session->count_all_total_entities();
					$message = 'Content import 1/4: Indexing records (' . $indexed . ' so far)';
					$session->create_frontloading_placeholders( $importer->get_indexed_assets_urls() );
					$session->bump_total_number_of_entities(
						$importer->get_indexed_entities_counts()
					);
					break;

				case WP_Stream_Importer::STAGE_TOPOLOGICAL_SORT:
					$message = 'Content import 2/4: Indexing data';
					break;

				case WP_Stream_Importer::STAGE_FRONTLOAD_ASSETS:
					$session->bump_frontloading_progress(
						$importer->get_frontloading_progress(),
						$importer->get_frontloading_events()
					);
					$nb_media = $session->count_awaiting_frontloading_placeholders();
					$message = 'Content import 3/4: Downloading media (' . $nb_media . ' remaining)';
					break;

				case WP_Stream_Importer::STAGE_IMPORT_ENTITIES:
					$session->bump_imported_entities_counts(
						$importer->get_imported_entities_counts()
					);
					$nb_remaining_entities = $session->count_remaining_entities();
					$message = 'Content import 4/4: Inserting data (' . $nb_remaining_entities . ' remaining)';
					break;

				default:
					$message = 'Importing content';
					break;
			}

			// Report progress to the UI
			post_message_to_js(json_encode([
				'type' => 'import-wxr-progress',
				'progress' => $message,
			]));
			continue;
		}
		if ( $importer->advance_to_next_stage() ) {
			continue;
		}
		// Import finished
		break;
	}
	`,
		});
	} finally {
		await clearProgressListener();
	}
}
