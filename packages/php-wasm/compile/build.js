import path from 'path';
import util from 'util';
import fs from 'fs';
const rmAsync = util.promisify(fs.rm);
import { spawn } from 'child_process';
import { phpVersions } from '../supported-php-versions.mjs';

// yargs parse
import yargs from 'yargs';
const argParser = yargs(process.argv.slice(2))
	.usage('Usage: $0 [options]')
	.options({
		PLATFORM: {
			type: 'string',
			choices: ['web', 'node'],
			default: 'web',
			description: 'The platform to build for',
		},
		JSPI: {
			type: 'boolean',
			default: false,
			description: 'Build with JSPI support',
		},
		DEBUG: {
			type: 'boolean',
			default: false,
			description: 'Build with debug symbols',
		},
		WITH_FILEINFO: {
			type: 'string',
			choices: ['yes', 'no'],
			description: 'Build with fileinfo support',
		},
		WITH_LIBXML: {
			type: 'string',
			choices: ['yes', 'no'],
			description: 'Build with libxml support',
		},
		WITH_LIBZIP: {
			type: 'string',
			choices: ['yes', 'no'],
			description: 'Build with libzip support',
		},
		WITH_GD: {
			type: 'string',
			choices: ['yes', 'no'],
			description: 'Build with libpng support',
		},
		WITH_ICONV: {
			type: 'string',
			choices: ['yes', 'no'],
			description: 'Build with iconv support',
		},
		WITH_MBSTRING: {
			type: 'string',
			choices: ['yes', 'no'],
			description: 'Build with mbstring support',
		},
		WITH_MBREGEX: {
			type: 'string',
			choices: ['yes', 'no'],
			description: 'Build with mbregex support',
		},
		WITH_CLI_SAPI: {
			type: 'string',
			choices: ['yes', 'no'],
			description: 'Build with CLI SAPI',
		},
		WITH_OPENSSL: {
			type: 'string',
			choices: ['yes', 'no'],
			description: 'Build with OpenSSL support',
		},
		WITH_NODEFS: {
			type: 'string',
			choices: ['yes', 'no'],
			description: 'Build with Node.js FS support',
		},
		WITH_CURL: {
			type: 'string',
			choices: ['yes', 'no'],
			description: 'Build with cURL support',
		},
		WITH_SQLITE: {
			type: 'string',
			choices: ['yes', 'no'],
			description: 'Build with SQLite support',
		},
		WITH_SOURCEMAPS: {
			type: 'string',
			choices: ['yes', 'no'],
			description: 'Build with source maps',
		},
		WITH_ICONV: {
			type: 'string',
			choices: ['yes', 'no'],
			description: 'Build with source maps',
		},
		WITH_MYSQL: {
			type: 'string',
			choices: ['yes', 'no'],
			description: 'Build with MySQL support',
		},
		WITH_WS_NETWORKING_PROXY: {
			type: 'string',
			choices: ['yes', 'no'],
			description: 'Build with WebSocket networking proxy support',
		},
		PHP_VERSION: {
			type: 'string',
			description: 'The PHP version to build',
			required: true,
		},
		['output-dir']: {
			type: 'string',
			description: 'The output directory',
			required: true,
		},
		WITH_OPENSSL_VERSION: {
			type: 'string',
			choices: ['1.1.0h', '1.1.1'],
			description: 'OpenSSL version to use',
			default: '1.1.0h',
		},
	});

const args = argParser.argv;

const platformDefaults = {
	all: {
		PHP_VERSION: '8.0.24',
		WITH_LIBZIP: 'yes',
		WITH_SQLITE: 'yes',
		WITH_JSPI: 'no',
		WITH_CURL: 'yes',
		WITH_FILEINFO: 'yes',
		WITH_ICONV: 'yes',
		WITH_LIBXML: 'yes',
		WITH_GD: 'yes',
		WITH_MBSTRING: 'yes',
		WITH_MBREGEX: 'yes',
		WITH_OPENSSL: 'yes',
		WITH_WS_NETWORKING_PROXY: 'yes',
	},
	web: {},
	node: {
		WITH_CLI_SAPI: 'yes',
		WITH_NODEFS: 'yes',
		WITH_MYSQL: 'yes',
	},
};
const platform = args.PLATFORM;

/* eslint-disable prettier/prettier */
const getArg = (name) => {
	let value =
		name in args
			? args[name]
			: name in platformDefaults[platform]
			? platformDefaults[platform][name]
			: name in platformDefaults.all
			? platformDefaults.all[name]
			: 'no';
	if (name === 'PHP_VERSION') {
		value = fullyQualifiedPHPVersion(value);
	}
	return `${name}=${value}`;
};

const requestedVersion = getArg('PHP_VERSION');
if (!requestedVersion || requestedVersion === 'undefined') {
	process.stdout.write(`PHP version ${requestedVersion} is not supported\n`);
	process.stdout.write(await argParser.getHelp());
	process.exit(1);
}

const sourceDir = path.dirname(new URL(import.meta.url).pathname);

// Build the base image
await asyncSpawn('make', ['base-image'], { cwd: sourceDir, stdio: 'inherit' });

await asyncSpawn(
	'docker',
	[
		'build',
		'-f',
		'php/Dockerfile',
		'.',
		'--tag=php-wasm',
		args.DEBUG ? '--progress=plain' : '--progress=auto',
		'--build-arg',
		getArg('PHP_VERSION'),
		'--build-arg',
		`OPENSSL_VERSION=${args.WITH_OPENSSL_VERSION || '1.1.0h'}`,
		'--build-arg',
		getArg('WITH_FILEINFO'),
		'--build-arg',
		getArg('WITH_LIBXML'),
		'--build-arg',
		getArg('WITH_LIBZIP'),
		'--build-arg',
		getArg('WITH_GD'),
		'--build-arg',
		getArg('WITH_MBSTRING'),
		'--build-arg',
		getArg('WITH_MBREGEX'),
		'--build-arg',
		getArg('WITH_CLI_SAPI'),
		'--build-arg',
		getArg('WITH_OPENSSL'),
		'--build-arg',
		getArg('WITH_NODEFS'),
		'--build-arg',
		getArg('WITH_CURL'),
		'--build-arg',
		getArg('WITH_SQLITE'),
		'--build-arg',
		getArg('WITH_SOURCEMAPS'),
		'--build-arg',
		getArg('WITH_ICONV'),
		'--build-arg',
		getArg('WITH_MYSQL'),
		'--build-arg',
		getArg('WITH_WS_NETWORKING_PROXY'),
		'--build-arg',
		`EMSCRIPTEN_ENVIRONMENT=${platform === 'node' ? 'node' : 'web'}`,
		'--build-arg',
		getArg('WITH_JSPI'),
	],
	{ cwd: sourceDir, stdio: 'inherit' }
);
/* eslint-enable prettier/prettier */

// Extract the PHP WASM module
const outputDir = path.resolve(process.cwd(), args.outputDir);
await asyncSpawn(
	'docker',
	[
		'run',
		'--name',
		'php-wasm-tmp',
		'--rm',
		'-v',
		`${outputDir}:/output`,
		'php-wasm',
		// Use sh -c because wildcards are a shell feature and
		// they don't work without running cp through shell.
		'sh',
		'-c',
		`cp -rf /root/output/* /output && mkdir -p /output/terminfo/x ${
			getArg('WITH_CLI_SAPI') === 'yes'
				? '&& cp /root/lib/share/terminfo/x/xterm /output/terminfo/x'
				: ''
		}`,
	],
	{ cwd: sourceDir, stdio: 'inherit' }
);

const _args = args;

function asyncSpawn(...args) {
	console.log('Running', args[0], args[1].join(' '), '...');
	return new Promise((resolve, reject) => {
		const child = spawn(...args);
		child.on('close', (code) => {
			if (code === 0) resolve(code);
			else reject(new Error(`Process exited with code ${code}`));
		});
	});
}

function fullyQualifiedPHPVersion(requestedVersion) {
	for (const { version, lastRelease } of phpVersions) {
		if (requestedVersion === version) {
			return lastRelease;
		}
	}
	return requestedVersion;
}
