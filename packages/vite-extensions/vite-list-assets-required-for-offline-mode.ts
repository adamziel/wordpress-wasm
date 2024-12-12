import { readdirSync, statSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';

/**
 * We want to cache all files from the website root directory, except for the files listed below.
 */
const patternsToNotCache = [
	/**
	 * Static files that are not needed for the website to function offline.
	 */
	'/package.json',
	'/README.md',
	'/.DS_Store',
	'/index.cjs',
	'/index.d.ts',
	/\/lib\/.*/, // Remote lib files
	/\/test-fixtures\/.*/, // Test fixtures
	'/index.js',
	/**
	 * WordPress assets removed from the minified builds, for example:
	 *
	 *      /wp-6.2/wp-content/themes/twentytwentyone/style.css
	 *
	 * We don't need to cache them, because they're only used in a short time
	 * window after the Playground is initially loaded and before they're all backfilled
	 * from a dedicated ZIP file shipping all the static assets for a given minified
	 * build.
	 *
	 * See <Assets backfilling PR link>
	 */
	/^\/wp-[\w+((\.\d+)?)]+\/.*/,
	/**
	 * Demos are not needed for the website to function offline.
	 */
	/^\/demos\/.*/,
	/**
	 * Files needed only by the playground.wordpress.net server.
	 */
	'/.htaccess',
	/**
	 * We can't download the PHP scripts – only get their execution result. This is fine,
	 * we don't need them for the offline mode anyway. This includes things like plugins-proxy.php.
	 */
	/\/.*\.php$/,
	/**
	 * WordPress, PHP, and SQLite files that are loaded during boot.
	 * Eagerly loading all the PHP and WordPress releases offered by Playground would use an
	 * extra ~200MB of bandwidth every time you load Playground on a new device.
	 *
	 * However, most of the time time you only want to load a specific Playground configuration.
	 *
	 * Therefore, in here we're excluding the PHP and WP releases from being loaded
	 * eagerly and instead we're defaulting to caching the specific release that's
	 * loaded anyway when booting Playgroung.
	 */
	/^\/assets\/php_.*\.wasm$/, // PHP WASM files
	/^\/assets\/php_.*\.js$/, // PHP JS files
	/^\/assets\/wp-.*\.zip$/, // Minified WordPress builds and static assets bundles
	/^\/assets\/sqlite-database-integration-[\w]+\.zip/, // SQLite plugin
	/^\/assets\/data-liberation-core\.phar-[\w]+\.gz$/, // Data Liberation library for WXR imports
];

function listFiles(dirPath: string, fileList: string[] = []) {
	const files = readdirSync(dirPath);

	files.forEach((file) => {
		const filePath = join(dirPath, file);
		const fileStat = statSync(filePath);

		if (fileStat.isDirectory()) {
			listFiles(filePath, fileList);
		} else {
			fileList.push(filePath);
		}
	});

	return fileList;
}

/**
 * This Vite plugin saves a list of those files as a JSON file served on
 * `playground.wordpress.net`. Playground then consults the list to
 * download and cache all those files at the end of the boot process.
 * .
 */
export const listAssetsRequiredForOfflineMode = ({
	outputFile,
	distDirectoriesToList,
}: {
	outputFile: string;
	distDirectoriesToList: string[];
}) => {
	return {
		name: 'list-assets-required-for-offline-mode',
		apply: 'build',
		writeBundle({ dir: outputDir }: { dir: string }) {
			const files = distDirectoriesToList.flatMap((dir) => {
				const absoluteDirPath = join(outputDir, dir);
				console.log(`Listing files in ${absoluteDirPath}`);
				return listFiles(absoluteDirPath)
					.map((file) => {
						file = file.replace(absoluteDirPath, '');
						if (file.startsWith('/')) {
							return file;
						}
						return `/${file}`;
					})
					.filter((item) => {
						return !patternsToNotCache.some((pattern) => {
							if (pattern instanceof RegExp) {
								return pattern.test(item);
							}
							return pattern === item;
						});
					});
			});
			writeFileSync(
				join(outputDir, outputFile),
				JSON.stringify(files, null, 2)
			);
		},
	};
};
