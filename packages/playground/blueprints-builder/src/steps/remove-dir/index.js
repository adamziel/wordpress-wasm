/**
 * Wordpress dependencies.
 */
import { registerBlockType } from '@wordpress/blocks';
import { trash } from '@wordpress/icons';

/**
 * Internal dependencies.
 */
import metadata from './block.json';
import Edit from './edit';

/**
 * Every block starts by registering a new block type definition.
 */
registerBlockType(metadata.name, {
	icon: trash,
	edit: Edit,
});
