export * from '@wp-playground/blueprints';

export type {
	HTTPMethod,
	PHPRunOptions,
	PHPRequest,
	PHPResponse,
	UniversalPHP,
	PHPOutput,
	PHPResponseData,
	ErrnoError,
	PHPRequestHandler,
	PHPRequestHandlerConfiguration,
	PHPRequestHeaders,
	SupportedPHPVersion,
	RmDirOptions,
	RuntimeType,
} from '@php-wasm/universal';
export {
	setPhpIniEntries,
	SupportedPHPVersions,
	SupportedPHPVersionsList,
	LatestSupportedPHPVersion,
} from '@php-wasm/universal';
export { phpVar, phpVars } from '@php-wasm/util';
export type { PlaygroundClient, MountDescriptor };

import {
	Blueprint,
	compileBlueprint,
	OnStepCompleted,
	runBlueprintSteps,
} from '@wp-playground/blueprints';
import { consumeAPI } from '@php-wasm/web';
import { ProgressTracker } from '@php-wasm/progress';
import type { MountDescriptor, PlaygroundClient } from '@wp-playground/remote';
import { collectPhpLogs, logger } from '@php-wasm/logger';

export interface StartPlaygroundOptions {
	iframe: HTMLIFrameElement;
	remoteUrl: string;
	progressTracker?: ProgressTracker;
	disableProgressBar?: boolean;
	blueprint?: Blueprint;
	onBlueprintStepCompleted?: OnStepCompleted;
	/**
	 * Called when the playground client is connected, but before the blueprint
	 * steps are run.
	 *
	 * @param playground
	 * @returns
	 */
	onClientConnected?: (playground: PlaygroundClient) => void;
	/**
	 * The SAPI name PHP will use.
	 * @internal
	 * @private
	 */
	sapiName?: string;
	/**
	 * Called before the blueprint steps are run,
	 * allows the caller to delay the Blueprint execution
	 * once the Playground is booted.
	 *
	 * @returns
	 */
	onBeforeBlueprint?: () => Promise<void>;
	mounts?: Array<MountDescriptor>;
	shouldInstallWordPress?: boolean;
	/**
	 * The string prefix used in the site URL served by the currently
	 * running remote.html. E.g. for a prefix like `/scope:playground/`,
	 * the scope would be `playground`. See the `@php-wasm/scopes` package
	 * for more details.
	 */
	scope?: string;
	/**
	 * Proxy URL to use for cross-origin requests.
	 *
	 * For example, if corsProxy is set to "https://cors.wordpress.net/proxy.php",
	 * then the CORS requests to https://github.com/WordPress/wordpress-playground.git would actually
	 * be made to https://cors.wordpress.net/proxy.php?https://github.com/WordPress/wordpress-playground.git.
	 *
	 * The Blueprints library will arbitrarily choose which requests to proxy. If you need
	 * to proxy every single request, do not use this option. Instead, you should preprocess
	 * your Blueprint to replace all cross-origin URLs with the proxy URL.
	 */
	corsProxy?: string;
}

/**
 * Loads playground in iframe and returns a PlaygroundClient instance.
 *
 * @param iframe Any iframe with Playground's remote.html loaded.
 * @param options Options for loading the playground.
 * @returns A PlaygroundClient instance.
 */
export async function startPlaygroundWeb({
	iframe,
	blueprint,
	remoteUrl,
	progressTracker = new ProgressTracker(),
	disableProgressBar,
	onBlueprintStepCompleted,
	onClientConnected = () => {},
	sapiName,
	onBeforeBlueprint,
	mounts,
	scope,
	corsProxy,
	shouldInstallWordPress,
}: StartPlaygroundOptions): Promise<PlaygroundClient> {
	assertValidRemote(remoteUrl);
	allowStorageAccessByUserActivation(iframe);

	remoteUrl = setQueryParams(remoteUrl, {
		progressbar: !disableProgressBar,
	});
	progressTracker.setCaption('Preparing WordPress');

	// Set a default blueprint if none is provided.
	if (!blueprint) {
		blueprint = {};
	}

	const compiled = compileBlueprint(blueprint, {
		progress: progressTracker.stage(0.5),
		onStepCompleted: onBlueprintStepCompleted,
		corsProxy,
	});

	await new Promise((resolve) => {
		iframe.src = remoteUrl;
		iframe.addEventListener('load', resolve, false);
	});

	// Connect the Comlink API client to the remote worker,
	// boot the playground, and run the blueprint steps.
	const playground = consumeAPI<PlaygroundClient>(
		iframe.contentWindow!,
		iframe.ownerDocument!.defaultView!
	) as PlaygroundClient;
	await playground.isConnected();
	progressTracker.pipe(playground);
	const downloadPHPandWP = progressTracker.stage();
	await playground.onDownloadProgress(downloadPHPandWP.loadingListener);
	await playground.boot({
		mounts,
		sapiName,
		scope: scope ?? Math.random().toFixed(16),
		shouldInstallWordPress,
		phpVersion: compiled.versions.php,
		wpVersion: compiled.versions.wp,
		withNetworking: compiled.features.networking,
	});
	await playground.isReady();
	downloadPHPandWP.finish();

	collectPhpLogs(logger, playground);
	onClientConnected(playground);

	if (onBeforeBlueprint) {
		await onBeforeBlueprint();
	}

	await runBlueprintSteps(compiled, playground);
	progressTracker.finish();

	return playground;
}

/**
 * Chrome does not allow Service Workers to be registered from cross-origin
 * iframes when third-party cookies are disabled unless
 * `requestStorageAccess()` is called and the user grants storage access.
 *
 * However, sandboxed <iframe>s cannot be granted storage access by default for
 * security reasons. Therefore, we need to add the
 * `allow-storage-access-by-user-activation` flag to the iframe's sandbox
 * attribute if it is not already present.
 *
 * https://developer.mozilla.org/en-US/docs/Web/API/Storage_Access_API
 */
function allowStorageAccessByUserActivation(iframe: HTMLIFrameElement) {
	if (
		iframe.sandbox?.length &&
		!iframe.sandbox?.contains('allow-storage-access-by-user-activation')
	) {
		iframe.sandbox.add('allow-storage-access-by-user-activation');
	}
}

const officialRemoteOrigin = 'https://playground.wordpress.net';
function assertValidRemote(remoteHtmlUrl: string) {
	const url = new URL(remoteHtmlUrl, officialRemoteOrigin);
	if (
		(url.origin === officialRemoteOrigin || url.hostname === 'localhost') &&
		url.pathname !== '/remote.html'
	) {
		throw new Error(
			`Invalid remote URL: ${url}. ` +
				`Expected origin to be ${officialRemoteOrigin}/remote.html.`
		);
	}
}

function setQueryParams(url: string, params: Record<string, unknown>) {
	const urlObject = new URL(url, officialRemoteOrigin);
	const qs = new URLSearchParams(urlObject.search);
	for (const [key, value] of Object.entries(params)) {
		if (value !== undefined && value !== null && value !== false) {
			if (Array.isArray(value)) {
				for (const item of value) {
					qs.append(key, item.toString());
				}
			} else {
				qs.set(key, value.toString());
			}
		}
	}
	urlObject.search = qs.toString();
	return urlObject.toString();
}
