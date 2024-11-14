import path, { join } from 'path';
import { spawn } from 'child_process';
import yargs from 'yargs';
import { promises as fs, statSync } from 'fs';
import semver from 'semver';

const parser = yargs(process.argv.slice(2))
	.usage('Usage: $0 [options]')
	.options({
		wpVersion: {
			type: 'string',
			description:
				'The WordPress version to download. Can be a major version like 6.4 or "beta" or "nightly".',
			required: true,
		},
		['output-js']: {
			type: 'string',
			description: 'wp.js and wp.zip output directory',
			required: true,
		},
		['output-assets']: {
			type: 'string',
			description: 'WordPress static files output directory',
			required: true,
		},
		force: {
			type: 'boolean',
			description:
				'Force rebuild even if the version is already downloaded',
			default: process.env.FORCE_REBUILD === 'true',
		},
	});

const args = parser.argv;

let wpVersions = await fetch(
	'https://api.wordpress.org/core/version-check/1.7/?channel=beta'
).then((res) => res.json());

wpVersions = wpVersions.offers.filter((v) => v.response === 'autoupdate');

let beta = null;
if (
	wpVersions[0].current.includes('beta') ||
	wpVersions[0].current.toLowerCase().includes('rc')
) {
	beta = wpVersions[0];
	wpVersions = wpVersions.slice(1);
}

/**
 * Create a list of the latest patch versions for each major.minor version.
 *
 * Sometimes the API may include multiple patch versions for the same major.minor version.
 * Playground builds only the latest patch version.
 */
const latestVersions = wpVersions.reduce((versionAccumulator, wpVersion) => {
	const [major, minor] = wpVersion.version.split('.');
	const majorMinor = `${major}.${minor}`;

	const currentVersionIndex = versionAccumulator.findIndex((v) =>
		v.version.startsWith(majorMinor)
	);
	if (-1 === currentVersionIndex) {
		versionAccumulator.push(wpVersion);
	} else if (
		semver.gt(
			wpVersion.version,
			versionAccumulator[currentVersionIndex].version
		)
	) {
		versionAccumulator[currentVersionIndex] = wpVersion;
	}
	return versionAccumulator;
}, []);

function toVersionInfo(apiVersion, slug = null) {
	if (!apiVersion) {
		return {};
	}
	return {
		url: apiVersion.download,
		version: apiVersion.version,
		majorVersion: apiVersion.version.substring(0, 3),
		slug: slug || apiVersion.version.substring(0, 3),
	};
}

let versionInfo = {};
if (args.wpVersion === 'nightly') {
	versionInfo.url =
		'https://wordpress.org/nightly-builds/wordpress-latest.zip';
	versionInfo.version = 'nightly';
	versionInfo.majorVersion = 'nightly';
	versionInfo.slug = 'nightly';
} else if (args.wpVersion === 'beta') {
	versionInfo = toVersionInfo(beta, 'beta');
} else if (args.wpVersion.startsWith('latest')) {
	const relevantApiVersion = {
		latest: latestVersions[0],
		'latest-minus-1': latestVersions[1],
		'latest-minus-2': latestVersions[2],
		'latest-minus-3': latestVersions[3],
	}[args.wpVersion];
	versionInfo = toVersionInfo(relevantApiVersion);
} else if (args.wpVersion.match(/\d\.\d/)) {
	const relevantApiVersion = latestVersions.find((v) =>
		v.version.startsWith(args.wpVersion)
	);
	versionInfo = toVersionInfo(relevantApiVersion);
}

if (!versionInfo.url) {
	process.stdout.write(`WP version ${args.wpVersion} is not supported\n`);
	process.stdout.write(await parser.getHelp());
	process.exit(1);
}

const sourceDir = path.dirname(new URL(import.meta.url).pathname);
const outputAssetsDir = path.resolve(process.cwd(), args.outputAssets);
const outputJsDir = path.resolve(process.cwd(), args.outputJs);

// Short-circuit if the version is already downloaded and not forced
const versionsPath = `${outputJsDir}/wp-versions.json`;
let versions = {};
try {
	const data = await fs.readFile(versionsPath, 'utf8');
	versions = JSON.parse(data);
} catch (e) {
	// If the existing JSON file doesn't exist or cannot be read,
	// just ignore that and assume an empty one.
	versions = {};
}

if (
	!args.force &&
	versionInfo.slug !== 'nightly' &&
	versions[versionInfo.slug] === versionInfo.version
) {
	process.stdout.write(
		`The requested version was ${args.wpVersion}, but its latest release (${versionInfo.version}) is already downloaded\n`
	);
	process.exit(0);
}

// Build WordPress
const wordpressDir = join(sourceDir, 'wordpress');
try {
	try {
		await fs.rm(wordpressDir, { recursive: true });
	} catch (e) {
		// Ignore
	}
	await fs.mkdir(wordpressDir);
	// Install WordPress in a local directory
	await asyncSpawn(
		'bun',
		[
			'../../cli/src/cli.ts',
			'run-blueprint',
			`--wp=${versionInfo.url}`,
			`--mount-before-install=${wordpressDir}:/wordpress`,
		],
		{ cwd: sourceDir, stdio: 'inherit' }
	);

	// Minify that WordPress
	await asyncSpawn(
		'docker',
		[
			'build',
			'.',
			'--progress=plain',
			'--tag=wordpress-playground',
			'--build-arg',
			`OUT_FILENAME=wp-${versionInfo.slug}`,
		],
		{ cwd: sourceDir, stdio: 'inherit' }
	);
} finally {
	await fs.rm(wordpressDir, { recursive: true });
}

// Extract the WordPress static root with wp-includes/ etc
await asyncSpawn(
	'docker',
	[
		'run',
		'--name',
		'wordpress-playground-tmp',
		'--rm',
		'-v',
		`${outputAssetsDir}:/output`,
		'wordpress-playground',
		'sh',
		'-c',
		`cp -r /root/output/wp-${versionInfo.slug} /output/`,
	],
	{ cwd: sourceDir, stdio: 'inherit' }
);

// Extract wp.zip from the docker image
await asyncSpawn(
	'docker',
	[
		'run',
		'--name',
		'wordpress-playground-tmp',
		'--rm',
		'-v',
		`${outputJsDir}:/output`,
		'wordpress-playground',
		'sh',
		'-c',
		`cp /root/output/*.zip /output/`,
	],
	{ cwd: sourceDir, stdio: 'inherit' }
);

// Update the WordPress versions JSON
// Set WordPress version
versions[versionInfo.slug] = versionInfo.version;

// Sort version keys, which are strings, in an ascending order
versions = Object.keys(versions)
	.sort()
	.reverse()
	.reduce((acc, key) => {
		acc[key] = versions[key];
		return acc;
	}, {});

const slugify = (v) => v.replace(/[^a-zA-Z0-9_]/g, '_');

// Write the updated JSON back to the file
await fs.writeFile(versionsPath, JSON.stringify(versions, null, 2));

const latestStableVersion = Object.keys(versions).filter((v) =>
	v.match(/^\d/)
)[0];

const sizes = {};
for (const version of Object.keys(versions)) {
	const zipPath = `${outputJsDir}/wp-${version}.zip`;
	try {
		sizes[version] = statSync(zipPath).size;
	} catch (e) {
		sizes[version] = 0;
	}
}

// Refresh get-wordpress-module.ts
const getWordPressModulePath = `${outputJsDir}/get-wordpress-module-details.ts`;
const getWordPressModuleContent = `
${Object.keys(versions)
	.map(
		(version) =>
			`// @ts-ignore
import url_${slugify(version)} from './wp-${version}.zip?url';`
	)
	.join('\n')}

/**
 * This file was auto generated by packages/playground/wordpress-builds/build/build.js
 * DO NOT CHANGE MANUALLY!
 * This file must statically exists in the project because of the way
 * vite resolves imports.
 */
export function getWordPressModuleDetails(wpVersion: string = ${JSON.stringify(
	latestStableVersion
)}): { size: number, url: string } {
	switch (wpVersion) {
		${Object.keys(versions)
			.map(
				(version) => `
		case '${version}':
			/** @ts-ignore */
			return {
				size: ${JSON.stringify(sizes[version])},
				url: url_${slugify(version)},
			};
			`
			)
			.join('')}

	}
	throw new Error('Unsupported WordPress module: ' + wpVersion);
}
`;
await fs.writeFile(getWordPressModulePath, getWordPressModuleContent);

function asyncSpawn(...args) {
	return new Promise((resolve, reject) => {
		const child = spawn(...args);

		child.on('close', (code) => {
			if (code === 0) resolve(code);
			else reject(new Error(`Process exited with code ${code}`));
		});
	});
}
