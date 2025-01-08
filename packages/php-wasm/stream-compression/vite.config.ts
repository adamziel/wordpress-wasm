/// <reference types='vitest' />
import { defineConfig } from 'vite';
import dts from 'vite-plugin-dts';
import * as path from 'path';
// eslint-disable-next-line @nx/enforce-module-boundaries
import { viteTsConfigPaths } from '../../vite-extensions/vite-ts-config-paths';
// eslint-disable-next-line @nx/enforce-module-boundaries
import { getExternalModules } from '../../vite-extensions/vite-external-modules';

export default defineConfig({
	cacheDir: '../../../node_modules/.vite/php-wasm-stream-compression',

	plugins: [
		dts({
			entryRoot: 'src',
			tsconfigPath: path.join(__dirname, 'tsconfig.lib.json'),
			pathsToAliases: false,
		}),
		viteTsConfigPaths({
			root: '../../../',
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
			name: 'php-wasm-stream-compression',
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
		environment: 'jsdom',
		setupFiles: ['./src/test/vitest-setup-file.ts'],
		include: ['src/**/*.{test,spec}.{js,mjs,cjs,ts,mts,cts,jsx,tsx}'],
	},
});
