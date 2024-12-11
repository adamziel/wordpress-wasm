/**
 * Avoid adding new code here. @wp-playground/common should remain
 * as lean as possible.
 *
 * This package exists to avoid circular dependencies. Let's not
 * use it as a default place to add code that doesn't seem to fit
 * anywhere else. If there's no good place for your code, perhaps
 * it needs to be restructured? Or maybe there's a need for a new package?
 * Let's always consider these questions before adding new code here.
 */

import { UniversalPHP } from '@php-wasm/universal';
import { phpVars } from '@php-wasm/util';

export { createMemoizedFetch } from './create-memoized-fetch';

export const RecommendedPHPVersion = '8.0';

/**
 * Unzip a zip file inside Playground.
 */
const tmpPath = '/tmp/file.zip';
export const unzipFile = async (
	php: UniversalPHP,
	zipPath: string | File,
	extractToPath: string,
	overwriteFiles = true
) => {
	if (zipPath instanceof File) {
		const zipFile = zipPath;
		zipPath = tmpPath;
		await php.writeFile(
			zipPath,
			new Uint8Array(await zipFile.arrayBuffer())
		);
	}
	const js = phpVars({
		zipPath,
		extractToPath,
		overwriteFiles,
	});
	await php.run({
		code: `<?php
        function unzip($zipPath, $extractTo, $overwriteFiles = true)
        {
            if (!is_dir($extractTo)) {
                mkdir($extractTo, 0777, true);
            }
            $zip = new ZipArchive;
            $res = $zip->open($zipPath);
            if ($res === TRUE) {
				for ($i = 0; $i < $zip->numFiles; $i++) {
					$filename = $zip->getNameIndex($i);
					$fileinfo = pathinfo($filename);
					$extractFilePath = rtrim($extractTo, '/') . '/' . $filename;
					// Check if file exists and $overwriteFiles is false
					if (!file_exists($extractFilePath) || $overwriteFiles) {
						// Extract file
						$zip->extractTo($extractTo, $filename);
					}
				}
				$zip->close();
				chmod($extractTo, 0777);
            } else {
                throw new Exception("Could not unzip file: " . $zip->getStatusString());
            }
        }
        unzip(${js.zipPath}, ${js.extractToPath}, ${js.overwriteFiles});
        `,
	});
	if (await php.fileExists(tmpPath)) {
		await php.unlink(tmpPath);
	}
};

export const zipDirectory = async (
	php: UniversalPHP,
	directoryPath: string
) => {
	const outputPath = `/tmp/file${Math.random()}.zip`;
	const js = phpVars({
		directoryPath,
		outputPath,
	});
	await php.run({
		code: `<?php
		function zipDirectory($directoryPath, $outputPath) {
			$zip = new ZipArchive;
			$res = $zip->open($outputPath, ZipArchive::CREATE);
			if ($res !== TRUE) {
				throw new Exception('Failed to create ZIP');
			}
			$files = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator($directoryPath)
			);
			foreach ($files as $file) {
				$file = strval($file);
				if (is_dir($file)) {
					continue;
				}
				$zip->addFile($file, substr($file, strlen($directoryPath)));
			}
			$zip->close();
			chmod($outputPath, 0777);
		}
		zipDirectory(${js.directoryPath}, ${js.outputPath});
		`,
	});

	const fileBuffer = await php.readFileAsBuffer(outputPath);
	php.unlink(outputPath);
	return fileBuffer;
};
