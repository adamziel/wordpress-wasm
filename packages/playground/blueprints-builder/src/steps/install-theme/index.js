/**
 * WordPress.
 */
import { registerBlockType } from '@wordpress/blocks';
import {brush} from '@wordpress/icons';
/**
 * Internal dependencies
 */
import metadata from './block.json';
import Edit from './edit';

/**
 * Every block starts by registering a new block type definition.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-registration/
 */
registerBlockType(metadata.name, {
	icon: brush,
	edit: Edit,
});
