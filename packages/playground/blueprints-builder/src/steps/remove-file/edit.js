/**
 * Wordpress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { trash } from '@wordpress/icons';
import { useBlockProps } from '@wordpress/block-editor';
import {
	Placeholder,
	Icon,
	__experimentalVStack as VStack,
	__experimentalHStack as HStack,
	__experimentalText as Text,
} from '@wordpress/components';
import { DataForm } from '@wordpress/dataviews';

/**
 * Internal dependencies.
 */
import './editor.scss';
import metadata from './block.json';

/**
 * The edit function describes the structure of your block in the context of the
 * editor. This represents what the editor will render when the block is used.
 *
 * @return {Element} Element to render.
 */
export default function Edit({ attributes, setAttributes, isSelected }) {
	const { path } = attributes;

	return (
		<p {...useBlockProps()}>
			<Placeholder
				preview={
					<VStack style={{ width: '100%' }}>
						<HStack justify='left' align={'center'} spacing={3}>
							<Icon icon={trash} size={28} className='step-icon' />
							<VStack spacing={1}>
								<Text upperCase size={12} weight={500} color='#949494'>{metadata.title}</Text>
								{!isSelected && (
									<Text weight={600}>{`at ${path || '{path}'}`}</Text>
								)}
							</VStack>
						</HStack>
						{isSelected && (
							<DataForm
								data={attributes}
								fields={[
									{
										id: 'path',
										label: 'File Path',
										type: 'text',
										placeholder: 'Enter the file path to remove (e.g., /wp-content/plugins/index.php)',
									}
								]}
								form={{
									fields: [
										'path'
									]
								}}
								onChange={setAttributes}
							/>
						)}
					</VStack>
				}
			/>
		</p>
	);
}
