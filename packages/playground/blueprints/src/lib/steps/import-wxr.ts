import { StepHandler } from '.';
import { writeFile } from './write-file';
import { phpVar } from '@php-wasm/util';
/* @ts-ignore */
// eslint-disable-next-line
import dataLiberationPluginUrl from '../../../../data-liberation/dist/data-liberation-core.phar.gz?url';

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
}

/**
 * Imports a WXR file into WordPress.
 *
 * @param playground Playground client.
 * @param file The file to import.
 */
export const importWxr: StepHandler<ImportWxrStep<File>> = async (
	playground,
	{ file },
	progress?
) => {
	progress?.tracker?.setCaption('Importing content');
	await writeFile(playground, {
		path: '/tmp/import.wxr',
		data: file,
	});
	const docroot = await playground.documentRoot;
	playground.onMessage((messageString) => {
		const message = JSON.parse(messageString) as any;
		if (message?.type === 'import-wxr-progress') {
			progress?.tracker?.setCaption(message.progress);
		} else if (message?.type === 'console.log') {
			console.log(message.data);
		}
	});
	try {
		const r = await playground.run({
			code: `<?php
		require ${phpVar(docroot)} . '/wp-load.php';
		require ${phpVar(docroot)} . '/wp-admin/includes/admin.php';

		// Defines the constants expected by the Box .phar stub when "cli" is used
		// as the SAPI name.
		// @TODO: Don't use the "cli" SAPI string and don't allow composer to run platform checks.
		if(!defined('STDERR')) {
			define('STDERR', fopen('php://stderr', 'w'));
		}
		if(!defined('STDIN')) {
			define('STDIN', fopen('php://stdin', 'r'));
		}
		if(!defined('STDOUT')) {
			define('STDOUT', fopen('php://stdout', 'w'));
		}
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
		while ( true ) {
			if ( true === $importer->next_step() ) {
				$mapping_candidates = $importer->get_site_url_mapping_candidates();
				if (count($mapping_candidates) > 0) {
					/**
					 * Auto-maps the theme unit test attachments domain
					 * to the wp-content/uploads directory on the current site.
					 * @TODO: remove it before merging.
					 */
					$importer->add_site_url_mapping(
						$mapping_candidates[0],
						$new_site_url . '/wp-content/uploads'
					);
					post_message_to_js(json_encode([
						'type' => 'console.log',
						'data' => $importer->get_site_url_mapping_candidates(),
					]));
				}
				// Note we're simply ignoring any frontloading errors.
				switch($importer->get_stage()) {
					case WP_Stream_Importer::STAGE_FRONTLOAD_ASSETS:
						$message = 'Frontloading assets';
						break;
					case WP_Stream_Importer::STAGE_IMPORT_ENTITIES:
						$message = 'Importing entities';
						break;
					default:
						$message = 'Stage: ' . $importer->get_stage();
						break;
				}

				// Report progress to the UI
				// @TODO: Use a reporter that can report progress to the UI,
				//        CLI, wp-admin page, and any other runtime.
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
		console.log(r.text);
	} catch (error) {
		console.dir('PHP error :(');
		console.error(error);
	}
};
