/**
 * Wordpress dependencies.
 */
import { registerBlockType } from '@wordpress/blocks';
import { details } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import Edit from './edit';
import metadata from './block.json';

/**
 * Every block starts by registering a new block type definition.
 */
registerBlockType(metadata.name, {
	icon: details,
	edit: Edit,
});
