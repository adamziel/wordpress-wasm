import { MessageListener } from '@php-wasm/universal';
import {
	spawnPHPWorkerThread,
	exposeAPI,
	consumeAPI,
	setupPostMessageRelay,
	SyncProgressCallback,
} from '@php-wasm/web';

import type {
	PlaygroundWorkerEndpoint,
	WorkerBootOptions,
	MountDescriptor,
} from './worker-thread';
export type { MountDescriptor, WorkerBootOptions };
import type { WebClientMixin } from './playground-client';
import ProgressBar, { ProgressBarOptions } from './progress-bar';

// Avoid literal "import.meta.url" on purpose as vite would attempt
// to resolve it during build time. This should specifically be
// resolved by the browser at runtime to reflect the current origin.
const origin = new URL('/', (import.meta || {}).url).origin;

// @ts-ignore
import moduleWorkerUrl from './worker-thread?worker&url';

export const workerUrl: string = new URL(moduleWorkerUrl, origin) + '';

// @ts-ignore
import serviceWorkerPath from '../../service-worker.ts?worker&url';
import { FilesystemOperation } from '@php-wasm/fs-journal';
import { setupFetchNetworkTransport } from './setup-fetch-network-transport';
import { logger } from '@php-wasm/logger';
import { PhpWasmError } from '@php-wasm/util';
import { responseTo } from '@php-wasm/web-service-worker';
export const serviceWorkerUrl = new URL(serviceWorkerPath, origin);

// Prevent Vite from hot-reloading this file – it would
// cause bootPlaygroundRemote() to register another web worker
// without unregistering the previous one. The first web worker
// would then fight for service worker requests with the second
// one. It's a difficult problem to debug and HMR isn't that useful
// here anyway – let's just disable it for this file.
// @ts-ignore
if (import.meta.hot) {
	// @ts-ignore
	import.meta.hot.accept(() => {});
}

const query = new URL(document.location.href).searchParams;
export async function bootPlaygroundRemote() {
	assertNotInfiniteLoadingLoop();

	const hasProgressBar = query.has('progressbar');
	let bar: ProgressBar | undefined;
	if (hasProgressBar) {
		bar = new ProgressBar();
		document.body.prepend(bar.element);
	}
	const sw = navigator.serviceWorker;
	if (!sw) {
		/**
		 * Service workers may only run in secure contexts.
		 * See https://w3c.github.io/webappsec-secure-contexts/
		 */
		if (window.isSecureContext) {
			throw new PhpWasmError(
				'Service workers are not supported in your browser.'
			);
		} else {
			throw new PhpWasmError(
				'WordPress Playground uses service workers and may only work on HTTPS and http://localhost/ sites, but the current site is neither.'
			);
		}
	}

	const registration = await sw.register(serviceWorkerUrl + '', {
		type: 'module',
		// Always bypass HTTP cache when fetching the new Service Worker script:
		updateViaCache: 'none',
	});

	// Check if there's a new service worker available and, if so, enqueue
	// the update:
	try {
		await registration.update();
	} catch (e) {
		// registration.update() throws if it can't reach the server.
		// We're swallowing the error to keep the app working in offline mode
		// or when playground.wordpress.net is down. We can be sure we have a
		// functional service worker at this point because sw.register() succeeded.
		logger.error('Failed to update service worker.', e);
	}

	const phpWorkerApi = consumeAPI<PlaygroundWorkerEndpoint>(
		await spawnPHPWorkerThread(workerUrl)
	);

	const wpFrame = document.querySelector('#wp') as HTMLIFrameElement;
	const phpRemoteApi: WebClientMixin = {
		async onDownloadProgress(fn) {
			return phpWorkerApi.onDownloadProgress(fn);
		},
		async journalFSEvents(root: string, callback) {
			return phpWorkerApi.journalFSEvents(root, callback);
		},
		async replayFSJournal(events: FilesystemOperation[]) {
			return phpWorkerApi.replayFSJournal(events);
		},
		async addEventListener(event, listener) {
			return await phpWorkerApi.addEventListener(event, listener);
		},
		async removeEventListener(event, listener) {
			return await phpWorkerApi.removeEventListener(event, listener);
		},
		async setProgress(options: ProgressBarOptions) {
			if (!bar) {
				throw new Error('Progress bar not available');
			}
			bar.setOptions(options);
		},
		async setLoaded() {
			if (!bar) {
				throw new Error('Progress bar not available');
			}
			bar.destroy();
		},
		async onNavigation(fn) {
			// Manage the address bar
			wpFrame.addEventListener('load', async (e: any) => {
				try {
					/**
					 * When navigating to a page with %0A sequences (encoded newlines)
					 * in the query string, the `location.href` property of the
					 * iframe's content window doesn't seem to reflect them. Everything
					 * else is in place, but not the %0A sequences.
					 *
					 * Weirdly, these sequences are available after the next event
					 * loop tick – hence the `setTimeout(0)`.
					 *
					 * The exact cause is unclear at the moment of writing of this
					 * comment. The WHATWG HTML Standard [1] has a few hints:
					 *
					 * * Current and active session history entries may get out of
					 *   sync for iframes.
					 * * Documents inside iframes have "is delaying load events" set
					 *   to true.
					 *
					 * But there doesn't seem to be any concrete explanation and no
					 * recommended remediation. If anyone has a clue, please share it
					 * in a GitHub issue or start a new PR.
					 *
					 * [1] https://html.spec.whatwg.org/multipage/document-sequences.html#nav-active-history-entry
					 */
					// Get the content window while e.currentTarget is available.
					// It will be undefined on the next event loop tick.
					const contentWindow = e.currentTarget!.contentWindow;
					await new Promise((resolve) => setTimeout(resolve, 0));
					const path = await playground.internalUrlToPath(
						contentWindow.location.href
					);
					fn(path);
				} catch (e) {
					// @TODO: The above call can fail if the remote iframe
					// is embedded in StackBlitz, or presumably, any other
					// environment with restrictive CSP. Any error thrown
					// due to CORS-related stuff crashes the entire remote
					// so let's ignore it for now and find a correct fix in time.
				}
			});
		},
		async goTo(requestedPath: string) {
			if (!requestedPath.startsWith('/')) {
				requestedPath = '/' + requestedPath;
			}
			/**
			 * Workaround for a Safari bug: navigating to `/wp-admin`
			 * without the trailing slash causes the browser to hang.
			 * Chrome and Firefox correctly navigate to `/wp-admin`,
			 * get a 302 redirect from PHPRequestHandler, and then follow
			 * it to `/wp-admin/`.
			 *
			 * Interestingly, opening pretty permalinks without the trailing slash
			 * works correctly. For example, `/sample-page` works as expected.
			 */
			if (requestedPath === '/wp-admin') {
				requestedPath = '/wp-admin/';
			}
			const newUrl = await playground.pathToInternalUrl(requestedPath);
			const oldUrl = wpFrame.src;

			// If the URL is the same, we need to force a reload
			// because otherwise the iframe will not reload the page.
			if (newUrl === oldUrl && wpFrame.contentWindow) {
				try {
					wpFrame.contentWindow.location.href = newUrl;
					return;
				} catch (e) {
					// The above call can fail if we're embedded in an
					// environment with a restrictive CSP policy.
				}
			}
			wpFrame.src = newUrl;
		},
		async getCurrentURL() {
			let url = '';
			try {
				url = wpFrame.contentWindow!.location.href;
			} catch (e) {
				// The above call can fail if we're embedded in an
				// environment with a restrictive CSP policy.
			}
			if (!url) {
				// If we can't get the URL from the iframe (e.g. it's not loaded
				// yet), let's refer to the src attribute of the iframe itself.
				// This is less reliable because the src attribute may not be
				// updated when the iframe navigates to a new URL.
				url = wpFrame.src;
			}
			return await playground.internalUrlToPath(url);
		},
		async setIframeSandboxFlags(flags: string[]) {
			wpFrame.setAttribute('sandbox', flags.join(' '));
		},
		/**
		 * This function is merely here to explicitly call workerApi.onMessage.
		 * Comlink should be able to handle that on its own, but something goes
		 * wrong and if this function is not here, we see the following error:
		 *
		 * Error: Failed to execute 'postMessage' on 'Worker': function() {
		 * } could not be cloned.
		 *
		 * In the future, this explicit declaration shouldn't be needed.
		 *
		 * @param callback
		 * @returns
		 */
		async onMessage(callback: MessageListener) {
			return await phpWorkerApi.onMessage(callback);
		},

		/**
		 * Ditto for this function.
		 * @see onMessage
		 * @param callback
		 * @returns
		 */
		async mountOpfs(
			options: MountDescriptor,
			onProgress?: SyncProgressCallback
		) {
			return await phpWorkerApi.mountOpfs(options, onProgress);
		},

		/**
		 * Ditto for this function.
		 * @see onMessage
		 * @param mountpoint
		 * @returns
		 */
		async unmountOpfs(mountpoint: string) {
			return await phpWorkerApi.unmountOpfs(mountpoint);
		},

		/**
		 * Download WordPress assets.
		 * @see backfillStaticFilesRemovedFromMinifiedBuild in the worker-thread.ts
		 */
		async backfillStaticFilesRemovedFromMinifiedBuild() {
			await phpWorkerApi.backfillStaticFilesRemovedFromMinifiedBuild();
		},

		/**
		 * Checks whether we have the missing WordPress assets readily
		 * available in the request cache.
		 */
		async hasCachedStaticFilesRemovedFromMinifiedBuild() {
			return await phpWorkerApi.hasCachedStaticFilesRemovedFromMinifiedBuild();
		},

		async boot(options) {
			await phpWorkerApi.boot(options);

			// Proxy the service worker messages to the web worker:
			navigator.serviceWorker.addEventListener(
				'message',
				async function onMessage(event) {
					/**
					 * Ignore events meant for other PHP instances to
					 * avoid handling the same event twice.
					 *
					 * This is important because the service worker posts the
					 * same message to all application instances across all browser tabs.
					 */
					if (options.scope && event.data.scope !== options.scope) {
						return;
					}

					// Wait for the PHP API client to be set by bootPlaygroundRemote
					const args = event.data.args || [];
					const method = event.data
						.method as keyof PlaygroundWorkerEndpoint;
					const result = await (phpWorkerApi[method] as Function)(
						...args
					);
					event.source!.postMessage(
						responseTo(event.data.requestId, result)
					);
				}
			);
			sw.startMessages();

			try {
				await phpWorkerApi.isReady();

				setupPostMessageRelay(
					wpFrame,
					getOrigin((await playground.absoluteUrl)!)
				);

				if (options.withNetworking) {
					await setupFetchNetworkTransport(phpWorkerApi, {
						corsProxyUrl: options.corsProxyUrl,
					});
				}

				setAPIReady();
			} catch (e) {
				setAPIError(e as Error);
				throw e;
			}

			/**
			 * When we're running WordPress from a minified bundle, we're missing some static assets.
			 * The section below backfills them if needed. It doesn't do anything if the assets are already
			 * in place, or when WordPress is loaded from a non-minified bundle.
			 *
			 * Minified bundles are shipped without most static assets to reduce the bundle size and
			 * the loading time. When WordPress loads for the first time, the browser parses all the
			 * <script src="">, <link href="">, etc. tags and fetches the missing assets from the server.
			 *
			 * Unfortunately, fetching these assets on demand wouldn't work in an offline mode.
			 *
			 * Below we're downloading a zipped bundle of the missing assets.
			 */
			if (
				await phpRemoteApi.hasCachedStaticFilesRemovedFromMinifiedBuild()
			) {
				/**
				 * If we already have the static assets in the cache, the backfilling only
				 * involves unzipping the archive. This is fast. Let's do it before the first
				 * render.
				 *
				 * Why?
				 *
				 * Because otherwise the initial offline page render would lack CSS.
				 * Without the static assets in /wordpress/wp-content, the browser would
				 * attempt to fetch them from the server. However, we're in an offline mode
				 * so nothing would be fetched.
				 */
				await phpRemoteApi.backfillStaticFilesRemovedFromMinifiedBuild();
			} else {
				/**
				 * If we don't have the static assets in the cache, we need to fetch them.
				 *
				 * Let's wait for the initial page load before we start the backfilling.
				 * The static assets are 12MB+ in size. Starting the download before
				 * Playground is loaded would noticeably delay the first paint.
				 */
				wpFrame.addEventListener('load', () => {
					phpRemoteApi.backfillStaticFilesRemovedFromMinifiedBuild();
				});
			}
		},
	};

	await phpWorkerApi.isConnected();

	// If onDownloadProgress is not explicitly re-exposed here,
	// Comlink will throw an error and claim the callback
	// cannot be cloned. Adding a transfer handler for functions
	// doesn't help:
	// https://github.com/GoogleChromeLabs/comlink/issues/426#issuecomment-578401454
	// @TODO: Handle the callback conversion automatically and don't explicitly re-expose
	//        the onDownloadProgress method
	const [setAPIReady, setAPIError, playground] = exposeAPI(
		phpRemoteApi,
		phpWorkerApi
	);

	/*
	 * An assertion to make sure Playground Client is compatible
	 * with Remote<PlaygroundClient>
	 */
	return playground;
}

function getOrigin(url: string) {
	return new URL(url, 'https://example.com').origin;
}

/**
 * When the service worker fails for any reason, the page displayed inside
 * the iframe won't be a WordPress instance we expect from the service worker.
 * Instead, it will be the original page trying to load the service worker. This
 * causes an infinite loop with a loader inside a loader inside a loader.
 */
function assertNotInfiniteLoadingLoop() {
	let isBrowserInABrowser = false;
	try {
		isBrowserInABrowser =
			window.parent !== window &&
			(window as any).parent.IS_WASM_WORDPRESS;
	} catch (e) {
		// ignore
	}
	if (isBrowserInABrowser) {
		throw new Error(
			`The service worker did not load correctly. This is a bug,
			please report it on https://github.com/WordPress/wordpress-playground/issues`
		);
	}
	(window as any).IS_WASM_WORDPRESS = true;
}
