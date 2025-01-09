import express, { Request } from 'express';
import { PHPRequest, PHPResponse } from '@php-wasm/universal';
import { IncomingMessage, Server, ServerResponse } from 'http';
import { AddressInfo } from 'net';

export interface ServerOptions {
	port: number;
	onBind: (port: number) => Promise<any>;
	handleRequest: (request: PHPRequest) => Promise<PHPResponse>;
}

export async function startServer(options: ServerOptions) {
	Bun.serve({
		port: options.port,
		async fetch(req: Request, res: Response) {
			const phpResponse = await options.handleRequest({
				url: req.url,
				headers: parseHeaders(req),
				method: req.method as any,
				body: await bufferRequestBody(req),
			});

			return new Response(phpResponse.bytes, {
				status: phpResponse.httpStatusCode,
				headers: phpResponse.headers,
			});
		},
	});
	await new Promise((resolve) => {
		setTimeout(() => {
			resolve(true);
		}, 1000);
	});
	await options.onBind(options.port);
}

const bufferRequestBody = async (req: Request): Promise<Uint8Array> =>
	new Uint8Array(await req.arrayBuffer());
// await new Promise((resolve) => {
// 	const body: Uint8Array[] = [];
// 	req.on('data', (chunk) => {
// 		body.push(chunk);
// 	});
// 	req.on('end', () => {
// 		resolve(Buffer.concat(body));
// 	});
// });

const parseHeaders = (req: Request): Record<string, string> => {
	const requestHeaders: Record<string, string> = {};
	for (const [key, value] of req.headers.entries()) {
		requestHeaders[key.toLowerCase()] = value;
	}
	return requestHeaders;
};
