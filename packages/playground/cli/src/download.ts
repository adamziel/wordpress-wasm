import { EmscriptenDownloadMonitor } from '@php-wasm/progress';
import { createHash } from 'crypto';
import fs from 'fs-extra';
import os from 'os';
import path, { basename } from 'path';

export const CACHE_FOLDER = path.join(os.homedir(), '.wordpress-playground');

/**
 * @TODO: Look for a common abstraction with the downloads done by the website setup.
 * 		  These downloads look similar to what the website does to setup WordPress.
 *        The website could also use service worker caching to speed up the
 *        process.
 */
export async function fetchWordPress(
	wpVersion = 'latest',
	monitor: EmscriptenDownloadMonitor
) {
	const wpDetails = await resolveWPRelease(wpVersion);
	const wpZip = await cachedDownload(
		wpDetails.url,
		`${wpDetails.version}.zip`,
		monitor
	);
	return wpZip;
}

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

export async function resolveWPRelease(version = 'latest') {
	// Support custom URLs
	if (version.startsWith('https://') || version.startsWith('http://')) {
		const shasum = createHash('sha1');
		shasum.update(version);
		const sha1 = shasum.digest('hex');
		return {
			url: version,
			version: 'custom-' + sha1.substring(0, 8),
		};
	}

	if (version === 'trunk' || version === 'nightly') {
		return {
			url: 'https://wordpress.org/nightly-builds/wordpress-latest.zip',
			version: 'nightly-' + new Date().toISOString().split('T')[0],
		};
	}

	let latestVersions = await fetch(
		'https://api.wordpress.org/core/version-check/1.7/?channel=beta'
	).then((res) => res.json());

	latestVersions = latestVersions.offers.filter(
		(v: any) => v.response === 'autoupdate'
	);

	for (const apiVersion of latestVersions) {
		if (version === 'beta' && apiVersion.version.includes('beta')) {
			return {
				url: apiVersion.download,
				version: apiVersion.version,
			};
		} else if (version === 'latest') {
			return {
				url: apiVersion.download,
				version: apiVersion.version,
			};
		} else if (
			apiVersion.version.substring(0, version.length) === version
		) {
			return {
				url: apiVersion.download,
				version: apiVersion.version,
			};
		}
	}

	return {
		url: `https://wordpress.org/wordpress-${version}.zip`,
		version: version,
	};
}
