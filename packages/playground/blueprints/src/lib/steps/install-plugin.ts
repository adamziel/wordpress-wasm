import { StepHandler } from '.';
import { InstallAssetOptions, installAsset } from './install-asset';
import { activatePlugin } from './activate-plugin';
import { writeFile } from './write-file';
import { zipNameToHumanName } from '../utils/zip-name-to-human-name';
import { Directory } from '../resources';
import { joinPaths } from '@php-wasm/util';
import { writeFiles } from '@php-wasm/universal';
import { logger } from '@php-wasm/logger';

/**
 * @inheritDoc installPlugin
 * @hasRunnableExample
 * @needsLogin
 * @landingPage /wp-admin/plugins.php
 * @example
 *
 * <code>
 * {
 * 		"step": "installPlugin",
 * 		"pluginData": {
 * 			"resource": "wordpress.org/plugins",
 * 			"slug": "gutenberg"
 * 		},
 * 		"options": {
 * 			"activate": true
 * 		}
 * }
 * </code>
 *
 * @example
 *
 * <code>
 * {
 * 		"step": "installPlugin",
 * 		"pluginData": {
 * 			"resource": "git:directory",
 * 			"url": "https://github.com/wordpress/wordpress-playground.git",
 * 				"ref": "HEAD",
 * 				"path": "wp-content/plugins/hello-dolly"
 * 		},
 * 		"options": {
 * 			"activate": true
 * 		}
 * }
 * </code>
 */
export interface InstallPluginStep<FileResource, DirectoryResource>
	extends Pick<InstallAssetOptions, 'ifAlreadyInstalled'> {
	/**
	 * The step identifier.
	 */
	step: 'installPlugin';
	/**
	 * The plugin files to install. It can be a plugin zip file, a single PHP
	 * file, or a directory containing all the plugin files at its root.
	 */
	pluginData: FileResource | DirectoryResource;

	/**
	 * @deprecated. Use `pluginData` instead.
	 */
	pluginZipFile?: FileResource;

	/**
	 * Optional installation options.
	 */
	options?: InstallPluginOptions;
}

export interface InstallPluginOptions {
	/**
	 * Whether to activate the plugin after installing it.
	 */
	activate?: boolean;
	/**
	 * The name of the folder to install the plugin to. Defaults to guessing from pluginData
	 */
	targetFolderName?: string;
}

/**
 * Installs a WordPress plugin in the Playground.
 *
 * @param playground The playground client.
 * @param pluginData The plugin zip file.
 * @param options Optional. Set `activate` to false if you don't want to activate the plugin.
 */
export const installPlugin: StepHandler<
	InstallPluginStep<File, Directory>
> = async (
	playground,
	{ pluginData, pluginZipFile, ifAlreadyInstalled, options = {} },
	progress?
) => {
	if (pluginZipFile) {
		pluginData = pluginZipFile;
		logger.warn(
			'The "pluginZipFile" option is deprecated. Use "pluginData" instead.'
		);
	}

	const pluginsDirectoryPath = joinPaths(
		await playground.documentRoot,
		'wp-content',
		'plugins'
	);
	const targetFolderName =
		'targetFolderName' in options ? options.targetFolderName : '';
	let assetFolderPath = '';
	let assetNiceName = '';
	if (pluginData instanceof File) {
		if (pluginData.name.endsWith('.php')) {
			const destinationFilePath = joinPaths(
				pluginsDirectoryPath,
				pluginData.name
			);
			await writeFile(playground, {
				path: destinationFilePath,
				data: pluginData,
			});
			assetFolderPath = pluginsDirectoryPath;
			assetNiceName = pluginData.name;
		} else {
			// Assume any other file is a zip file
			// @TODO: Consider validating whether this is a zip file?
			const zipFileName =
				pluginData.name.split('/').pop() || 'plugin.zip';
			assetNiceName = zipNameToHumanName(zipFileName);

			progress?.tracker.setCaption(
				`Installing the ${assetNiceName} plugin`
			);
			const assetResult = await installAsset(playground, {
				ifAlreadyInstalled,
				zipFile: pluginData,
				targetPath: `${await playground.documentRoot}/wp-content/plugins`,
				targetFolderName: targetFolderName,
			});
			assetFolderPath = assetResult.assetFolderPath;
			assetNiceName = assetResult.assetFolderName;
		}
	} else if (pluginData) {
		assetNiceName = pluginData.name;
		progress?.tracker.setCaption(`Installing the ${assetNiceName} plugin`);

		const pluginDirectoryPath = joinPaths(
			pluginsDirectoryPath,
			targetFolderName || pluginData.name
		);
		await writeFiles(playground, pluginDirectoryPath, pluginData.files, {
			rmRoot: true,
		});
		assetFolderPath = pluginDirectoryPath;
	}

	// Activate
	const activate = 'activate' in options ? options.activate : true;

	if (activate) {
		await activatePlugin(
			playground,
			{
				pluginPath: assetFolderPath,
				pluginName: assetNiceName,
			},
			progress
		);
	}
};
