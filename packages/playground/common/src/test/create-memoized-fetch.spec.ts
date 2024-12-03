import { createMemoizedFetch } from '../create-memoized-fetch';

describe('createMemoizedFetch', () => {
	it('should return a function', () => {
		expect(createMemoizedFetch()).toBeInstanceOf(Function);
	});

	it('should return a response with the same status, headers, and the body stream', async () => {
		const fetch = vitest.fn().mockResolvedValueOnce(
			new Response('hello', {
				status: 200,
				statusText: 'OK',
				headers: { 'Content-type': 'text/plain' },
			})
		);
		const memoizedFetch = createMemoizedFetch(fetch);
		const response1 = await memoizedFetch('https://example.com');
		const response2 = await memoizedFetch('https://example.com');
		expect(response1.status).toBe(response2.status);
		expect(response1.headers.get('Content-type')).toBe(
			response2.headers.get('Content-type')
		);
		expect(await response1.text()).toBe(await response2.text());
		expect(fetch).toHaveBeenCalledTimes(1);
	});
});
