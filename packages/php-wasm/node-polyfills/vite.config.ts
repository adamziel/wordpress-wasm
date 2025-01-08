/// <reference types='vitest' />
import { join } from 'path';
import { defineConfig } from 'vite';
import dts from 'vite-plugin-dts';
import viteTsConfigPaths from 'vite-tsconfig-paths';
// eslint-disable-next-line @nx/enforce-module-boundaries
import { getExternalModules } from '../../vite-extensions/vite-external-modules';

export default defineConfig({
	cacheDir: '../../../node_modules/.vite/php-wasm-node-polyfills',

	plugins: [
		viteTsConfigPaths({
			root: '../../../',
		}),
		dts({
			entryRoot: 'src',
			tsconfigPath: join(__dirname, 'tsconfig.lib.json'),
			pathsToAliases: false,
		}),
	],

	// Uncomment this if you are using workers.
	// worker: {
	//  plugins: [ nxViteTsPaths() ],
	// },

	// Configuration for building your library.
	// See: https://vitejs.dev/guide/build.html#library-mode
	build: {
		lib: {
			// Could also be a dictionary or array of multiple entry points.
			entry: 'src/index.ts',
			name: 'php-wasm-node-polyfills',
			fileName: 'index',
			// Change this to the formats you want to support.
			// Don't forget to update your package.json as well.
			formats: ['es', 'cjs'],
		},
		sourcemap: true,
		rollupOptions: {
			// External packages that should not be bundled into your library.
			external: getExternalModules(),
		},
	},

	test: {
		globals: true,
		cache: {
			dir: '../../../node_modules/.vitest',
		},
		environment:
			'Run this task with either "node" or "jsdom" configuration, e.g. nx run php-wasm-node-polyfills:test:node',
		include: ['src/**/*.{test,spec}.{js,mjs,cjs,ts,mts,cts,jsx,tsx}'],
	},
});
