import { useEffect, useMemo, useState } from 'react';

export type URLComponents = {
	searchParams: Record<string, string | undefined>;
	hash?: string;
	host?: string;
	port?: string;
	protocol?: string;
	pathname?: string;
};

export function useCurrentUrl() {
	const [url, setUrl] = useState(window.location.href);
	useEffect(() => {
		const unsubscribe = subscribeToLocationUpdates(() => {
			setUrl(window.location.href);
		});
		return () => {
			unsubscribe();
		};
	}, []);

	return useMemo(() => new URL(url), [url]);
}

export function updateUrl(
	currentLocation: string,
	urlComponents: Partial<URLComponents>,
	searchParamsMode: 'replace' | 'merge' = 'replace'
) {
	const currentUrl = new URL(currentLocation);
	if ('searchParams' in urlComponents) {
		if (searchParamsMode === 'replace') {
			currentUrl.search = '';
		}
		if (urlComponents.searchParams !== undefined) {
			Object.entries(urlComponents.searchParams).forEach(
				([key, value]) => {
					if (value === undefined) {
						currentUrl.searchParams.delete(key);
					} else if (Array.isArray(value)) {
						value.forEach((v) => {
							currentUrl.searchParams.append(key, v);
						});
					} else {
						currentUrl.searchParams.set(key, value);
					}
				}
			);
		}
	}
	if ('hash' in urlComponents) {
		if (urlComponents.hash === undefined) {
			currentUrl.hash = '';
		} else {
			currentUrl.hash = urlComponents.hash;
		}
	}
	if (urlComponents.host) {
		currentUrl.host = urlComponents.host;
	}
	if (urlComponents.port) {
		currentUrl.port = urlComponents.port;
	}
	if (urlComponents.protocol) {
		currentUrl.protocol = urlComponents.protocol;
	}
	if (urlComponents.pathname) {
		currentUrl.pathname = urlComponents.pathname;
	}
	return currentUrl.toString();
}

/**
 * Wouter's useLocation hook doesn't reflect the search params or the fragment.
 * This hook does.
 *
 * The code below is copied from wouter's useLocation hook.
 * @see https://github.com/molefrog/wouter
 */

/**
 * History API docs @see https://developer.mozilla.org/en-US/docs/Web/API/History
 */
const eventPopstate = 'popstate';
const eventPushState = 'pushState';
const eventReplaceState = 'replaceState';
const eventHashchange = 'hashchange';
const events = [
	eventPopstate,
	eventPushState,
	eventReplaceState,
	eventHashchange,
];

const subscribeToLocationUpdates = (callback: () => void) => {
	for (const event of events) {
		window.addEventListener(event, callback);
	}
	return () => {
		for (const event of events) {
			window.removeEventListener(event, callback);
		}
	};
};

const patchKey = Symbol.for('wouter_v3');

// While History API does have `popstate` event, the only
// proper way to listen to changes via `push/replaceState`
// is to monkey-patch these methods.
//
// See https://stackoverflow.com/a/4585031
if (
	typeof window !== 'undefined' &&
	typeof window.history !== 'undefined' &&
	typeof window[patchKey as any] === 'undefined'
) {
	for (const type of [eventPushState, eventReplaceState]) {
		const original = window.history[type as keyof History];
		// TODO: we should be using unstable_batchedUpdates to avoid multiple re-renders,
		// however that will require an additional peer dependency on react-dom.
		// See: https://github.com/reactwg/react-18/discussions/86#discussioncomment-1567149
		// @ts-ignore
		window.history[type as keyof History] = function (...args: any[]) {
			const result = original.apply(this, args);
			const event = new Event(type);
			// @ts-ignore
			event.arguments = args;

			window.dispatchEvent(event);
			return result;
		};
	}

	// patch history object only once
	// See: https://github.com/molefrog/wouter/issues/167
	Object.defineProperty(window, patchKey, { value: true });
}
