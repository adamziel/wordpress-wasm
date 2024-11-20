/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { brush } from '@wordpress/icons';
import {
	useBlockProps
} from '@wordpress/block-editor';
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
 * Edit function for the theme installation block.
 *
 * @param {Object} props Component properties.
 * @return {Element} Element to render.
 */
export default function Edit({ attributes, setAttributes, isSelected }) {
	const { themeZipFile, options } = attributes;
	const { resource, path, url, slug } = themeZipFile;
	const { activate, importStarterContent } = options;

	const handleResourceChange = (newResource) => {
		let newAttributes = {
			resource: newResource
		};

		// Conditionally add attributes based on the selected resource
		if (newResource === 'vfs') {
			newAttributes.path = themeZipFile.path || '';
		} else if (newResource === 'url') {
			newAttributes.url = themeZipFile.url || '';
		} else if (newResource === 'wordpress.org/themes') {
			newAttributes.slug = themeZipFile.slug || '';
		}

		setAttributes({
			themeZipFile: newAttributes
		});
	};

	const handleInputChange = (field, value) => {
		setAttributes({
			themeZipFile: {
				...themeZipFile,
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
							<Icon icon={brush} size={28} className='step-icon' />
							<VStack spacing={1}>
								<Text upperCase size={12} weight={500} color='#949494'>{metadata.title}</Text>
								{!isSelected && (
									<Text weight={600}>{`${resource} > ${getResourceInfo(resource) || 'undefined'} > ${activate ? 'Activate' : 'Install and keep Inactive'} > ${importStarterContent ? 'with' : 'without'} starter content`}</Text>
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
									<ToggleGroupControlOption value="wordpress.org/themes" label="Theme" />
									<ToggleGroupControlOption value="vfs" label="VFS" />
								</ToggleGroupControl>

								{resource === 'vfs' && (
									<TextControl
										label={__('Path', 'install-theme')}
										value={path}
										placeholder='Enter the file path for the theme ZIP'
										onChange={(newPath) => handleInputChange('path', newPath)}
									/>
								)}
								{resource === 'url' && (
									<TextControl
										label={__('Url', 'install-theme')}
										value={url}
										placeholder='Enter the URL of the theme ZIP file'
										onChange={(newPath) => handleInputChange('url', newPath)}
									/>
								)}
								{resource === 'wordpress.org/themes' && (
									<TextControl
										label={__('Slug', 'install-theme')}
										value={slug}
										placeholder='Enter the theme slug from WordPress.org (e.g., "twentytwentyfour")'
										onChange={(newPath) => handleInputChange('slug', newPath)}
									/>
								)}

								<ToggleControl
									label="Activate"
									checked={activate}
									onChange={() => setAttributes({
										options: {
											...options,
											activate: !activate
										}
									})}
								/>

								<ToggleControl
									label="Import Starter Content"
									checked={importStarterContent}
									onChange={() => setAttributes({
										options: {
											...options,
											importStarterContent: !importStarterContent
										}
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
