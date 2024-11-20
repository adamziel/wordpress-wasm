/**
 * WordPress dependencies.
 */
import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { cog, plus, trash } from '@wordpress/icons';
import { useBlockProps } from '@wordpress/block-editor';
import {
	Placeholder,
	Button,
	Icon,
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
	const { consts } = attributes;
	const [isOpen, setIsOpen] = useState(false);
	const [configName, setConfigName] = useState('');
	const [configValue, setConfigValue] = useState('');
	const [configList, updateConfigList] = useState(Object.entries(consts));
	const [selectedConfig, setSelectedConfig] = useState(undefined);

	useEffect(() => {
		setAttributes({
			consts: Object.fromEntries(configList)
		});
	}, [configList]);

	const addConfig = () => {
		// Update list
		updateConfigList([
			...configList,
			[configName, configValue]
		]);

		// Clear add form
		setConfigName('');
		setConfigValue('');
	};

	const updateConfig = (index, field, fieldValue) => {
		const updated = configList.map(([key, value], i) => {
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

		updateConfigList(updated);
	};

	const removeConfig = () => {
		updateConfigList(configList.filter((config, index) => index !== selectedConfig));
	};

	return (
		<div {...useBlockProps()}>
			<Placeholder
				preview={
					<VStack style={{ width: '100%' }}>
						<HStack justify='left' align={'center'} spacing={3}>
							<Icon icon={cog} size={28} className='step-icon' />
							<VStack spacing={1}>
								<Text upperCase size={12} weight={500} color='#949494'>{metadata.title}</Text>
								{!isSelected && (
									<Text weight={600}>{(<pre>{JSON.stringify(consts, null, " ")}</pre> || `{config consts}`)}</Text>
								)}
							</VStack>
						</HStack>
						{isSelected && (
							<VStack>
								<HStack alignment='bottom'>
									<InputControl
										label="Name"
										value={configName}
										onChange={(value) => { setConfigName(value) }}
									/>
									<InputControl
										label="Value"
										value={configValue}
										onChange={(value) => setConfigValue(value)}
									/>
									<Button
										icon={plus}
										label="Add Config"
										onClick={addConfig}
									/>
								</HStack>
								{consts && configList.map(([key, value], index) => {
									return (
										<HStack key={index} alignment='bottom'>
											<InputControl
												label="Name"
												value={key}
												onChange={(value) => updateConfig(index, 'key', value)}
											/>
											<InputControl
												label="Value"
												value={value}
												onChange={(value) => updateConfig(index, 'value', value)}
											/>
											<Button
												isDestructive
												icon={trash}
												label="Delete Config"
												onClick={() => {
													setSelectedConfig(index);
													setIsOpen(true);
												}}
											/>
										</HStack>
									)
								})}
							</VStack>
						)}
					</VStack>
				}
			/>
			<ConfirmDialog
				isOpen={isOpen}
				onConfirm={() => {
					removeConfig();
					setSelectedConfig(undefined);
					setIsOpen(false);
				}}
				onCancel={() => setIsOpen(false)}
			>
				Delete Config?
			</ConfirmDialog>
		</div>
	);
}
