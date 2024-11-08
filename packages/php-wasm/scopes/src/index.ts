/**
 * Scopes are unique strings, like `my-site`, used to uniquely brand
 * the outgoing HTTP traffic from each browser tab. This helps the
 * main thread distinguish between the relevant and irrelevant
 * messages received from the Service Worker.
 *
 * Scopes are included in the `PHPRequestHandler.absoluteUrl` as follows:
 *
 * An **unscoped** URL: http://localhost:8778/wp-login.php
 * A **scoped** URL:    http://localhost:8778/scope:my-site/wp-login.php
 *
 * For more information, see the README section on scopes.
 */

/**
 * Checks if the given URL contains scope information.
 *
 * @example
 * ```js
 * isURLScoped(new URL('http://localhost/scope:my-site/index.php'));
 * // true
 *
 * isURLScoped(new URL('http://localhost/index.php'));
 * // false
 * ```
 *
 * @param  url The URL to check.
 * @returns `true` if the URL contains scope information, `false` otherwise.
 */
export function isURLScoped(url: URL): boolean {
	return url.pathname.startsWith(`/scope:`);
}

/**
 * Returns the scope stored in the given URL.
 *
 * @example
 * ```js
 * getScopeFromURL(new URL('http://localhost/scope:my-site/index.php'));
 * // '96253'
 *
 * getScopeFromURL(new URL('http://localhost/index.php'));
 * // null
 * ```
 *
 * @param  url The URL.
 * @returns The scope if the URL contains a scope, `null` otherwise.
 */
export function getURLScope(url: URL): string | null {
	if (isURLScoped(url)) {
		return url.pathname.split('/')[1].split(':')[1];
	}
	return null;
}

/**
 * Returns a new URL with the requested scope information.
 *
 * @example
 * ```js
 * setURLScope(new URL('http://localhost/index.php'), 'my-site');
 * // URL('http://localhost/scope:my-site/index.php')
 *
 * setURLScope(new URL('http://localhost/scope:my-site/index.php'), 'my-site');
 * // URL('http://localhost/scope:my-site/index.php')
 *
 * setURLScope(new URL('http://localhost/index.php'), null);
 * // URL('http://localhost/index.php')
 * ```
 *
 * @param  url   The URL to scope.
 * @param  scope The scope value.
 * @returns A new URL with the scope information in it.
 */
export function setURLScope(url: URL | string, scope: string | null): URL {
	let newUrl = new URL(url);

	if (isURLScoped(newUrl)) {
		if (scope) {
			const parts = newUrl.pathname.split('/');
			parts[1] = `scope:${scope}`;
			newUrl.pathname = parts.join('/');
		} else {
			newUrl = removeURLScope(newUrl);
		}
	} else if (scope) {
		const suffix = newUrl.pathname === '/' ? '' : newUrl.pathname;
		newUrl.pathname = `/scope:${scope}${suffix}`;
	}

	return newUrl;
}

/**
 * Returns a new URL without any scope information.
 *
 * @example
 * ```js
 * removeURLScope(new URL('http://localhost/scope:my-site/index.php'));
 * // URL('http://localhost/index.php')
 *
 * removeURLScope(new URL('http://localhost/index.php'));
 * // URL('http://localhost/index.php')
 * ```
 *
 * @param  url The URL to remove scope information from.
 * @returns A new URL without the scope information.
 */
export function removeURLScope(url: URL): URL {
	if (!isURLScoped(url)) {
		return url;
	}
	const newUrl = new URL(url);
	const parts = newUrl.pathname.split('/');
	newUrl.pathname = '/' + parts.slice(2).join('/');
	return newUrl;
}
