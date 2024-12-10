import { EmscriptenDownloadMonitor } from '@php-wasm/progress';
import fs from 'fs-extra';
import os from 'os';
import path, { basename } from 'path';

export const CACHE_FOLDER = path.join(os.homedir(), '.wordpress-playground');

export async function fetchSqliteIntegration(
	monitor: EmscriptenDownloadMonitor
) {
	const sqliteZip = await cachedDownload(
		'https://github.com/WordPress/sqlite-database-integration/archive/refs/heads/main.zip',
		'sqlite.zip',
		monitor
	);
	return sqliteZip;
}

// @TODO: Support HTTP cache, invalidate the local file if the remote file has
// changed
export async function cachedDownload(
	remoteUrl: string,
	cacheKey: string,
	monitor: EmscriptenDownloadMonitor
) {
	const artifactPath = path.join(CACHE_FOLDER, cacheKey);
	if (!fs.existsSync(artifactPath)) {
		fs.ensureDirSync(CACHE_FOLDER);
		await downloadTo(remoteUrl, artifactPath, monitor);
	}
	return readAsFile(artifactPath);
}

async function downloadTo(
	remoteUrl: string,
	localPath: string,
	monitor: EmscriptenDownloadMonitor
) {
	const response = await monitor.monitorFetch(fetch(remoteUrl));
	const reader = response.body!.getReader();
	const tmpPath = `${localPath}.partial`;
	const writer = fs.createWriteStream(tmpPath);
	while (true) {
		const { done, value } = await reader.read();
		if (value) {
			writer.write(value);
		}
		if (done) {
			break;
		}
	}
	writer.close();
	if (!writer.closed) {
		await new Promise((resolve, reject) => {
			writer.on('finish', (err: any) => {
				if (err) {
					fs.removeSync(tmpPath);
					reject(err);
				} else {
					fs.renameSync(tmpPath, localPath);
					resolve(null);
				}
			});
		});
	}
}

export function readAsFile(path: string, fileName?: string): File {
	return new File([fs.readFileSync(path)], fileName ?? basename(path));
}
