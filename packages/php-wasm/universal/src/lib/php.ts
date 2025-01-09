import { PHPResponse } from './php-response';
import { getLoadedRuntime } from './load-php-runtime';
import type { PHPRuntimeId } from './load-php-runtime';
import {
	MessageListener,
	PHPRequest,
	PHPRequestHeaders,
	PHPRunOptions,
	SpawnHandler,
	PHPEventListener,
	PHPEvent,
} from './universal-php';
import { RmDirOptions, ListFilesOptions, FSHelpers } from './fs-helpers';
import {
	getFunctionsMaybeMissingFromAsyncify,
	improveWASMErrorReporting,
	UnhandledRejectionsTarget,
} from './wasm-error-reporting';
import { Semaphore, createSpawnHandler, joinPaths } from '@php-wasm/util';
import { PHPRequestHandler } from './php-request-handler';
import { logger } from '@php-wasm/logger';
import { isExitCodeZero } from './is-exit-code-zero';
import { Emscripten } from './emscripten-types';

const STRING = 'string';
const NUMBER = 'number';

export const __private__dont__use = Symbol('__private__dont__use');

export class PHPExecutionFailureError extends Error {
	constructor(
		message: string,
		public response: PHPResponse,
		public source: 'request' | 'php-wasm'
	) {
		super(message);
	}
}

export type UnmountFunction = (() => Promise<any>) | (() => any);
export type MountHandler = (
	php: PHP,
	FS: Emscripten.RootFS,
	vfsMountPoint: string
) => UnmountFunction | Promise<UnmountFunction>;

export const PHP_INI_PATH = '/internal/shared/php.ini';
const AUTO_PREPEND_SCRIPT = '/internal/shared/auto_prepend_file.php';

type MountObject = {
	mountHandler: MountHandler;
	unmount: () => Promise<any>;
};

/**
 * An environment-agnostic wrapper around the Emscripten PHP runtime
 * that universals the super low-level API and provides a more convenient
 * higher-level API.
 *
 * It exposes a minimal set of methods to run PHP scripts and to
 * interact with the PHP filesystem.
 */
export class PHP implements Disposable {
	protected [__private__dont__use]: any;
	#sapiName?: string;
	#webSapiInitialized = false;
	#wasmErrorsTarget: UnhandledRejectionsTarget | null = null;
	#eventListeners: Map<string, Set<PHPEventListener>> = new Map();
	#messageListeners: MessageListener[] = [];
	#mounts: Record<string, MountObject> = {};
	requestHandler?: PHPRequestHandler;

	/**
	 * An exclusive lock that prevent multiple requests from running at
	 * the same time.
	 */
	semaphore: Semaphore;

	/**
	 * Initializes a PHP runtime.
	 *
	 * @internal
	 * @param  PHPRuntime - Optional. PHP Runtime ID as initialized by loadPHPRuntime.
	 * @param  requestHandlerOptions - Optional. Options for the PHPRequestHandler. If undefined, no request handler will be initialized.
	 */
	constructor(PHPRuntimeId?: PHPRuntimeId) {
		this.semaphore = new Semaphore({ concurrency: 1 });
		if (PHPRuntimeId !== undefined) {
			this.initializeRuntime(PHPRuntimeId);
		}
	}

	/**
	 * Adds an event listener for a PHP event.
	 * @param eventType - The type of event to listen for.
	 * @param listener - The listener function to be called when the event is triggered.
	 */
	addEventListener(eventType: PHPEvent['type'], listener: PHPEventListener) {
		if (!this.#eventListeners.has(eventType)) {
			this.#eventListeners.set(eventType, new Set());
		}
		this.#eventListeners.get(eventType)!.add(listener);
	}

	/**
	 * Removes an event listener for a PHP event.
	 * @param eventType - The type of event to remove the listener from.
	 * @param listener - The listener function to be removed.
	 */
	removeEventListener(
		eventType: PHPEvent['type'],
		listener: PHPEventListener
	) {
		this.#eventListeners.get(eventType)?.delete(listener);
	}

	dispatchEvent<Event extends PHPEvent>(event: Event) {
		const listeners = this.#eventListeners.get(event.type);
		if (!listeners) {
			return;
		}

		for (const listener of listeners) {
			listener(event);
		}
	}

	/**
	 * Listens to message sent by the PHP code.
	 *
	 * To dispatch messages, call:
	 *
	 *     post_message_to_js(string $data)
	 *
	 *     Arguments:
	 *         $data (string) – Data to pass to JavaScript.
	 *
	 * @example
	 *
	 * ```ts
	 * const php = await PHP.load('8.0');
	 *
	 * php.onMessage(
	 *     // The data is always passed as a string
	 *     function (data: string) {
	 *         // Let's decode and log the data:
	 *         console.log(JSON.parse(data));
	 *     }
	 * );
	 *
	 * // Now that we have a listener in place, let's
	 * // dispatch a message:
	 * await php.run({
	 *     code: `<?php
	 *         post_message_to_js(
	 *             json_encode([
	 *                 'post_id' => '15',
	 *                 'post_title' => 'This is a blog post!'
	 *             ])
	 *         ));
	 *     `,
	 * });
	 * ```
	 *
	 * @param listener Callback function to handle the message.
	 */
	onMessage(listener: MessageListener) {
		this.#messageListeners.push(listener);
		return async () => {
			this.#messageListeners = this.#messageListeners.filter(
				(l) => l !== listener
			);
		};
	}

	async setSpawnHandler(handler: SpawnHandler | string) {
		if (typeof handler === 'string') {
			// This workaround is needed because the
			// Comlink messaging library used by Playground
			// has a hard time serializing a composite
			// handler object.
			// @TODO: Don't eval text-based functions here. Instead
			//        use a MessagePort to communicate with the
			//		  parent context.
			// Perhaps this library would be useful:
			// https://github.com/WebReflection/coincident/
			handler = createSpawnHandler(eval(handler));
		}
		this[__private__dont__use].spawnProcess = handler;
	}

	/** @deprecated Use PHPRequestHandler instead. */
	get absoluteUrl() {
		return this.requestHandler!.absoluteUrl;
	}

	/** @deprecated Use PHPRequestHandler instead. */
	get documentRoot() {
		return this.requestHandler!.documentRoot;
	}

	/** @deprecated Use PHPRequestHandler instead. */
	pathToInternalUrl(path: string): string {
		return this.requestHandler!.pathToInternalUrl(path);
	}

	/** @deprecated Use PHPRequestHandler instead. */
	internalUrlToPath(internalUrl: string): string {
		return this.requestHandler!.internalUrlToPath(internalUrl);
	}

	initializeRuntime(runtimeId: PHPRuntimeId) {
		if (this[__private__dont__use]) {
			throw new Error('PHP runtime already initialized.');
		}
		const runtime = getLoadedRuntime(runtimeId);
		if (!runtime) {
			throw new Error('Invalid PHP runtime id.');
		}
		this[__private__dont__use] = runtime;
		this[__private__dont__use].ccall(
			'wasm_set_phpini_path',
			null,
			['string'],
			[PHP_INI_PATH]
		);

		if (!this.fileExists(PHP_INI_PATH)) {
			this.writeFile(
				PHP_INI_PATH,
				[
					'auto_prepend_file=' + AUTO_PREPEND_SCRIPT,
					'memory_limit=256M',
					'ignore_repeated_errors = 1',
					'error_reporting = E_ALL',
					'display_errors = 1',
					'html_errors = 1',
					'display_startup_errors = On',
					'log_errors = 1',
					'always_populate_raw_post_data = -1',
					'upload_max_filesize = 2000M',
					'post_max_size = 2000M',
					'allow_url_fopen = On',
					'allow_url_include = Off',
					'session.save_path = /home/web_user',
					'implicit_flush = 1',
					'output_buffering = 0',
					'max_execution_time = 0',
					'max_input_time = -1',
				].join('\n')
			);
		}
		if (!this.fileExists(AUTO_PREPEND_SCRIPT)) {
			this.writeFile(
				AUTO_PREPEND_SCRIPT,
				`<?php
				// Define constants set via defineConstant() calls
				if(file_exists('/internal/shared/consts.json')) {
					$consts = json_decode(file_get_contents('/internal/shared/consts.json'), true);
					foreach ($consts as $const => $value) {
						if (!defined($const) && is_scalar($value)) {
							define($const, $value);
						}
					}
				}
				// Preload all the files from /internal/shared/preload
				foreach (glob('/internal/shared/preload/*.php') as $file) {
					require_once $file;
				}
				`
			);
		}

		runtime['onMessage'] = async (
			data: string
		): Promise<string | Uint8Array> => {
			for (const listener of this.#messageListeners) {
				const returnData = await listener(data);

				if (returnData) {
					return returnData;
				}
			}

			return '';
		};

		this.#wasmErrorsTarget = improveWASMErrorReporting(runtime);
		this.dispatchEvent({
			type: 'runtime.initialized',
		});
	}

	/** @inheritDoc */
	async setSapiName(newName: string) {
		const result = this[__private__dont__use].ccall(
			'wasm_set_sapi_name',
			NUMBER,
			[STRING],
			[newName]
		);
		if (result !== 0) {
			throw new Error(
				'Could not set SAPI name. This can only be done before the PHP WASM module is initialized.' +
					'Did you already dispatch any requests?'
			);
		}
		this.#sapiName = newName;
	}

	/**
	 * Changes the current working directory in the PHP filesystem.
	 * This is the directory that will be used as the base for relative paths.
	 * For example, if the current working directory is `/root/php`, and the
	 * path is `data`, the absolute path will be `/root/php/data`.
	 *
	 * @param  path - The new working directory.
	 */
	chdir(path: string) {
		this[__private__dont__use].FS.chdir(path);
	}

	/**
	 * Do not use. Use new PHPRequestHandler() instead.
	 * @deprecated
	 */
	async request(request: PHPRequest): Promise<PHPResponse> {
		logger.warn(
			'PHP.request() is deprecated. Please use new PHPRequestHandler() instead.'
		);
		if (!this.requestHandler) {
			throw new Error('No request handler available.');
		}
		return this.requestHandler.request(request);
	}

	/**
	 * Runs PHP code.
	 *
	 * This low-level method directly interacts with the WebAssembly
	 * PHP interpreter.
	 *
	 * Every time you call run(), it prepares the PHP
	 * environment and:
	 *
	 * * Resets the internal PHP state
	 * * Populates superglobals ($_SERVER, $_GET, etc.)
	 * * Handles file uploads
	 * * Populates input streams (stdin, argv, etc.)
	 * * Sets the current working directory
	 *
	 * You can use run() in two primary modes:
	 *
	 * ### Code snippet mode
	 *
	 * In this mode, you pass a string containing PHP code to run.
	 *
	 * ```ts
	 * const result = await php.run({
	 * 	code: `<?php echo "Hello world!";`
	 * });
	 * // result.text === "Hello world!"
	 * ```
	 *
	 * In this mode, information like __DIR__ or __FILE__ isn't very
	 * useful because the code is not associated with any file.
	 *
	 * Under the hood, the PHP snippet is passed to the `zend_eval_string`
	 * C function.
	 *
	 * ### File mode
	 *
	 * In the file mode, you pass a scriptPath and PHP executes a file
	 * found at a that path:
	 *
	 * ```ts
	 * php.writeFile(
	 * 	"/www/index.php",
	 * 	`<?php echo "Hello world!";"`
	 * );
	 * const result = await php.run({
	 * 	scriptPath: "/www/index.php"
	 * });
	 * // result.text === "Hello world!"
	 * ```
	 *
	 * In this mode, you can rely on path-related information like __DIR__
	 * or __FILE__.
	 *
	 * Under the hood, the PHP file is executed with the `php_execute_script`
	 * C function.
	 *
	 * The `run()` method cannot be used in conjunction with `cli()`.
	 *
	 * @example
	 * ```js
	 * const result = await php.run(`<?php
	 *  $fp = fopen('php://stderr', 'w');
	 *  fwrite($fp, "Hello, world!");
	 * `);
	 * // result.errors === "Hello, world!"
	 * ```
	 *
	 * @param  options - PHP runtime options.
	 */
	async run(request: PHPRunOptions): Promise<PHPResponse> {
		/*
		 * Prevent multiple requests from running at the same time.
		 * For example, if a request is made to a PHP file that
		 * requests another PHP file, the second request may
		 * be dispatched before the first one is finished.
		 */
		const release = await this.semaphore.acquire();
		let heapBodyPointer;
		try {
			if (!this.#webSapiInitialized) {
				this.#initWebRuntime();
				this.#webSapiInitialized = true;
			}
			if (request.scriptPath && !this.fileExists(request.scriptPath)) {
				throw new Error(
					`The script path "${request.scriptPath}" does not exist.`
				);
			}
			this.#setRelativeRequestUri(request.relativeUri || '');
			this.#setRequestMethod(request.method || 'GET');
			const headers = normalizeHeaders(request.headers || {});
			const host = headers['host'] || 'example.com:443';

			const port = this.#inferPortFromHostAndProtocol(
				host,
				request.protocol || 'http'
			);
			this.#setRequestHost(host);
			this.#setRequestPort(port);
			this.#setRequestHeaders(headers);
			if (request.body) {
				heapBodyPointer = this.#setRequestBody(request.body);
			}
			if (typeof request.code === 'string') {
				this.writeFile('/internal/eval.php', request.code);
				this.#setScriptPath('/internal/eval.php');
			} else if (typeof request.scriptPath === 'string') {
				this.#setScriptPath(request.scriptPath || '');
			} else {
				throw new TypeError(
					'The request object must have either a `code` or a ' +
						'`scriptPath` property.'
				);
			}

			const $_SERVER = this.#prepareServerEntries(
				request.$_SERVER,
				headers,
				port
			);
			for (const key in $_SERVER) {
				this.#setServerGlobalEntry(key, $_SERVER[key]);
			}

			const env = request.env || {};
			for (const key in env) {
				this.#setEnv(key, env[key]);
			}

			const response = await this.#handleRequest();
			if (response.exitCode !== 0) {
				logger.warn(`PHP.run() output was:`, response.text);
				const error = new PHPExecutionFailureError(
					`PHP.run() failed with exit code ${response.exitCode} and the following output: ` +
						response.errors,
					response,
					'request'
				) as PHPExecutionFailureError;
				logger.error(error);
				throw error;
			}
			return response;
		} catch (e) {
			this.dispatchEvent({
				type: 'request.error',
				error: e as Error,
				// Distinguish between PHP request and PHP-wasm errors
				source: (e as any).source ?? 'php-wasm',
			});
			throw e;
		} finally {
			try {
				if (heapBodyPointer) {
					this[__private__dont__use].free(heapBodyPointer);
				}
			} finally {
				release();
				this.dispatchEvent({
					type: 'request.end',
				});
			}
		}
	}

	/**
	 * Prepares the $_SERVER entries for the PHP runtime.
	 *
	 * @param defaults Default entries to include in $_SERVER.
	 * @param headers HTTP headers to include in $_SERVER (as HTTP_ prefixed entries).
	 * @param port HTTP port, used to determine infer $_SERVER['HTTPS'] value if none
	 *             was provided.
	 * @returns Computed $_SERVER entries.
	 */
	#prepareServerEntries(
		defaults: Record<string, string> | undefined,
		headers: PHPRequestHeaders,
		port: number
	): Record<string, string> {
		const $_SERVER = {
			...(defaults || {}),
		};
		$_SERVER['HTTPS'] = $_SERVER['HTTPS'] || port === 443 ? 'on' : 'off';
		for (const name in headers) {
			let HTTP_prefix = 'HTTP_';
			/**
			 * Some headers are special and don't have the HTTP_ prefix.
			 */
			if (
				['content-type', 'content-length'].includes(name.toLowerCase())
			) {
				HTTP_prefix = '';
			}
			$_SERVER[`${HTTP_prefix}${name.toUpperCase().replace(/-/g, '_')}`] =
				headers[name];
		}
		return $_SERVER;
	}

	#initWebRuntime() {
		this[__private__dont__use].ccall('php_wasm_init', null, [], []);
	}

	#getResponseHeaders(): {
		headers: PHPResponse['headers'];
		httpStatusCode: number;
	} {
		const headersFilePath = '/internal/headers.json';
		if (!this.fileExists(headersFilePath)) {
			throw new Error(
				'SAPI Error: Could not find response headers file.'
			);
		}

		const headersData = JSON.parse(this.readFileAsText(headersFilePath));
		const headers: PHPResponse['headers'] = {};
		for (const line of headersData.headers) {
			if (!line.includes(': ')) {
				continue;
			}
			const colonIndex = line.indexOf(': ');
			const headerName = line.substring(0, colonIndex).toLowerCase();
			const headerValue = line.substring(colonIndex + 2);
			if (!(headerName in headers)) {
				headers[headerName] = [] as string[];
			}
			headers[headerName].push(headerValue);
		}
		return {
			headers,
			httpStatusCode: headersData.status,
		};
	}

	#setRelativeRequestUri(uri: string) {
		this[__private__dont__use].ccall(
			'wasm_set_request_uri',
			null,
			[STRING],
			[uri]
		);
		if (uri.includes('?')) {
			const queryString = uri.substring(uri.indexOf('?') + 1);
			this[__private__dont__use].ccall(
				'wasm_set_query_string',
				null,
				[STRING],
				[queryString]
			);
		}
	}

	#setRequestHost(host: string) {
		this[__private__dont__use].ccall(
			'wasm_set_request_host',
			null,
			[STRING],
			[host]
		);
	}

	#setRequestPort(port: number) {
		this[__private__dont__use].ccall(
			'wasm_set_request_port',
			null,
			[NUMBER],
			[port]
		);
	}

	#inferPortFromHostAndProtocol(host: string, protocol: string) {
		let port;
		try {
			port = parseInt(new URL(host).port, 10);
		} catch (e) {
			// ignore
		}

		if (!port || isNaN(port) || port === 80) {
			port = protocol === 'https' ? 443 : 80;
		}
		return port;
	}

	#setRequestMethod(method: string) {
		this[__private__dont__use].ccall(
			'wasm_set_request_method',
			null,
			[STRING],
			[method]
		);
	}

	#setRequestHeaders(headers: PHPRequestHeaders) {
		if (headers['cookie']) {
			this[__private__dont__use].ccall(
				'wasm_set_cookies',
				null,
				[STRING],
				[headers['cookie']]
			);
		}
		if (headers['content-type']) {
			this[__private__dont__use].ccall(
				'wasm_set_content_type',
				null,
				[STRING],
				[headers['content-type']]
			);
		}
		if (headers['content-length']) {
			this[__private__dont__use].ccall(
				'wasm_set_content_length',
				null,
				[NUMBER],
				[parseInt(headers['content-length'], 10)]
			);
		}
	}

	#setRequestBody(body: string | Uint8Array) {
		let size, contentLength;
		if (typeof body === 'string') {
			logger.warn(
				'Passing a string as the request body is deprecated. Please use a Uint8Array instead. See ' +
					'https://github.com/WordPress/wordpress-playground/issues/997 for more details'
			);
			contentLength = this[__private__dont__use].lengthBytesUTF8(body);
			size = contentLength + 1;
		} else {
			contentLength = body.byteLength;
			size = body.byteLength;
		}

		const heapBodyPointer = this[__private__dont__use].malloc(size);
		if (!heapBodyPointer) {
			throw new Error('Could not allocate memory for the request body.');
		}

		// Write the string to the WASM memory
		if (typeof body === 'string') {
			this[__private__dont__use].stringToUTF8(
				body,
				heapBodyPointer,
				size + 1
			);
		} else {
			this[__private__dont__use].HEAPU8.set(body, heapBodyPointer);
		}

		this[__private__dont__use].ccall(
			'wasm_set_request_body',
			null,
			[NUMBER],
			[heapBodyPointer]
		);
		this[__private__dont__use].ccall(
			'wasm_set_content_length',
			null,
			[NUMBER],
			[contentLength]
		);
		return heapBodyPointer;
	}

	#setScriptPath(path: string) {
		this[__private__dont__use].ccall(
			'wasm_set_path_translated',
			null,
			[STRING],
			[path]
		);
	}

	#setServerGlobalEntry(key: string, value: string) {
		this[__private__dont__use].ccall(
			'wasm_add_SERVER_entry',
			null,
			[STRING, STRING],
			[key, value]
		);
	}

	#setEnv(name: string, value: string) {
		this[__private__dont__use].ccall(
			'wasm_add_ENV_entry',
			null,
			[STRING, STRING],
			[name, value]
		);
	}

	/**
	 * Defines a constant in the PHP runtime.
	 * @param key - The name of the constant.
	 * @param value - The value of the constant.
	 */
	defineConstant(key: string, value: string | boolean | number | null) {
		let consts = {};
		try {
			consts = JSON.parse(
				this.fileExists('/internal/shared/consts.json')
					? this.readFileAsText('/internal/shared/consts.json') ||
							'{}'
					: '{}'
			);
		} catch (e) {
			// ignore
		}
		this.writeFile(
			'/internal/shared/consts.json',
			JSON.stringify({
				...consts,
				[key]: value,
			})
		);
	}

	async #handleRequest(): Promise<PHPResponse> {
		let exitCode: number;

		/*
		 * Emscripten throws WASM failures outside of the promise chain so we need
		 * to listen for them here and rethrow in the correct context. Otherwise we
		 * get crashes and unhandled promise rejections without any useful error
		 * messages or stack traces.
		 */
		let errorListener: any;
		try {
			// eslint-disable-next-line no-async-promise-executor
			exitCode = await new Promise<number>((resolve, reject) => {
				errorListener = (e: ErrorEvent) => {
					logger.error(e);
					logger.error(e.error);
					const rethrown = new Error('Rethrown');
					rethrown.cause = e.error;
					(rethrown as any).betterMessage = e.message;
					reject(rethrown);
				};
				this.#wasmErrorsTarget?.addEventListener(
					'error',
					errorListener
				);
				const response = this[__private__dont__use].ccall(
					'wasm_sapi_handle_request',
					NUMBER,
					[],
					[],
					{ async: true }
				);
				if (response instanceof Promise) {
					return response.then(resolve, reject);
				}
				return resolve(response);
			});
		} catch (e) {
			/**
			 * An exception here means an irrecoverable crash. Let's make
			 * it very clear to the consumers of this API – every method
			 * call on this PHP instance will throw an error from now on.
			 */
			for (const name in this) {
				if (typeof this[name] === 'function') {
					(this as any)[name] = () => {
						throw new Error(
							`PHP runtime has crashed – see the earlier error for details.`
						);
					};
				}
			}
			(this as any).functionsMaybeMissingFromAsyncify =
				getFunctionsMaybeMissingFromAsyncify();

			const err = e as Error;
			const message = (
				'betterMessage' in err ? err.betterMessage : err.message
			) as string;
			const rethrown = new Error(message);
			rethrown.cause = err;
			logger.error(rethrown);
			throw rethrown;
		} finally {
			this.#wasmErrorsTarget?.removeEventListener('error', errorListener);
		}

		const { headers, httpStatusCode } = this.#getResponseHeaders();
		return new PHPResponse(
			exitCode === 0 ? httpStatusCode : 500,
			headers,
			this.readFileAsBuffer('/internal/stdout'),
			this.readFileAsText('/internal/stderr'),
			exitCode
		);
	}

	/**
	 * Recursively creates a directory with the given path in the PHP filesystem.
	 * For example, if the path is `/root/php/data`, and `/root` already exists,
	 * it will create the directories `/root/php` and `/root/php/data`.
	 *
	 * @param  path - The directory path to create.
	 */
	mkdir(path: string) {
		return FSHelpers.mkdir(this[__private__dont__use].FS, path);
	}

	/**
	 * @deprecated Use mkdir instead.
	 */
	mkdirTree(path: string) {
		return FSHelpers.mkdir(this[__private__dont__use].FS, path);
	}

	/**
	 * Reads a file from the PHP filesystem and returns it as a string.
	 *
	 * @throws {@link @php-wasm/universal:ErrnoError} – If the file doesn't exist.
	 * @param  path - The file path to read.
	 * @returns The file contents.
	 */
	readFileAsText(path: string) {
		return FSHelpers.readFileAsText(this[__private__dont__use].FS, path);
	}

	/**
	 * Reads a file from the PHP filesystem and returns it as an array buffer.
	 *
	 * @throws {@link @php-wasm/universal:ErrnoError} – If the file doesn't exist.
	 * @param  path - The file path to read.
	 * @returns The file contents.
	 */
	readFileAsBuffer(path: string): Uint8Array {
		return FSHelpers.readFileAsBuffer(this[__private__dont__use].FS, path);
	}

	/**
	 * Overwrites data in a file in the PHP filesystem.
	 * Creates a new file if one doesn't exist yet.
	 *
	 * @param  path - The file path to write to.
	 * @param  data - The data to write to the file.
	 */
	writeFile(path: string, data: string | Uint8Array) {
		return FSHelpers.writeFile(this[__private__dont__use].FS, path, data);
	}

	/**
	 * Removes a file from the PHP filesystem.
	 *
	 * @throws {@link @php-wasm/universal:ErrnoError} – If the file doesn't exist.
	 * @param  path - The file path to remove.
	 */
	unlink(path: string) {
		return FSHelpers.unlink(this[__private__dont__use].FS, path);
	}

	/**
	 * Moves a file or directory in the PHP filesystem to a
	 * new location.
	 *
	 * @param oldPath The path to rename.
	 * @param newPath The new path.
	 */
	mv(fromPath: string, toPath: string) {
		return FSHelpers.mv(this[__private__dont__use].FS, fromPath, toPath);
	}

	/**
	 * Removes a directory from the PHP filesystem.
	 *
	 * @param path The directory path to remove.
	 * @param options Options for the removal.
	 */
	rmdir(path: string, options: RmDirOptions = { recursive: true }) {
		return FSHelpers.rmdir(this[__private__dont__use].FS, path, options);
	}

	/**
	 * Lists the files and directories in the given directory.
	 *
	 * @param  path - The directory path to list.
	 * @param  options - Options for the listing.
	 * @returns The list of files and directories in the given directory.
	 */
	listFiles(
		path: string,
		options: ListFilesOptions = { prependPath: false }
	) {
		return FSHelpers.listFiles(
			this[__private__dont__use].FS,
			path,
			options
		);
	}

	/**
	 * Checks if a directory exists in the PHP filesystem.
	 *
	 * @param  path – The path to check.
	 * @returns True if the path is a directory, false otherwise.
	 */
	isDir(path: string) {
		return FSHelpers.isDir(this[__private__dont__use].FS, path);
	}

	/**
	 * Checks if a file exists in the PHP filesystem.
	 *
	 * @param  path – The path to check.
	 * @returns True if the path is a file, false otherwise.
	 */
	isFile(path: string) {
		return FSHelpers.isFile(this[__private__dont__use].FS, path);
	}

	/**
	 * Creates a symlink in the PHP filesystem.
	 * @param target
	 * @param path
	 */
	symlink(target: string, path: string) {
		return FSHelpers.symlink(this[__private__dont__use].FS, target, path);
	}

	/**
	 * Checks if a path is a symlink in the PHP filesystem.
	 *
	 * @param path
	 * @returns True if the path is a symlink, false otherwise.
	 */
	isSymlink(path: string) {
		return FSHelpers.isSymlink(this[__private__dont__use].FS, path);
	}

	/**
	 * Reads the target of a symlink in the PHP filesystem.
	 *
	 * @param path
	 * @returns The target of the symlink.
	 */
	readlink(path: string) {
		return FSHelpers.readlink(this[__private__dont__use].FS, path);
	}

	/**
	 * Resolves the real path of a file in the PHP filesystem.
	 * @param path
	 * @returns The real path of the file.
	 */
	realpath(path: string) {
		return FSHelpers.realpath(this[__private__dont__use].FS, path);
	}

	/**
	 * Checks if a file (or a directory) exists in the PHP filesystem.
	 *
	 * @param  path - The file path to check.
	 * @returns True if the file exists, false otherwise.
	 */
	fileExists(path: string) {
		return FSHelpers.fileExists(this[__private__dont__use].FS, path);
	}

	/**
	 * Hot-swaps the PHP runtime for a new one without
	 * interrupting the operations of this PHP instance.
	 *
	 * @param runtime
	 * @param cwd. Internal, the VFS path to recreate in the new runtime.
	 *             This arg is temporary and will be removed once BasePHP
	 *             is fully decoupled from the request handler and
	 *             accepts a constructor-level cwd argument.
	 */
	async hotSwapPHPRuntime(runtime: number, cwd?: string) {
		// Once we secure the lock and have the new runtime ready,
		// the rest of the swap handler is synchronous to make sure
		// no other operations acts on the old runtime or FS.
		// If there was await anywhere here, we'd risk applyng
		// asynchronous changes to either the filesystem or the
		// old PHP runtime without propagating them to the new
		// runtime.

		const oldFS = this[__private__dont__use].FS;

		// Unmount all the mount handlers
		const mountHandlers: { mountHandler: MountHandler; vfsPath: string }[] =
			[];
		for (const [vfsPath, mount] of Object.entries(this.#mounts)) {
			mountHandlers.push({ mountHandler: mount.mountHandler, vfsPath });
			await mount.unmount();
		}

		// Kill the current runtime
		try {
			this.exit();
		} catch (e) {
			// Ignore the exit-related exception
		}

		// Initialize the new runtime
		this.initializeRuntime(runtime);

		if (this.#sapiName) {
			this.setSapiName(this.#sapiName);
		}

		// Copy the old /internal directory to the new filesystem
		copyFS(oldFS, this[__private__dont__use].FS, '/internal');

		// Copy the MEMFS directory structure from the old FS to the new one
		if (cwd) {
			copyFS(oldFS, this[__private__dont__use].FS, cwd);
		}

		// Re-mount all the mount handlers
		for (const { mountHandler, vfsPath } of mountHandlers) {
			this.mkdir(vfsPath);
			await this.mount(vfsPath, mountHandler);
		}
	}

	/**
	 * Mounts a filesystem to a given path in the PHP filesystem.
	 *
	 * @param  virtualFSPath - Where to mount it in the PHP virtual filesystem.
	 * @param  mountHandler - The mount handler to use.
	 * @return Unmount function to unmount the filesystem.
	 */
	async mount(
		virtualFSPath: string,
		mountHandler: MountHandler
	): Promise<UnmountFunction> {
		const unmountCallback = await mountHandler(
			this,
			this[__private__dont__use].FS,
			virtualFSPath
		);
		const mountObject = {
			mountHandler,
			unmount: async () => {
				await unmountCallback();
				delete this.#mounts[virtualFSPath];
			},
		};
		this.#mounts[virtualFSPath] = mountObject;
		return () => {
			mountObject.unmount();
		};
	}

	/**
	 * Starts a PHP CLI session with given arguments.
	 *
	 * This method can only be used when PHP was compiled with the CLI SAPI
	 * and it cannot be used in conjunction with `run()`.
	 *
	 * Once this method finishes running, the PHP instance is no
	 * longer usable and should be discarded. This is because PHP
	 * internally cleans up all the resources and calls exit().
	 *
	 * @param  argv - The arguments to pass to the CLI.
	 * @returns The exit code of the CLI session.
	 */
	async cli(argv: string[]): Promise<number> {
		for (const arg of argv) {
			this[__private__dont__use].ccall(
				'wasm_add_cli_arg',
				null,
				[STRING],
				[arg]
			);
		}
		try {
			return await this[__private__dont__use].ccall(
				'run_cli',
				null,
				[],
				[],
				{
					async: true,
				}
			);
		} catch (error) {
			if (isExitCodeZero(error)) {
				return 0;
			}
			throw error;
		}
	}

	setSkipShebang(shouldSkip: boolean) {
		this[__private__dont__use].ccall(
			'wasm_set_skip_shebang',
			null,
			[NUMBER],
			[shouldSkip ? 1 : 0]
		);
	}

	exit(code = 0) {
		this.dispatchEvent({
			type: 'runtime.beforedestroy',
		});
		try {
			this[__private__dont__use]._exit(code);
		} catch (e) {
			// ignore the exit error
		}

		// Clean up any initialized state
		this.#webSapiInitialized = false;

		// Delete any links between this PHP instance and the runtime
		this.#wasmErrorsTarget = null;
		delete this[__private__dont__use]['onMessage'];
		delete this[__private__dont__use];
	}

	[Symbol.dispose]() {
		if (this.#webSapiInitialized) {
			this.exit(0);
		}
	}
}

export function normalizeHeaders(
	headers: PHPRequestHeaders
): PHPRequestHeaders {
	const normalized: PHPRequestHeaders = {};
	for (const key in headers) {
		normalized[key.toLowerCase()] = headers[key];
	}
	return normalized;
}

/**
 * Copies the MEMFS directory structure from one FS in another FS.
 * Non-MEMFS nodes are ignored.
 */
function copyFS(
	source: Emscripten.FileSystemInstance,
	target: Emscripten.FileSystemInstance,
	path: string
) {
	let oldNode;
	try {
		oldNode = source.lookupPath(path);
	} catch (e) {
		return;
	}
	// MEMFS nodes have a `contents` property. NODEFS nodes don't.
	// We only want to copy MEMFS nodes here.
	if (!('contents' in oldNode.node)) {
		return;
	}

	// Let's be extra careful and only proceed if newFs doesn't
	// already have a node at the given path.
	try {
		// @TODO: Figure out the right thing to do. In Parent -> child PHP case,
		//        we indeed want to synchronize the entire filesystem. However,
		//        this approach seems slow and inefficient. Instead of exhaustively
		//        iterating, could we just mark directories as dirty on write? And
		//        how do we sync in both directions?
		// target = target.lookupPath(path);
		// return;
	} catch (e) {
		// There's no such node in the new FS. Good,
		// we may proceed.
	}

	if (!source.isDir(oldNode.node.mode)) {
		target.writeFile(path, source.readFile(path));
		return;
	}

	target.mkdirTree(path);
	const filenames = source
		.readdir(path)
		.filter((name: string) => name !== '.' && name !== '..');
	for (const filename of filenames) {
		copyFS(source, target, joinPaths(path, filename));
	}
}
