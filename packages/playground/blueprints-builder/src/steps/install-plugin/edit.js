/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { plugins } from '@wordpress/icons';
import { useBlockProps } from '@wordpress/block-editor';
import {
	Placeholder,
	TextControl,
	ToggleControl,
	Icon,
	__experimentalVStack as VStack,
	__experimentalToggleGroupControl as ToggleGroupControl,
	__experimentalToggleGroupControlOption as ToggleGroupControlOption,
	__experimentalHStack as HStack,
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
	const { pluginZipFile, options } = attributes;
	const { resource, path, url, slug } = pluginZipFile;
	const { activate } = options;

	const handleResourceChange = (newResource) => {
		let newAttributes = {
			resource: newResource
		};

		// Conditionally add attributes based on the selected resource
		if (newResource === 'vfs') {
			newAttributes.path = pluginZipFile.path || '';
		} else if (newResource === 'url') {
			newAttributes.url = pluginZipFile.url || '';
		} else if (newResource === 'wordpress.org/plugins') {
			newAttributes.slug = pluginZipFile.slug || '';
		}

		setAttributes({
			pluginZipFile: newAttributes
		});
	};

	const handleInputChange = (field, value) => {
		setAttributes({
			pluginZipFile: {
				...pluginZipFile,
				[field]: value
			}
		});
	};

	const getResourceInfo = (resource) => {
		switch (resource) {
			case 'url':
				return url;
			case 'vfs':
				return path;
			default:
				return slug;
		}
	}

	return (
		<div {...useBlockProps()}>
			<Placeholder
				preview={
					<VStack style={{ width: '100%' }}>
						<HStack justify='left' align={'center'} spacing={3}>
							<Icon icon={plugins} size={28} className='step-icon' />
							<VStack spacing={1}>
								<Text upperCase size={12} weight={500} color='#949494'>{metadata.title}</Text>
								{!isSelected && (
									<Text weight={600}>{`${resource} > ${getResourceInfo(resource)} > ${activate ? 'Activate' : 'Install and keep Inactive'}`}</Text>
								)}
							</VStack>
						</HStack>
						{isSelected && (
							<>
								<ToggleGroupControl
									label="Resource"
									value={resource}
									isBlock
									onChange={handleResourceChange}
								>
									<ToggleGroupControlOption value="url" label="URL" />
									<ToggleGroupControlOption value="wordpress.org/plugins" label="Plugin" />
									<ToggleGroupControlOption value="vfs" label="VFS" />
								</ToggleGroupControl>

								{resource === 'vfs' && (
									<TextControl
										label={__('Path', 'install-plugin')}
										value={path}
										placeholder='Enter the file path for the plugin ZIP'
										onChange={(newPath) => handleInputChange('path', newPath)}
									/>
								)}
								{resource === 'url' && (
									<TextControl
										label={__('Url', 'install-plugin')}
										value={url}
										placeholder='Enter the URL of the plugin ZIP file'
										onChange={(newPath) => handleInputChange('url', newPath)}
									/>
								)}
								{resource === 'wordpress.org/plugins' && (
									<TextControl
										label={__('Slug', 'install-plugin')}
										value={slug}
										placeholder='Enter the plugin slug from WordPress.org (e.g., "contact-form-7")'
										onChange={(newPath) => handleInputChange('slug', newPath)}
									/>
								)}

								<ToggleControl
									label="Activate"
									checked={activate}
									onChange={() => setAttributes({
										options: { activate: !activate }
									})}
								/>
							</>
						)}
					</VStack>
				}
			/>
		</div>
	);
}
