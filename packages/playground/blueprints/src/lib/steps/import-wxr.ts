import { StepHandler } from '.';
import { writeFile } from './write-file';
import { phpVar } from '@php-wasm/util';

/* @ts-ignore */
// Temporary measure to load the data-liberation plugin.
// @TODO: Find a nicer way that runs fetch during the Blueprint compilation process.
import dataLiberationPluginUrl from '../../data-liberation-plugin.zip?url';
import { unzipFile } from '@wp-playground/common';
import { activatePlugin } from './activate-plugin';

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
	const dataLiberationPlugin = await fetch(dataLiberationPluginUrl).then(
		(res) => res.arrayBuffer()
	);
	await unzipFile(
		playground,
		new File(
			[new Uint8Array(dataLiberationPlugin)],
			'data-liberation-plugin.zip'
		),
		'/wordpress/wp-content/plugins/data-liberation'
	);
	await activatePlugin(playground, {
		pluginPath: 'data-liberation/plugin.php',
	});
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
		await playground.run({
			code: `<?php
		require ${phpVar(docroot)} . '/wp-load.php';
		require ${phpVar(docroot)} . '/wp-admin/includes/admin.php';

		$admin_id = get_users(array('role' => 'Administrator') )[0]->ID;
        wp_set_current_user( $admin_id );

		$importer = WP_Stream_Importer::create_for_wxr_file(
			'/tmp/import.wxr'
		);
		while ( true ) {
			if ( true === $importer->next_step() ) {
				// Note we're simply ignoring any frontloading errors.
				switch($importer->get_stage()) {
					case WP_Stream_Importer::STAGE_FRONTLOAD_ASSETS:
						$message = 'Frontloading assets... ';
						break;
					case WP_Stream_Importer::STAGE_IMPORT_ENTITIES:
						$message = 'Importing entities... ';
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
	} catch (error) {
		console.dir('PHP error :(');
		console.error(error);
	}
};
