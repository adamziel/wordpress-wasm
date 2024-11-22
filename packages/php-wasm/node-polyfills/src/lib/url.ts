import { currentJsRuntime } from './current-js-runtime';

if (currentJsRuntime === 'NODE') {
	/**
	 * Polyfill for URL.canParse if it's missing.
	 *
	 * URL.canParse is available since Node 19.9.0,
	 * but Playground currently uses Node 18.18.0.
	 *
	 * This implementation is based on the one from `core-js`
	 * by Denis Pushkarev, https://github.com/zloirock
	 * https://github.com/zloirock/core-js/blob/master/packages/core-js/modules/web.url.can-parse.js
	 */
	if (typeof URL.canParse !== 'function') {
		globalThis.URL.canParse = function (url: string) {
			try {
				return !!new URL(url);
			} catch (e) {
				return false;
			}
		};
	}
}
