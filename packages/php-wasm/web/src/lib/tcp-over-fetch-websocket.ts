/**
 * This is the secret sauce that enables HTTPS requests from PHP
 * via file_get_contents(), curl, and all other networking mechanisms.
 *
 * This is effectively a MITM attack on the PHP instance. The code
 * in this module decrypts the outbound traffic, runs the request
 * using fetch(), and then provides an encrypted response. From
 * PHP's perspective, it is indistinguishable from a direct connection
 * to the right server.
 *
 * ## How does it work?
 *
 * Emscripten can be configured to stream all network traffic through a
 * WebSocket. `@php-wasm/node` and `wp-now` use that to access the internet
 * via a local WebSocket->TCP proxy, but the in-browser version of WordPress
 * Playground exposes no such proxy.
 *
 * This module implements a "fake" WebSocket class. Instead of starting a `ws://`
 * connection, it translates the raw HTTP/HTTPS bytes into a `fetch()` call.
 *
 * In case of HTTP, the raw request bytes are parsed into a Request object with
 * a body stream and passes it to `fetch()`. Then, as the response status, headers,
 * and the body arrive, they're stream-encoded as raw response bytes and exposed as
 * incoming WebSocket data.
 *
 * In case of HTTPS, we the raw bytes are first piped through a custom TCPConnection
 * class as follows:
 *
 * 1. We generate a self-signed CA certificate and tell PHP to trust it using the
 *    `openssl.cafile` PHP.ini option
 * 2. We create a domain-specific child certificate and sign it with the CA private key.
 * 3. We start accepting raw encrypted bytes, process them as structured TLS records,
 *    and perform the TLS handshake.
 * 4. Encrypted tunnel is established
 *   * TLSConnection decrypts the encrypted outbound data sent by PHP
 *   * TLSConnection encrypts the unencrypted inbound data fed back to PHP
 *
 * From there, the plaintext data is treated by the same HTTP<->fetch() machinery as
 * described in the previous paragraph.
 */
import { TLS_1_2_Connection } from './tls/1_2/connection';
import { generateCertificate, GeneratedCertificate } from './tls/certificates';
import { concatUint8Arrays } from './tls/utils';
import { ContentTypes } from './tls/1_2/types';

export type TCPOverFetchOptions = {
	CAroot: GeneratedCertificate;
};

/**
 * Sets up a WebSocket that analyzes the received bytes and, if they look like
 * TLS or HTTP, handles the network transmission using fetch().
 */
export const tcpOverFetchWebsocket = (tcpOptions: TCPOverFetchOptions) => {
	return {
		websocket: {
			url: (_: any, host: string, port: string) => {
				const query = new URLSearchParams({
					host,
					port,
				}).toString();
				return `ws://playground.internal/?${query}`;
			},
			subprotocol: 'binary',
			decorator: () => {
				return class extends TCPOverFetchWebsocket {
					constructor(url: string, wsOptions: string[]) {
						super(url, wsOptions, {
							CAroot: tcpOptions.CAroot,
						});
					}
				};
			},
		},
	};
};

export interface TCPOverFetchWebsocketOptions {
	CAroot?: GeneratedCertificate;
	/**
	 * If true, the WebSocket will emit 'message' events with the received bytes
	 * and the 'close' event when the WebSocket is closed.
	 *
	 * If false, the consumer will be responsible for reading the bytes from the
	 * clientDownstream stream and tracking the closure of that stream.
	 */
	outputType?: 'messages' | 'stream';
}

export class TCPOverFetchWebsocket {
	CONNECTING = 0;
	OPEN = 1;
	CLOSING = 2;
	CLOSED = 3;
	readyState = this.CONNECTING;
	binaryType = 'blob';
	bufferedAmount = 0;
	extensions = '';
	protocol = 'ws';
	host = '';
	port = 0;
	listeners = new Map<string, any>();
	CAroot?: GeneratedCertificate;

	clientUpstream = new TransformStream();
	clientUpstreamWriter = this.clientUpstream.writable.getWriter();
	clientDownstream = new TransformStream();
	fetchInitiated = false;
	bufferedBytesFromClient: Uint8Array = new Uint8Array(0);

	constructor(
		public url: string,
		public options: string[],
		{ CAroot, outputType = 'messages' }: TCPOverFetchWebsocketOptions = {}
	) {
		const wsUrl = new URL(url);
		this.host = wsUrl.searchParams.get('host')!;
		this.port = parseInt(wsUrl.searchParams.get('port')!, 10);
		this.binaryType = 'arraybuffer';

		this.CAroot = CAroot;
		if (outputType === 'messages') {
			this.clientDownstream.readable
				.pipeTo(
					new WritableStream({
						write: (chunk) => {
							/**
							 * Emscripten expects the message event to be emitted
							 * so let's emit it.
							 */
							this.emit('message', { data: chunk });
						},
						abort: () => {
							// We don't know what went wrong and the browser
							// won't tell us much either, so let's just pretend
							// the server is unreachable.
							this.emit('error', new Error('ECONNREFUSED'));
							this.close();
						},
						close: () => {
							this.close();
						},
					})
				)
				.catch(() => {
					// Ignore failures arising from stream errors.
					// This class communicates problems to the caller
					// via the 'error' event.
				});
		}
		this.readyState = this.OPEN;
		this.emit('open');
	}

	on(eventName: string, callback: (e: any) => void) {
		this.addEventListener(eventName, callback);
	}

	once(eventName: string, callback: (e: any) => void) {
		const wrapper = (e: any) => {
			callback(e);
			this.removeEventListener(eventName, wrapper);
		};
		this.addEventListener(eventName, wrapper);
	}

	addEventListener(eventName: string, callback: (e: any) => void) {
		if (!this.listeners.has(eventName)) {
			this.listeners.set(eventName, new Set());
		}
		this.listeners.get(eventName).add(callback);
	}

	removeListener(eventName: string, callback: (e: any) => void) {
		this.removeEventListener(eventName, callback);
	}

	removeEventListener(eventName: string, callback: (e: any) => void) {
		const listeners = this.listeners.get(eventName);
		if (listeners) {
			listeners.delete(callback);
		}
	}

	emit(eventName: string, data: any = {}) {
		if (eventName === 'message') {
			this.onmessage(data);
		} else if (eventName === 'close') {
			this.onclose(data);
		} else if (eventName === 'error') {
			this.onerror(data);
		} else if (eventName === 'open') {
			this.onopen(data);
		}
		const listeners = this.listeners.get(eventName);
		if (listeners) {
			for (const listener of listeners) {
				listener(data);
			}
		}
	}

	// Default event handlers that can be overridden by the user
	// eslint-disable-next-line @typescript-eslint/no-unused-vars
	onclose(data: any) {}
	// eslint-disable-next-line @typescript-eslint/no-unused-vars
	onerror(data: any) {}
	// eslint-disable-next-line @typescript-eslint/no-unused-vars
	onmessage(data: any) {}
	// eslint-disable-next-line @typescript-eslint/no-unused-vars
	onopen(data: any) {}

	/**
	 * Emscripten calls this method whenever the WASM module
	 * writes bytes to the TCP socket.
	 */
	send(data: ArrayBuffer) {
		if (
			this.readyState === this.CLOSING ||
			this.readyState === this.CLOSED
		) {
			return;
		}

		this.clientUpstreamWriter.write(new Uint8Array(data));

		if (this.fetchInitiated) {
			return;
		}

		// Guess the protocol type first so we can learn
		// what to do with the incoming bytes.
		this.bufferedBytesFromClient = concatUint8Arrays([
			this.bufferedBytesFromClient,
			new Uint8Array(data),
		]);
		switch (guessProtocol(this.port, this.bufferedBytesFromClient)) {
			case false:
				// Not enough data to classify the protocol,
				// let's wait for more.
				return;
			case 'other':
				this.emit('error', new Error('Unsupported protocol'));
				this.close();
				break;
			case 'tls':
				this.fetchOverTLS();
				this.fetchInitiated = true;
				break;
			case 'http':
				this.fetchOverHTTP();
				this.fetchInitiated = true;
				break;
		}
	}

	async fetchOverTLS() {
		if (!this.CAroot) {
			throw new Error(
				'TLS protocol is only supported when the TCPOverFetchWebsocket is ' +
					'instantiated with a CAroot'
			);
		}
		const siteCert = await generateCertificate(
			{
				subject: {
					commonName: this.host,
					organizationName: this.host,
					countryName: 'US',
				},
				issuer: this.CAroot.tbsDescription.subject,
			},
			this.CAroot.keyPair
		);

		const tlsConnection = new TLS_1_2_Connection();

		// Connect this WebSocket's client end to the TLS connection.
		// Forward the encrypted bytes from the WebSocket to the TLS connection.
		this.clientUpstream.readable
			.pipeTo(tlsConnection.clientEnd.upstream.writable)
			.catch(() => {
				// Ignore failures arising from pipeTo() errors.
				// The caller will observe the clientEnd.downstream.writable stream
				// erroring out.
			});

		// Forward the decrypted bytes from the TLS connection to this WebSocket.
		tlsConnection.clientEnd.downstream.readable
			.pipeTo(this.clientDownstream.writable)
			.catch(() => {
				// Ignore failures arising from pipeTo() errors.
				// The caller will observe the clientEnd.downstream.writable stream
				// erroring out.
			});

		// Perform the TLS handshake
		await tlsConnection.TLSHandshake(siteCert.keyPair.privateKey, [
			siteCert.certificate,
			this.CAroot.certificate,
		]);

		// Connect the TLS server end to the fetch() request
		const request = await RawBytesFetch.parseHttpRequest(
			tlsConnection.serverEnd.upstream.readable,
			this.host,
			'https'
		);
		try {
			await RawBytesFetch.fetchRawResponseBytes(request).pipeTo(
				tlsConnection.serverEnd.downstream.writable
			);
		} catch (e) {
			// Ignore errors from fetch()
			// They are handled in the constructor
			// via this.clientDownstream.readable.pipeTo()
			// and if we let the failures they would be logged
			// as an unhandled promise rejection.
		}
	}

	async fetchOverHTTP() {
		// Connect this WebSocket's client end to the fetch() request
		const request = await RawBytesFetch.parseHttpRequest(
			this.clientUpstream.readable,
			this.host,
			'http'
		);
		try {
			await RawBytesFetch.fetchRawResponseBytes(request).pipeTo(
				this.clientDownstream.writable
			);
		} catch (e) {
			// Ignore errors from fetch()
			// They are handled in the constructor
			// via this.clientDownstream.readable.pipeTo()
			// and if we let the failures they would be logged
			// as an unhandled promise rejection.
		}
	}

	close() {
		/**
		 * Workaround a PHP.wasm issue – if the WebSocket is
		 * closed asynchronously after the last chunk is received,
		 * the PHP.wasm runtime enters an infinite polling loop.
		 *
		 * The root cause of the problem is unclear at the time
		 * of writing this comment. There's a chance it's a regular
		 * POSIX behavior.
		 *
		 * Either way, sending an empty data chunk before closing
		 * the WebSocket resolves the problem.
		 */
		this.emit('message', { data: new Uint8Array(0) });

		this.readyState = this.CLOSING;
		this.emit('close');
		this.readyState = this.CLOSED;
	}
}

const HTTP_METHODS = [
	'GET',
	'POST',
	'HEAD',
	'PATCH',
	'OPTIONS',
	'DELETE',
	'PUT',
	'TRACE',
];

function guessProtocol(port: number, data: Uint8Array) {
	if (data.length < 8) {
		// Not enough data to classify the protocol, let's wait for more.
		return false;
	}

	// Assume TLS if we're on the usual HTTPS port and the
	// first three bytes look like a TLS handshake record.
	const looksLikeTls =
		port === 443 &&
		data[0] === ContentTypes.Handshake &&
		// TLS versions between 1.0 and 1.2
		data[1] === 0x03 &&
		data[2] >= 0x01 &&
		data[2] <= 0x03;
	if (looksLikeTls) {
		return 'tls';
	}

	// Assume HTTP if we're on the usual HTTP port and the
	// first starts with an HTTP method and a space.
	const decodedFirstLine = new TextDecoder('latin1', {
		fatal: true,
	}).decode(data);
	const looksLikeHttp = HTTP_METHODS.some((method) =>
		decodedFirstLine.startsWith(method + ' ')
	);
	if (looksLikeHttp) {
		return 'http';
	}

	return 'other';
}

class RawBytesFetch {
	/**
	 * Streams a HTTP response including the status line and headers.
	 */
	static fetchRawResponseBytes(request: Request) {
		// This initially used a TransformStream and piped the response
		// body to the writable side of the TransformStream.
		//
		// Unfortunately, the first response body chunk was not correctly
		// enqueued so we switched to a customReadableStream.
		return new ReadableStream({
			async start(controller) {
				let response: Response;
				try {
					response = await fetch(request);
					controller.enqueue(RawBytesFetch.headersAsBytes(response));
				} catch (error) {
					controller.error(error);
					return;
				}

				const reader = response.body?.getReader();
				if (!reader) {
					controller.close();
					return;
				}

				const encoder = new TextEncoder();
				while (true) {
					const { done, value } = await reader.read();
					if (value) {
						/**
						 * Pass the body stream assuming the response uses
						 * chunked transfer encoding.
						 *
						 * See `headersAsBytes()` for the details.
						 */
						controller.enqueue(
							encoder.encode(`${value.length.toString(16)}\r\n`)
						);
						controller.enqueue(value);
						controller.enqueue(encoder.encode('\r\n'));
					}
					if (done) {
						controller.enqueue(encoder.encode('0\r\n\r\n'));
						controller.close();
						return;
					}
				}
			},
		});
	}

	private static headersAsBytes(response: Response) {
		const status = `HTTP/1.1 ${response.status} ${response.statusText}`;

		const headersObject: Record<string, string> = {};
		response.headers.forEach((value, name) => {
			headersObject[name.toLowerCase()] = value;
		});

		/**
		 * Best-effort attempt to provide the correct content-length
		 * to the PHP-side request handler.
		 *
		 * Web servers often respond with a combination of Content-Length
		 * and Content-Encoding. For example, a 16kb text file may be compressed
		 * to 4kb with gzip and served with a Content-Encoding of `gzip` and a
		 * Content-Length of 4KB.
		 *
		 * The web browser, however, exposes neither the Content-Encoding header
		 * nor the gzipped data stream. All we have access to is the original
		 * Content-Length value of the gzipped file and a decompressed data stream.
		 *
		 * If we just pass that along to the PHP-side request handler, it would
		 * see a 16KB body stream with a Content-Length of 4KB. It would then
		 * truncate the body stream at 4KB and discard the rest of the data.
		 *
		 * This is not what we want.
		 *
		 * To correct that behavior, we're stripping the Content-Length entirely.
		 * We do that for every single response because we don't have any way
		 * of knowing whether any Content-Encoding was used. Furthermore, we can't
		 * just calculate the correct Content-Length value without consuming the
		 * entire content stream – and we want to pass each data chunk to PHP
		 * as we receive it.
		 *
		 * Instead of a fixed Content-Length, we'll use Content-Encoding: Chunked,
		 * and then provide a per-chunk Content-Length. See fetchRawResponseBytes()
		 * for the details.
		 */
		delete headersObject['content-length'];
		headersObject['transfer-encoding'] = 'chunked';

		const headers: string[] = [];
		for (const [name, value] of Object.entries(headersObject)) {
			headers.push(`${name}: ${value}`);
		}
		const string = [status, ...headers].join('\r\n') + '\r\n\r\n';
		return new TextEncoder().encode(string);
	}

	/**
	 * Parses a raw, streamed HTTP request into a Request object
	 * with known headers and a readable body stream.
	 */
	static async parseHttpRequest(
		requestBytesStream: ReadableStream<Uint8Array>,
		host: string,
		protocol: 'http' | 'https'
	) {
		let inputBuffer: Uint8Array = new Uint8Array(0);

		let requestDataExhausted = false;
		let headersEndIndex = -1;
		const requestBytesReader = requestBytesStream.getReader();
		while (headersEndIndex === -1) {
			const { done, value } = await requestBytesReader.read();
			if (done) {
				requestDataExhausted = true;
				break;
			}
			inputBuffer = concatUint8Arrays([inputBuffer, value]);
			// Find the end of the headers (\r\n\r\n). This is
			// not optimal as we may end up scanning the same
			// bytes multiple times, but the overhead is negligible
			// and the code is much simpler this way.
			headersEndIndex = findSequenceInBuffer(
				inputBuffer,
				new Uint8Array([0x0d, 0x0a, 0x0d, 0x0a])
			);
		}
		requestBytesReader.releaseLock();

		const headersBuffer = inputBuffer.slice(0, headersEndIndex);
		const parsedHeaders = RawBytesFetch.parseRequestHeaders(headersBuffer);

		const bodyBytes = inputBuffer.slice(
			headersEndIndex + 4 /* Skip \r\n\r\n */
		);
		let outboundBodyStream: ReadableStream<Uint8Array> | undefined;
		if (parsedHeaders.method !== 'GET') {
			const requestBytesReader = requestBytesStream.getReader();
			outboundBodyStream = new ReadableStream<Uint8Array>({
				async start(controller) {
					if (bodyBytes.length > 0) {
						controller.enqueue(bodyBytes);
					}
					if (requestDataExhausted) {
						controller.close();
					}
				},
				async pull(controller) {
					const { done, value } = await requestBytesReader.read();

					if (value) {
						controller.enqueue(value);
					}
					if (done) {
						controller.close();
						return;
					}
				},
			});
		}

		/**
		 * Prefer the Host header to the host from the URL used in a PHP
		 * function call.
		 *
		 * There are tradeoffs involved in this decision.
		 *
		 * The URL from the PHP function call is the actual network location
		 * the caller intended to reach, e.g. `http://192.168.1.100` or
		 * `http://127.0.0.1`.
		 *
		 * The Host header is what the developer wanted to provide to the
		 * web server, e.g. `wordpress.org` or `localhost`.
		 *
		 * The Host header is not a reliable indication of the target URL.
		 * However, `fetch()` does not support Host spoofing. Furthermore,
		 * a webserver running on 127.0.0.1 may only respond correctly
		 * when it is provided with the Host header `localhost`.
		 *
		 * Prefering the Host header over the host from the PHP function call
		 * is not perfect, but it seems like the lesser of two evils.
		 */
		const hostname = parsedHeaders.headers.get('Host') ?? host;
		const url = new URL(parsedHeaders.path, protocol + '://' + hostname);
		url.pathname = parsedHeaders.path;

		return new Request(url.toString(), {
			method: parsedHeaders.method,
			headers: parsedHeaders.headers,
			body: outboundBodyStream,
			// In Node.js, duplex: 'half' is required when
			// the body stream is provided.
			// @ts-expect-error
			duplex: 'half',
		});
	}

	private static parseRequestHeaders(httpRequestBytes: Uint8Array) {
		const httpRequest = new TextDecoder().decode(httpRequestBytes);
		const statusLineMaybe = httpRequest.split('\n')[0];
		const [method, path] = statusLineMaybe.split(' ');

		const headers = new Headers();
		for (const line of httpRequest.split('\r\n').slice(1)) {
			if (line === '') {
				break;
			}
			const [name, value] = line.split(': ');
			headers.set(name, value);
		}

		return { method, path, headers };
	}
}

function findSequenceInBuffer(
	buffer: Uint8Array,
	sequence: Uint8Array
): number {
	const bufferLength = buffer.length;
	const sequenceLength = sequence.length;
	const lastPossibleIndex = bufferLength - sequenceLength;

	for (let i = 0; i <= lastPossibleIndex; i++) {
		let found = true;
		for (let j = 0; j < sequenceLength; j++) {
			if (buffer[i + j] !== sequence[j]) {
				found = false;
				break;
			}
		}
		if (found) {
			return i;
		}
	}
	return -1;
}
