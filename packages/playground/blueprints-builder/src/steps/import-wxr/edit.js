/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { details } from '@wordpress/icons';
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
	const { file } = attributes;
	const { url } = file;

	const handleInputChange = (value) => {
		setAttributes({
			file: {
				...file,
				...value
			}
		});
	};

	return (
		<div {...useBlockProps()}>
			<Placeholder
				preview={
					<VStack style={{ width: '100%' }}>
						<HStack justify='left' align={'center'} spacing={3}>
							<Icon icon={details} size={28} className='step-icon' />
							<VStack spacing={1}>
								<Text upperCase size={12} weight={500} color='#949494'>{metadata.title}</Text>
								{!isSelected && (
									<Text weight={600}>{`from ${url || '{wxr url}'}`}</Text>
								)}
							</VStack>
						</HStack>
						{isSelected && (
							<DataForm
								data={{
									url
								}}
								fields={[
									{
										id: 'url',
										label: 'Url',
										type: 'text',
										placeholder: 'e.g, https://your-site.com/starter-content.wxr'
									}
								]}
								form={{
									fields: [
										'url'
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
