/// <reference types="vitest" />
import { defineConfig } from 'vite';
import type { Plugin } from 'vite';
import { join } from 'path';
import dts from 'vite-plugin-dts';
// eslint-disable-next-line @nx/enforce-module-boundaries
import { remoteDevServerHost, remoteDevServerPort } from '../build-config';
// eslint-disable-next-line @nx/enforce-module-boundaries
import { viteTsConfigPaths } from '../../vite-extensions/vite-ts-config-paths';
import { copyFileSync, existsSync } from 'fs';
import { buildVersionPlugin } from '../../vite-extensions/vite-build-version';

const path = (filename: string) => new URL(filename, import.meta.url).pathname;
const plugins = [
	viteTsConfigPaths({
		root: '../../../',
	}),
	dts({
		entryRoot: 'src',
		tsconfigPath: join(__dirname, 'tsconfig.lib.json'),
		pathsToAliases: false,
	}),
	/**
	 * Copy the `.htaccess` file to the `dist` directory.
	 */
	{
		name: 'htaccess-plugin',
		apply: 'build',
		writeBundle({ dir: outputDir }) {
			const htaccessPath = path('.htaccess');

			if (existsSync(htaccessPath) && outputDir) {
				copyFileSync(htaccessPath, join(outputDir, '.htaccess'));
			}
		},
	} as Plugin,
	buildVersionPlugin('remote-config'),
];
export default defineConfig({
	assetsInclude: ['**/*.wasm', '*.zip'],
	cacheDir: '../../../node_modules/.vite/playground',
	// Bundled WordPress files live in a separate dependency-free `wordpress`
	// package so that every package may use them without causing circular
	// dependencies.
	// Other than that, the `remote` package has no public assets of its own.
	// Therefore, let's just point the `remote` public directory to the
	// `wordpress` package to make WordPress assets available.
	publicDir: path('../wordpress-builds/public'),

	css: {
		modules: {
			localsConvention: 'camelCaseOnly',
		},
	},

	preview: {
		port: remoteDevServerPort - 100,
		host: remoteDevServerHost,
	},

	server: {
		port: remoteDevServerPort,
		host: remoteDevServerHost,
		fs: {
			// Allow serving files from the 'packages' directory
			allow: ['../../'],
		},
	},

	plugins,

	worker: {
		format: 'es',
		plugins,
		rollupOptions: {
			output: {
				// Ensure the service worker always has the same name
				entryFileNames: (chunkInfo: any) => {
					if (chunkInfo.name === 'service-worker') {
						return 'sw.js';
					}
					return '[name]-[hash].js';
				},
			},
		},
	},

	build: {
		target: 'esnext',
		// Important: Vite does not extract static assets as separate files
		//            in the library mode. assetsInlineLimit: 0 only works
		//            in the app mode.
		// @see https://github.com/vitejs/vite/issues/3295
		assetsInlineLimit: 0,
		sourcemap: true,
		rollupOptions: {
			input: {
				wordpress: path('/remote.html'),
			},
		},
	},

	test: {
		globals: true,
		cache: {
			dir: '../../../node_modules/.vitest',
		},
		environment: 'jsdom',
		include: ['src/**/*.{test,spec}.{js,mjs,cjs,ts,mts,cts,jsx,tsx}'],
	},
});
