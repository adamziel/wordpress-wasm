import { StepHandler } from '.';
import { writeFile } from './write-file';
import { phpVar } from '@php-wasm/util';

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
};
