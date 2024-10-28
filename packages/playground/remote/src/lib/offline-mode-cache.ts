import { isURLScoped } from '@php-wasm/scopes';
// @ts-ignore
import { buildVersion } from 'virtual:remote-config';

const CACHE_NAME_PREFIX = 'playground-cache';
const LATEST_CACHE_NAME = `${CACHE_NAME_PREFIX}-${buildVersion}`;

// We save a top-level Promise because this module is imported by
// a Service Worker module which does not allow top-level await.
const promisedOfflineModeCache = caches.open(LATEST_CACHE_NAME);

export async function cacheFirstFetch(request: Request): Promise<Response> {
	const offlineModeCache = await promisedOfflineModeCache;
	const cachedResponse = await offlineModeCache.match(request, {
		ignoreSearch: true,
	});
	if (cachedResponse) {
		return cachedResponse;
	}

	/**
	 * Ensure the response is not coming from HTTP cache.
	 *
	 * We never want to put a stale asset in CacheStorage as
	 * that would break Playground.
	 *
	 * See service-worker.ts for more details.
	 */
	const response = await fetchFresh(request);
	if (response.ok) {
		/**
		 * Confirm the current service worker is still active
		 * when the asset is fetched. Caching a stale request
		 * from a stale worker has no benefits. It only takes
		 * up space.
		 */
		if (isCurrentServiceWorkerActive()) {
			// Intentionally do not await writing to the cache so the response
			// promise can be returned immediately and observed for progress events.
			// NOTE: This is a race condition for simultaneous requests for the same asset.
			offlineModeCache.put(request, response.clone());
		}
	}

	return response;
}

export async function networkFirstFetch(request: Request): Promise<Response> {
	const offlineModeCache = await promisedOfflineModeCache;
	const cachedResponse = await offlineModeCache.match(request, {
		ignoreSearch: true,
	});

	let response: Response | undefined = undefined;
	try {
		response = await fetch(request, {
			cache: 'no-cache',
		});
	} catch (e) {
		if (cachedResponse) {
			return cachedResponse;
		}
		throw e;
	}

	if (response.ok) {
		// Intentionally do not await writing to the cache so the response
		// promise can be returned immediately and observed for progress events.
		// NOTE: This is a race condition for simultaneous requests for the same asset.
		offlineModeCache.put(request, response.clone());
		return response;
	}

	if (cachedResponse) {
		return cachedResponse;
	}

	return response;
}

/**
 * For offline mode to work we need to cache all required assets.
 *
 * These assets are listed in the `/assets-required-for-offline-mode.json`
 * file and contain JavaScript, CSS, and other assets required to load the
 * site without making any network requests.
 */
export async function cacheOfflineModeAssetsForCurrentRelease(): Promise<any> {
	// Get the cache manifest and add all the files to the cache
	const manifestResponse = await fetchFresh(
		'/assets-required-for-offline-mode.json'
	);
	const requiredOfflineAssetUrls = await manifestResponse.json();
	const urlsToCache = ['/', ...requiredOfflineAssetUrls];
	const websiteRequests = urlsToCache.map(
		/**
		 * Ensure the response is not coming from HTTP cache.
		 *
		 * If it did, we'd risk mixing assets from different
		 * Playground builds and breaking the site.
		 *
		 * See service-worker.ts for more details.
		 */
		(url: string) => new Request(url, { cache: 'no-cache' })
	);
	const offlineModeCache = await promisedOfflineModeCache;
	await offlineModeCache.addAll(websiteRequests);
}

/**
 * Remove outdated files from the cache.
 *
 * We cache data based on `buildVersion` which is updated whenever Playground
 * is built. So when a new version of Playground is deployed, the service
 * worker will remove the old cache and cache the new assets.
 *
 * If your build version doesn't change while developing locally check
 * `buildVersionPlugin` for more details on how it's generated.
 */
export async function purgeEverythingFromPreviousRelease() {
	const keys = await caches.keys();
	const oldKeys = keys.filter(
		(key) => key.startsWith(CACHE_NAME_PREFIX) && key !== LATEST_CACHE_NAME
	);
	return Promise.all(oldKeys.map((key) => caches.delete(key)));
}

/**
 * Answers whether a given URL has a response in the offline mode cache.
 * Ignores the search part of the URL by default.
 */
export async function hasCachedResponse(
	url: string,
	queryOptions: CacheQueryOptions = { ignoreSearch: true }
): Promise<boolean> {
	const offlineModeCache = await promisedOfflineModeCache;
	const cachedResponse = await offlineModeCache.match(url, queryOptions);
	return !!cachedResponse;
}

export function shouldCacheUrl(url: URL) {
	if (url.href.includes('wordpress-static.zip')) {
		return true;
	}
	/**
	 * The development environment uses Vite which doesn't work offline because
	 * it dynamically generates assets. Check the README for offline development
	 * instructions.
	 */
	if (
		url.href.startsWith('http://127.0.0.1:5400/') ||
		url.href.startsWith('http://localhost:5400/') ||
		url.href.startsWith('https://playground.test/') ||
		url.pathname.startsWith('/website-server/')
	) {
		return false;
	}

	/**
	 * Don't cache scoped requests made to the PHP Worker Thread.
	 * They may be static assets, but they may also be PHP files.
	 * We can't tell by the URL, e.g. `/sitemap.xml` can be both.
	 */
	if (isURLScoped(url)) {
		return false;
	}

	/**
	 * Don't cache responses generated by PHP files – they may
	 * change on every request.
	 */
	if (url.pathname.endsWith('.php')) {
		return false;
	}

	/**
	 * Allow only requests to the same hostname to be cached.
	 */
	return self.location.hostname === url.hostname;
}

/**
 * Fetches a resource and avoids stale responses from browser cache.
 *
 * @param resource The resource to fetch.
 * @param init     Optional object containing custom settings.
 * @returns Promise<Response>
 */
function fetchFresh(resource: RequestInfo | URL, init?: RequestInit) {
	return fetch(resource, {
		...init,
		cache: 'no-cache',
	});
}

export function isCurrentServiceWorkerActive() {
	// @ts-ignore
	// Firefox doesn't support serviceWorker.state
	if (!('serviceWorker' in self) || !('state' in self.serviceWorker)) {
		return true;
	}
	// @ts-ignore
	return self.serviceWorker.state === 'activated';
}
