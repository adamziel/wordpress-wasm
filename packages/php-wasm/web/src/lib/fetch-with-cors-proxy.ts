import { cloneRequest } from '@php-wasm/web-service-worker';

export async function fetchWithCorsProxy(
	input: RequestInfo,
	init?: RequestInit,
	corsProxyUrl?: string
): Promise<Response> {
	const directFetch = fetch(input, init);
	if (!corsProxyUrl) {
		return directFetch;
	}

	try {
		return await directFetch;
	} catch {
		let newInput: string | Request;
		if (typeof input === 'string' || input instanceof URL) {
			newInput = `${corsProxyUrl}${input}`;
		} else if (input instanceof Request) {
			newInput = await cloneRequest(input, {
				url: `${corsProxyUrl}${input.url}`,
			});
		} else {
			throw new Error('Invalid input type for fetch');
		}

		return fetch(newInput, init);
	}
}
