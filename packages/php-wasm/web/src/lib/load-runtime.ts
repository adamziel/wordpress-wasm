import {
	EmscriptenOptions,
	loadPHPRuntime,
	PHPLoaderModule,
	SupportedPHPVersion,
} from '@php-wasm/universal';
import { getPHPLoaderModule } from './get-php-loader-module';
import {
	TCPOverFetchOptions,
	tcpOverFetchWebsocket,
} from './tcp-over-fetch-websocket';

export interface LoaderOptions {
	emscriptenOptions?: EmscriptenOptions;
	onPhpLoaderModuleLoaded?: (module: PHPLoaderModule) => void;
	tcpOverFetch?: TCPOverFetchOptions;
}

/**
 * Fake a websocket connection to prevent errors in the web app
 * from cascading and breaking the Playground.
 */
const fakeWebsocket = () => {
	return {
		websocket: {
			decorator: (WebSocketConstructor: any) => {
				return class FakeWebsocketConstructor extends WebSocketConstructor {
					constructor() {
						try {
							super();
						} catch (e) {
							// pass
						}
					}

					send() {
						return null;
					}
				};
			},
		},
	};
};

export async function loadWebRuntime(
	phpVersion: SupportedPHPVersion,
	options: LoaderOptions = {}
) {
	const phpLoaderModule = await getPHPLoaderModule(phpVersion);
	options.onPhpLoaderModuleLoaded?.(phpLoaderModule);
	const websocketExtension = options.tcpOverFetch
		? tcpOverFetchWebsocket(options.tcpOverFetch)
		: fakeWebsocket();
	return await loadPHPRuntime(phpLoaderModule, {
		...(options.emscriptenOptions || {}),
		...websocketExtension,
	});
}
