export interface CachedFetchResponse {
	body: ReadableStream<Uint8Array>;
	responseInit: ResponseInit;
}

/**
 * Creates a fetch function that memoizes the response stream.
 * Calling it twice will return a response with the same status,
 * headers, and the body stream.
 * Memoization is keyed by URL. Method, headers etc are ignored.
 *
 * @param originalFetch The fetch function to memoize. Defaults to the global fetch.
 */
export function createMemoizedFetch(originalFetch = fetch) {
	const cache: Record<
		string,
		Promise<CachedFetchResponse> | CachedFetchResponse
	> = {};

	return async function memoizedFetch(url: string, options?: RequestInit) {
		if (!cache[url]) {
			// Write to cache synchronously to avoid duplicate requests.
			cache[url] = originalFetch(url, options).then((response) => ({
				body: response.body!,
				responseInit: {
					status: response.status,
					statusText: response.statusText,
					headers: response.headers,
				},
			}));
		}
		const { body, responseInit } = await cache[url];
		// Split the response stream so that the cached one is not consumed.
		const [left, right] = body.tee();
		// Cache the "left" stream and don't use it until the next .tee().
		cache[url] = {
			body: left,
			responseInit,
		};
		// Return the "right" stream for consumption.
		return new Response(right, responseInit);
	};
}
