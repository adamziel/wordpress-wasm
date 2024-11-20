/**
 * Wordpress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { code as codeIcon } from '@wordpress/icons';
import { useBlockProps } from '@wordpress/block-editor';
import {
	Placeholder,
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
	const { code } = attributes;

	return (
		<p {...useBlockProps()}>
			<Placeholder
				preview={
					<VStack style={{ width: '100%' }}>
						<HStack justify='left' align={'center'} spacing={3}>
							<Icon icon={codeIcon} size={28} className='step-icon' />
							<VStack spacing={1}>
								<Text upperCase size={12} weight={500} color='#949494'>{metadata.title}</Text>
								{!isSelected && (
									<Text weight={600}>{"code snippet"}</Text>
								)}
							</VStack>
						</HStack>
						{isSelected && (
							<TextareaControl
								__nextHasNoMarginBottom
								help={`When running WordPress functions, the code key must first load wp-load.php and start with "<?php require_once 'wordpress/wp-load.php'; "`}
								label="Code"
								onChange={(value) => setAttributes({ code: value })}
								placeholder="<?php ?>"
								value={code}
							/>
						)}
					</VStack>
				}
			/>
		</p>
	);
}
