import { TCPOverFetchWebsocket } from './tcp-over-fetch-websocket';
import express from 'express';
import http from 'http';
import { AddressInfo } from 'net';
import zlib from 'zlib';

const pygmalion = `PREFACE TO PYGMALION.

A Professor of Phonetics.

As will be seen later on, Pygmalion needs, not a preface, but a sequel,
which I have supplied in its due place. The English have no respect for
their language, and will not teach their children to speak it. They
spell it so abominably that no man can teach himself what it sounds
like. It is impossible for an Englishman to open his mouth without
making some other Englishman hate or despise him. German and Spanish
are accessible to foreigners: English is not accessible even to
Englishmen. The reformer England needs today is an energetic phonetic
enthusiast: that is why I have made such a one the hero of a popular
play. There have been heroes of that kind crying in the wilderness for
many years past. When I became interested in the subject towards the
end of the eighteen-seventies, Melville Bell was dead; but Alexander J.
Ellis was still a living patriarch, with an impressive head always
covered by a velvet skull cap, for which he would apologize to public
meetings in a very courtly manner. He and Tito Pagliardini, another
phonetic veteran, were men whom it was impossible to dislike. Henry
Sweet, then a young man, lacked their sweetness of character: he was
about as conciliatory to conventional mortals as Ibsen or Samuel
Butler. His great ability as a phonetician (he was, I think, the best
of them all at his job) would have entitled him to high official
recognition, and perhaps enabled him to popularize his subject, but for
his Satanic contempt for all academic dignitaries and persons in
general who thought more of Greek than of phonetics. Once, in the days
when the Imperial Institute rose in South Kensington, and Joseph
Chamberlain was booming the Empire, I induced the editor of a leading
monthly review to commission an article from Sweet on the imperial
importance of his subject. When it arrived, it contained nothing but a
savagely derisive attack on a professor of language and literature
whose chair Sweet regarded as proper to a phonetic expert only. The
article, being libelous, had to be returned as impossible; and I had to
renounce my dream of dragging its author into the limelight. When I met
him afterwards, for the first time for many years, I found to my
astonishment that he, who had been a quite tolerably presentable young
man, had actually managed by sheer scorn to alter his personal
appearance until he had become a sort of walking repudiation of Oxford
and all its traditions. It must have been largely in his own despite
that he was squeezed into something called a Readership of phonetics
there. The future of phonetics rests probably with his pupils, who all
swore by him; but nothing could bring the man himself into any sort of
compliance with the university, to which he nevertheless clung by
divine right in an intensely Oxonian way. I daresay his papers, if he
has left any, include some satires that may be published without too
destructive results fifty years hence. He was, I believe, not in the
least an ill-natured man: very much the opposite, I should say; but he
would not suffer fools gladly.`;

describe('TCPOverFetchWebsocket', () => {
	let server: http.Server;
	let host: string;
	let port: number;

	beforeAll(async () => {
		const app = express();
		server = app.listen(0);
		const address = server.address() as AddressInfo;
		host = `127.0.0.1`;
		port = address.port;
		app.get('/simple', (req, res) => {
			res.send('Hello, World!');
		});

		app.get('/slow', (req, res) => {
			setTimeout(() => {
				res.send('Slow response');
			}, 1000);
		});

		app.get('/stream', (req, res) => {
			res.flushHeaders();
			res.write('Part 1');
			setTimeout(() => {
				res.write('Part 2');
				res.end();
			}, 1500);
		});

		app.get('/headers', (req, res) => {
			res.set('X-Custom-Header', 'TestValue');
			res.send('OK');
		});

		app.get('/gzipped', (req, res) => {
			const gzip = zlib.createGzip();
			gzip.write(pygmalion);
			gzip.end();

			const gzippedChunks: Uint8Array[] = [];
			gzip.on('data', (chunk) => {
				gzippedChunks.push(chunk);
			});
			gzip.on('end', () => {
				const length = gzippedChunks.reduce(
					(acc, chunk) => acc + chunk.length,
					0
				);
				res.setHeader('Content-Encoding', 'gzip');
				res.setHeader('Content-Length', length.toString());
				for (const chunk of gzippedChunks) {
					res.write(chunk);
				}
				res.end();
			});
		});

		app.post('/echo', (req, res) => {
			// Set appropriate headers
			res.setHeader(
				'Content-Type',
				req.headers['content-type'] || 'text/plain'
			);
			res.setHeader('Transfer-Encoding', 'chunked');
			// Create readable stream from request body
			const stream = req;

			// Pipe the input stream directly to the response
			stream.pipe(res);

			// Handle errors
			stream.on('error', (error) => {
				console.error('Stream error:', error);
				res.status(500).end();
			});
		});

		app.get('/error', (req, res) => {
			res.status(500).send('Internal Server Error');
		});
	});

	afterAll(() => {
		server.close();
	});

	it('should handle a simple HTTP request', async () => {
		const socket = await makeRequest({
			host,
			port,
			path: '/simple',
			outputType: 'stream',
		});
		const response = await bufferResponse(socket);
		expect(response).toContain('HTTP/1.1 200 OK');
		expect(response).toContain('Hello, World!');
	});

	it('should handle a slow response', async () => {
		const socket = await makeRequest({
			host,
			port,
			path: '/slow',
			outputType: 'stream',
		});
		const response = await bufferResponse(socket);
		expect(response).toContain('HTTP/1.1 200 OK');
		expect(response).toContain('Slow response');
	});

	it('should handle a streaming response', async () => {
		const socket = await makeRequest({
			host,
			port,
			path: '/stream',
			outputType: 'stream',
		});
		const response = await bufferResponse(socket);
		expect(response).toContain('HTTP/1.1 200 OK');
		expect(response).toContain('Part 1');
		expect(response).toContain('Part 2');
	});

	it('should handle an error response', async () => {
		const socket = await makeRequest({
			host,
			port,
			path: '/error',
			outputType: 'stream',
		});
		const response = await bufferResponse(socket);
		expect(response).toContain('HTTP/1.1 500 Internal Server Error');
		expect(response).toContain('Internal Server Error');
	});

	it('should handle a request with a large payload', async () => {
		const largePayload = 'X'.repeat(1024 * 1024); // 1MB of data
		const socket = await makeRequest({
			host,
			port,
			path: '/echo',
			method: 'POST',
			body: largePayload,
			outputType: 'stream',
		});
		const response = await bufferResponse(socket);
		expect(response).toContain('HTTP/1.1 200 OK');
		expect(response.length).toBeGreaterThanOrEqual(largePayload.length);
	});

	it('should handle a basic POST request', async () => {
		const socket = await makeRequest({
			host,
			port,
			path: '/echo',
			method: 'POST',
			body: 'Hello, World!',
			outputType: 'stream',
		});
		const response = await bufferResponse(socket);
		expect(response).toContain('HTTP/1.1 200 OK');
		expect(response).toContain('Hello, World!');
	});

	it('should handle a request with paused streaming', async () => {
		const socket = new TCPOverFetchWebsocket(
			`ws://playground.internal/?host=${host}&port=${port}`,
			[],
			{ outputType: 'stream' }
		);
		const headers = `POST /echo HTTP/1.1\r\nHost: ${host}:${port}\r\nContent-Length: 18\r\n\r\n`;
		socket.send(new TextEncoder().encode(headers));
		socket.send(new TextEncoder().encode(`Part 1`));

		const responseStream = socket.clientDownstream.readable.pipeThrough(
			new TextDecoderStream()
		);

		const reader = responseStream.getReader();

		const responseHeaders = await reader.read();
		expect(responseHeaders.value).toContain('HTTP/1.1 200 OK');
		expect(responseHeaders.done).toBe(false);

		await reader.read(); // Skip the chunk length (Transfer-Encoding: chunked)
		const responseBodyPart1 = await reader.read();
		await reader.read(); // Skip the chunk delimiter
		expect(responseBodyPart1.value).toContain('Part 1');
		expect(responseBodyPart1.done).toBe(false);

		// Wait for a bit, ensure the connection remains open
		await new Promise((resolve) => setTimeout(resolve, 500));

		socket.send(new TextEncoder().encode(`Part 2`));
		await reader.read(); // Skip the chunk length
		const responseBodyPart2 = await reader.read();
		await reader.read(); // Skip the chunk delimiter
		expect(responseBodyPart2.value).toContain('Part 2');
		expect(responseBodyPart2.done).toBe(false);

		socket.send(new TextEncoder().encode(`Part 3`));
		await reader.read(); // Skip the chunk length
		const responseBodyPart3 = await reader.read();
		await reader.read(); // Skip the chunk delimiter
		expect(responseBodyPart3.done).toBe(false);
		expect(responseBodyPart3.value).toContain('Part 3');

		await reader.read(); // Skip the final empty chunk
		const responseBodyPart4 = await reader.read();
		expect(responseBodyPart4.done).toBe(true);
	});

	it('should handle a non-existent endpoint', async () => {
		const socket = await makeRequest({
			host,
			port,
			path: '/non-existent',
			outputType: 'stream',
		});
		const response = await bufferResponse(socket);
		expect(response).toContain('HTTP/1.1 404 Not Found');
	});

	it('should handle a malformed request', async () => {
		const socket = new TCPOverFetchWebsocket(
			`ws://playground.internal/?host=${host}&port=${port}`,
			[]
		);

		const promise = new Promise((resolve) => {
			socket.on('error', (error) => {
				resolve(error);
			});
		});
		socket.send(new TextEncoder().encode('INVALID REQUEST\r\n\r\n'));
		expect(promise).resolves.toEqual(new Error('Unsupported protocol'));
	});

	it('should handle connection to a non-existent server', async () => {
		const badHost = 'non-existent-server.local';
		const badPort = 1;
		const socket = new TCPOverFetchWebsocket(
			`ws://playground.internal/?host=${badHost}&port=${badPort}`,
			[]
		);
		const promise = new Promise((resolve) => {
			socket.on('error', (error) => {
				resolve(error);
			});
		});
		const request = `GET /non-existent HTTP/1.1\r\nHost: ${badHost}:${badPort}\r\n\r\n`;
		socket.send(new TextEncoder().encode(request));

		await expect(promise).resolves.toEqual(new Error('ECONNREFUSED'));
	});

	it('should handle a request with custom headers', async () => {
		const socket = await makeRequest({
			host,
			port,
			path: '/headers',
			method: 'GET',
			additionalHeaders: 'X-Custom-Header: TestValue\r\n',
			outputType: 'stream',
		});
		const response = await bufferResponse(socket);
		expect(response).toContain('HTTP/1.1 200 OK');
		expect(response).toContain('x-custom-header: TestValue');
	});

	it('should handle a gzipped response', async () => {
		const socket = await makeRequest({
			host,
			port,
			path: '/gzipped',
			outputType: 'stream',
		});
		const response = await bufferResponse(socket);
		expect(response).toContain('HTTP/1.1 200 OK');
		// Confirm we're using transfer-encoding: chunked
		expect(response).not.toContain('content-length');
		expect(response).toContain('transfer-encoding: chunked');

		// Confirm the response is not truncated
		expect(response.length).toBeGreaterThan(pygmalion.length);
		expect(response).toContain(pygmalion.slice(-100));
	});
});

type MakeRequestOptions = {
	host: string;
	port: number;
	path: string;
	method?: string;
	body?: string;
	additionalHeaders?: string;
	outputType?: 'messages' | 'stream';
};
async function makeRequest({
	host,
	port,
	path,
	method = 'GET',
	body = '',
	additionalHeaders = '',
	outputType = 'messages',
}: MakeRequestOptions) {
	const socket = new TCPOverFetchWebsocket(
		`ws://playground.internal/?host=${host}&port=${port}`,
		[],
		{ outputType }
	);
	const request = `${method} ${path} HTTP/1.1\r\nHost: ${host}:${port}\r\n${additionalHeaders}${
		body ? `Content-Length: ${body.length}\r\n` : ''
	}\r\n${body}`;
	socket.send(new TextEncoder().encode(request));
	return socket;
}

async function bufferResponse(socket: TCPOverFetchWebsocket): Promise<string> {
	return new Promise((resolve) => {
		let response = '';
		socket.clientDownstream.readable.pipeTo(
			new WritableStream({
				write(chunk) {
					response += new TextDecoder().decode(chunk);
				},
				close() {
					resolve(response);
				},
			})
		);
	});
}
