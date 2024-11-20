/**
 * Wordpress dependencies.
 */
import { registerBlockType } from '@wordpress/blocks';
import { copy } from '@wordpress/icons';

/**
 * Internal dependencies.
 */
import metadata from './block.json';
import Edit from './edit';

/**
 * Every block starts by registering a new block type definition.
 */
registerBlockType(metadata.name, {
	icon: copy,
	edit: Edit,
});
