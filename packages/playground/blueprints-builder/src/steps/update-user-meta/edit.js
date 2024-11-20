/**
 * WordPress dependencies.
 */
import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { postAuthor, plus, trash } from '@wordpress/icons';
import { useBlockProps } from '@wordpress/block-editor';
import {
	Placeholder,
	Button,
	Icon,
	BaseControl,
	__experimentalConfirmDialog as ConfirmDialog,
	__experimentalInputControl as InputControl,
	__experimentalHStack as HStack,
	__experimentalVStack as VStack,
	__experimentalText as Text,
} from '@wordpress/components';

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
	const { meta, userId } = attributes;
	const [isOpen, setIsOpen] = useState(false);
	const [metaName, setMetaName] = useState('');
	const [metaValue, setMetaValue] = useState('');
	const [metaList, updateMetaList] = useState(Object.entries(meta));
	const [selectedMeta, setSelectedMeta] = useState(undefined);

	useEffect(() => {
		setAttributes({
			meta: Object.fromEntries(metaList)
		});
	}, [metaList]);

	const addOption = () => {
		// Update list
		updateMetaList([
			...metaList,
			[metaName, metaValue]
		]);

		// Clear add form
		setMetaName('');
		setMetaValue('');
	};

	const updateOption = (index, field, fieldValue) => {
		const updated = metaList.map(([key, value], i) => {
			if (i === index) {
				if ('key' === field) {
					return [fieldValue, value]
				}

				if ('value' === field) {
					return [key, fieldValue]
				}
			} else {
				return [key, value];
			}
		});

		updateMetaList(updated);
	};

	const removeOption = () => {
		updateMetaList(metaList.filter((meta, index) => index !== selectedMeta));
	};

	return (
		<div {...useBlockProps()}>
			<Placeholder
				preview={
					<VStack style={{ width: '100%' }}>
						<HStack justify='left' align={'center'} spacing={3}>
							<Icon icon={postAuthor} size={28} className='step-icon' />
							<VStack spacing={1}>
								<Text upperCase size={12} weight={500} color='#949494'>{metadata.title}</Text>
								{!isSelected && (
									<VStack>
										<Text weight={600}>{meta ? <pre>{JSON.stringify(meta, null, " ")}</pre> : <span>{`{config user meta}`}</span>}</Text>
										<Text weight={600}>{userId ? `for UserId ${userId}` : '{user Id}'}</Text>
									</VStack>
								)}
							</VStack>
						</HStack>
						{isSelected && (
							<VStack>

								<HStack justify='left' alignment='bottom'>
									<InputControl
										label="Name"
										value={metaName}
										onChange={(value) => { setMetaName(value) }}
									/>
									<InputControl
										label="Value"
										value={metaValue}
										onChange={(value) => setMetaValue(value)}
									/>
									<Button
										icon={plus}
										label="Add Config"
										onClick={addOption}
									/>
								</HStack>
								{meta && metaList.map(([key, value], index) => {
									return (
										<HStack justify='left' key={index} alignment='bottom'>
											<InputControl
												label="Name"
												value={key}
												onChange={(value) => updateOption(index, 'key', value)}
											/>
											<InputControl
												label="Value"
												value={value}
												onChange={(value) => updateOption(index, 'value', value)}
											/>
											<Button
												isDestructive
												icon={trash}
												label="Delete Config"
												onClick={() => {
													setSelectedMeta(index);
													setIsOpen(true);
												}}
											/>
										</HStack>
									)
								})}
								<HStack justify='left'>
									<InputControl
										label="User Id"
										value={userId}
										onChange={(value) => setAttributes({ userId: Number(value) })}
									/>
								</HStack>
							</VStack>
						)}
					</VStack>
				}
			/>
			<ConfirmDialog
				isOpen={isOpen}
				onConfirm={() => {
					removeOption();
					setSelectedMeta(undefined);
					setIsOpen(false);
				}}
				onCancel={() => setIsOpen(false)}
			>
				Delete User Meta?
			</ConfirmDialog>
		</div >
	);
}
