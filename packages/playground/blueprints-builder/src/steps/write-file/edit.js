/**
 * Wordpress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { page } from '@wordpress/icons';
import { useBlockProps } from '@wordpress/block-editor';
import {
	Placeholder,
	TextControl,
	TextareaControl,
	Icon,
	__experimentalVStack as VStack,
	__experimentalHStack as HStack,
	__experimentalText as Text,
} from '@wordpress/components';

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
	const { path, data } = attributes;

	return (
		<p {...useBlockProps()}>
			<Placeholder
				preview={
					<VStack style={{ width: '100%' }}>
						<HStack justify='left' align={'center'} spacing={3}>
							<Icon icon={page} size={28} className='step-icon' />
							<VStack spacing={1}>
								<Text upperCase size={12} weight={500} color='#949494'>{metadata.title}</Text>
								{!isSelected && (
									<Text weight={600}>{`write file at ${path}`}</Text>
								)}
							</VStack>
						</HStack>
						{isSelected && (
							<VStack>
								<TextControl
									label={__('Path', 'write-file')}
									value={path}
									placeholder='The path of the file to write to'
									onChange={(value) => setAttributes({ path: value })}
								/>
								<TextareaControl
									__nextHasNoMarginBottom
									label="Data"
									onChange={(value) => setAttributes({ data: value })}
									placeholder="The data to write"
									value={data}
								/>
							</VStack>
						)}
					</VStack>
				}
			/>
		</p>
	);
}
