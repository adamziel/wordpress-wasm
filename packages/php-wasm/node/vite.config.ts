/**
 * For Vitest only! The module is built with esbuild which is configured
 * in project.json.
 */
/// <reference types="vitest" />
import { defineConfig } from 'vite';
import viteTsConfigPaths from 'vite-tsconfig-paths';
// eslint-disable-next-line @nx/enforce-module-boundaries
import { getExternalModules } from '../../vite-extensions/vite-external-modules';

export default defineConfig(function () {
	return {
		cacheDir: '../../../node_modules/.vite/php-wasm',

		plugins: [
			viteTsConfigPaths({
				root: '../../../',
			}),
		],

		// Configuration for building your library.
		// See: https://vitejs.dev/guide/build.html#library-mode
		build: {
			target: 'node',
			lib: {
				// Could also be a dictionary or array of multiple entry points.
				entry: 'src/index.ts',
				name: 'php-wasm-node',
				fileName: 'index',
				formats: ['es'],
			},
			sourcemap: true,
			rollupOptions: {
				// Don't bundle the PHP loaders in the final build. See
				// the preserve-php-loaders-imports plugin above.
				external: getExternalModules(),
				output: {
					entryFileNames: '[name].js',
					chunkFileNames: '[name].js',
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

		define: {
			TEST: JSON.stringify(true),
		},
	};
});
