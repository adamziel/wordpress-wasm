
import * as Comlink from 'comlink';
import {
	PHPServer,
	PHPBrowser,
	startPHP,
	getPHPLoaderModule,
	EmscriptenDownloadMonitor
} from '@wordpress/php-wasm';
import {
	startupOptions,
	materializedProxy,
	setURLScope,
} from '@wordpress/php-wasm/worker-library';
import { DOCROOT, wordPressSiteUrl } from './config';
import { isUploadedFilePath } from './worker-utils';
import { getWordPressModule } from './wp-modules-urls';
import * as macros from './wp-macros';
import patchWordPress from './wp-patch';

let readyResolve;
const ready = new Promise((resolve) => {
	readyResolve = resolve;
});

const monitor = new EmscriptenDownloadMonitor();
const playground: any = {
	onDownloadProgress: (cb) => monitor.addEventListener('progress', cb),
	isReady: () => ready,
};
Comlink.expose(
	playground,
	typeof window !== 'undefined' ? Comlink.windowEndpoint(self.parent) : undefined
);

// Expect underscore, not a dot. Vite doesn't deal well with the dot in the
// parameters names passed to the worker via a query string.
const wpVersion = (startupOptions.wpVersion || '6_1').replace('_', '.');
const phpVersion = (startupOptions.phpVersion || '8_0').replace('_', '.');
const [phpLoaderModule, wpLoaderModule] = await Promise.all([
	getPHPLoaderModule(phpVersion),
	getWordPressModule(wpVersion),
]);
monitor.setModules([phpLoaderModule, wpLoaderModule]);
const php = await startPHP(
	phpLoaderModule,
	monitor.getEmscriptenArgs(),
	[wpLoaderModule]
)

const scope = Math.random().toFixed(16);
const scopedSiteUrl = setURLScope(wordPressSiteUrl, scope).toString();
const server = new PHPServer(php, {
	documentRoot: DOCROOT,
	absoluteUrl: scopedSiteUrl,
	isStaticFilePath: isUploadedFilePath,
});

const browser = new PHPBrowser(server);

Object.assign(playground, {
	scope,
	getWordPressModuleDetails: () => ({
		staticAssetsDirectory: `wp-${wpVersion.replace('_', '.')}`,
		defaultTheme: wpLoaderModule?.defaultThemeName,
	}),
	isReady: () => ready,
	php: materializedProxy(php),
	server: materializedProxy(server),
	browser: materializedProxy(browser),
	wp: {}
});
for (const macro in macros) {
	playground.wp[macro] = (...args) => macros[macro](playground, ...args);
}

patchWordPress(php, scopedSiteUrl);
readyResolve();
