/// <reference types='vitest' />
import { defineConfig } from 'vite';
import dts from 'vite-plugin-dts';
// eslint-disable-next-line @nx/enforce-module-boundaries
import { viteTsConfigPaths } from '../../vite-extensions/vite-ts-config-paths';

const path = (filename: string) => new URL(filename, import.meta.url).pathname;
export default defineConfig({
	assetsInclude: ['**/*.wasm', '*.zip'],
	cacheDir: '../../../node_modules/.vite/playground-common',
	plugins: [
		viteTsConfigPaths({
			root: '../../../',
		}),
		dts({
			entryRoot: 'src',
			tsconfigPath: path('tsconfig.lib.json'),
			pathsToAliases: false,
		}),
	],

	build: {
		target: 'esnext',
		// Important: Vite does not extract static assets as separate files
		//            in the library mode. assetsInlineLimit: 0 only works
		//            in the app mode.
		// @see https://github.com/vitejs/vite/issues/3295
		assetsInlineLimit: 0,
		sourcemap: true,
		rollupOptions: {
			input: path('src/index.ts'),
			// These additional options are required to preserve
			// all the exports from the entry point. Otherwise,
			// vite only preserves the ones it considers to be used.
			output: {
				name: 'exportsFromEntryPoint',
				// Ensure the main entry point always gets output as index.js
				entryFileNames: (chunkInfo: any) => {
					if (chunkInfo.name === 'index') {
						return 'index.js';
					}
					return '[name]-[hash].js';
				},
			},
			preserveEntrySignatures: 'strict',
		},
	},

	test: {
		globals: true,
		cache: {
			dir: '../../../node_modules/.vitest',
		},
		environment: 'node',
		include: ['src/**/*.{test,spec}.{js,mjs,cjs,ts,mts,cts,jsx,tsx}'],
	},
});
