import { saveAs } from 'file-saver';

import { DOCROOT } from '../config';
import type { PlaygroundAPI } from '../app';

// @ts-ignore
import migration from './migration.php?raw';

const databaseExportName = 'databaseExport.xml';
const databaseExportPath = '/' + databaseExportName;

export async function exportFile(playground: PlaygroundAPI, wpVersion: string, phpVersion: string) {
	const databaseExportResponse = await playground.request({
		relativeUrl: '/wp-admin/export.php?download=true&&content=all'
	});
	const databaseExportContent = new TextDecoder().decode(
		databaseExportResponse.body
	);
	await playground.writeFile(databaseExportPath, databaseExportContent);
	const exportName = `wordpress-playground--wp${wpVersion}--php${phpVersion}.zip`;
	const exportPath = `/${exportName}`;
	const exportWriteRequest = await playground.run({
		code:
			migration +
			` generateZipFile('${exportPath}', '${databaseExportPath}', '${DOCROOT}');`,
	});
	if (exportWriteRequest.exitCode !== 0) {
		throw exportWriteRequest.errors;
	}

	const fileBuffer = await playground.readFileAsBuffer(exportName);
	const file = new File([fileBuffer], exportName);
	saveAs(file);
}

export async function importFile(playground: PlaygroundAPI, file: File) {
	if (
		// eslint-disable-next-line no-alert
		!confirm(
			'Are you sure you want to import this file? Previous data will be lost.'
		)
	) {
		return false;
	}

	// Write uploaded file to filesystem for processing with PHP
	const fileArrayBuffer = await file.arrayBuffer();
	const fileContent = new Uint8Array(fileArrayBuffer);
	const importPath = '/import.zip';

	await playground.writeFile(importPath, fileContent);

	// Import the database
	const databaseFromZipFileReadRequest = await playground.run({
		code:
			migration +
			` readFileFromZipArchive('${importPath}', '${databaseExportPath}');`,
	});
	if (databaseFromZipFileReadRequest.exitCode !== 0) {
		throw databaseFromZipFileReadRequest.errors;
	}

	const databaseFromZipFileContent = new TextDecoder().decode(
		databaseFromZipFileReadRequest.body
	);

	const databaseFile = new File(
		[databaseFromZipFileContent],
		databaseExportName
	);

	const importerPageOneResponse = await playground.request({
		relativeUrl: '/wp-admin/admin.php?import=wordpress',
	});

	const importerPageOneContent = new DOMParser().parseFromString(
		new TextDecoder().decode(importerPageOneResponse.body),
		'text/html'
	);

	const firstUrlAction = importerPageOneContent
		.getElementById('import-upload-form')
		?.getAttribute('action');

	const stepOneResponse = await playground.request({
		relativeUrl: `/wp-admin/${firstUrlAction}`,
		method: 'POST',
		files: { import: databaseFile },
	});

	const importerPageTwoContent = new DOMParser().parseFromString(
		new TextDecoder().decode(stepOneResponse.body),
		'text/html'
	);

	const importerPageTwoForm = importerPageTwoContent.querySelector(
		'#wpbody-content form'
	);
	const secondUrlAction = importerPageTwoForm?.getAttribute('action') as string;

	const nonce = (
		importerPageTwoForm?.querySelector(
			"input[name='_wpnonce']"
		) as HTMLInputElement
	).value;

	const referrer = (
		importerPageTwoForm?.querySelector(
			"input[name='_wp_http_referer']"
		) as HTMLInputElement
	).value;

	const importId = (
		importerPageTwoForm?.querySelector(
			"input[name='import_id']"
		) as HTMLInputElement
	).value;

	await playground.request({
		relativeUrl: secondUrlAction,
		method: 'POST',
		formData: {
			_wpnonce: nonce,
			_wp_http_referer: referrer,
			import_id: importId,
		}
	});

	// Import the file system
	const importFileSystemRequest = await playground.run({
		code: migration + ` importZipFile('${importPath}');`,
	});
	if (importFileSystemRequest.exitCode !== 0) {
		throw importFileSystemRequest.errors;
	}

	return true;
}
