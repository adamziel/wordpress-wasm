/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { archive } from '@wordpress/icons';
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
 * Edit function for the plugin installation block.
 *
 * @param {Object} props Component properties.
 * @return {Element} Element to render.
 */
export default function Edit({ attributes, setAttributes, isSelected }) {
	const { zipFile, extractToPath } = attributes;
	const { path } = zipFile;

	const handleInputChange = (value) => {
		const key = Object.keys(value)[0];

		if ('path' === key) {
			setAttributes({
				zipFile: {
					...zipFile,
					...value
				}
			});
		} else {
			setAttributes(value);
		}
	};

	return (
		<div {...useBlockProps()}>
			<Placeholder
				preview={
					<VStack style={{ width: '100%' }}>
						<HStack justify='left' align={'center'} spacing={3}>
							<Icon icon={archive} size={28} className='step-icon' />
							<VStack spacing={1}>
								<Text upperCase size={12} weight={500} color='#949494'>{metadata.title}</Text>
								{!isSelected && (
									<Text weight={600}>{`from ${path || '{zip path}'} to ${extractToPath || '{folder path}'}`}</Text>
								)}
							</VStack>
						</HStack>
						{isSelected && (
							<DataForm
								data={{
									path,
									extractToPath
								}}
								fields={[
									{
										id: 'path',
										label: 'Path',
										type: 'text',
										placeholder: 'The path of the zip file to extract'
									},
									{
										id: 'extractToPath',
										label: 'Extract To Path',
										type: 'text',
										placeholder: 'The path to extract the zip file to'
									}
								]}
								form={{
									fields: [
										'path',
										'extractToPath'
									]
								}}
								onChange={handleInputChange}
							/>
						)}
					</VStack>
				}
			/>
		</div>
	);
}
