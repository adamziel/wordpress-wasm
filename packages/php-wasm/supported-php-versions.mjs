/**
 * @typedef {Object} PhpVersion
 * @property {string} version
 * @property {string} loaderFilename
 * @property {string} wasmFilename
 * @property {string} lastRelease
 */

/**
 * @type {PhpVersion[]}
 * @see https://www.php.net/releases/index.php
 */
export const phpVersions = [
	{
		version: '8.4',
		loaderFilename: 'php_8_4.js',
		wasmFilename: 'php_8_4.wasm',
		lastRelease: '8.4.0',
	},
	{
		version: '8.3',
		loaderFilename: 'php_8_3.js',
		wasmFilename: 'php_8_3.wasm',
		lastRelease: '8.3.0',
	},
	{
		version: '8.2',
		loaderFilename: 'php_8_2.js',
		wasmFilename: 'php_8_2.wasm',
		lastRelease: '8.2.10',
	},
	{
		version: '8.1',
		loaderFilename: 'php_8_1.js',
		wasmFilename: 'php_8_1.wasm',
		lastRelease: '8.1.23',
	},
	{
		version: '8.0',
		loaderFilename: 'php_8_0.js',
		wasmFilename: 'php_8_0.wasm',
		lastRelease: '8.0.30',
	},
	{
		version: '7.4',
		loaderFilename: 'php_7_4.js',
		wasmFilename: 'php_7_4.wasm',
		lastRelease: '7.4.33',
	},
	{
		version: '7.3',
		loaderFilename: 'php_7_3.js',
		wasmFilename: 'php_7_3.wasm',
		lastRelease: '7.3.33',
	},
	{
		version: '7.2',
		loaderFilename: 'php_7_2.js',
		wasmFilename: 'php_7_2.wasm',
		lastRelease: '7.2.34',
	},
	{
		version: '7.1',
		loaderFilename: 'php_7_1.js',
		wasmFilename: 'php_7_1.wasm',
		lastRelease: '7.1.30',
	},
	{
		version: '7.0',
		loaderFilename: 'php_7_0.js',
		wasmFilename: 'php_7_0.wasm',
		lastRelease: '7.0.33',
	},
	{
		version: '5.6',
		loaderFilename: 'php_5_6.js',
		wasmFilename: 'php_5_6.wasm',
		lastRelease: '5.6.40',
	},
];
