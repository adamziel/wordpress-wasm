/**
 * Imports required for the Playground Client.
 */
import { ProgressReceiver } from '@php-wasm/progress';
import { MessageListener, UniversalPHP } from '@php-wasm/universal';
import { RemoteAPI, SyncProgressCallback } from '@php-wasm/web';
import { ProgressBarOptions } from './progress-bar';
import type {
	PlaygroundWorkerEndpoint,
	MountDescriptor,
	WorkerBootOptions,
} from './worker-thread';

export interface WebClientMixin extends ProgressReceiver {
	/**
	 * Sets the progress bar options.
	 * @param options The progress bar options.
	 */
	setProgress(options: ProgressBarOptions): Promise<void>;

	/**
	 * Sets the loaded state.
	 */
	setLoaded(): Promise<void>;

	/**
	 * Sets the navigation event listener.
	 * @param fn The function to be called when a navigation event occurs.
	 */
	onNavigation(fn: (url: string) => void): Promise<void>;

	/**
	 * Navigates to the requested path.
	 * @param requestedPath The requested path.
	 */
	goTo(requestedPath: string): Promise<void>;

	/**
	 * Gets the current URL.
	 */
	getCurrentURL(): Promise<string>;

	/**
	 * Sets the iframe sandbox flags.
	 * @param flags The iframe sandbox flags.
	 */
	setIframeSandboxFlags(flags: string[]): Promise<void>;

	/**
	 * The onDownloadProgress event listener.
	 */
	onDownloadProgress: PlaygroundWorkerEndpoint['onDownloadProgress'];

	journalFSEvents: PlaygroundWorkerEndpoint['journalFSEvents'];
	replayFSJournal: PlaygroundWorkerEndpoint['replayFSJournal'];
	addEventListener: PlaygroundWorkerEndpoint['addEventListener'];
	removeEventListener: PlaygroundWorkerEndpoint['removeEventListener'];
	backfillStaticFilesRemovedFromMinifiedBuild: PlaygroundWorkerEndpoint['backfillStaticFilesRemovedFromMinifiedBuild'];
	hasCachedStaticFilesRemovedFromMinifiedBuild: PlaygroundWorkerEndpoint['hasCachedStaticFilesRemovedFromMinifiedBuild'];

	/** @inheritDoc @php-wasm/universal!UniversalPHP.onMessage */
	onMessage(listener: MessageListener): Promise<() => Promise<void>>;

	mountOpfs(
		options: MountDescriptor,
		onProgress?: SyncProgressCallback
	): Promise<void>;

	unmountOpfs(mountpoint: string): Promise<void>;

	boot(options: WorkerBootOptions): Promise<void>;
}

/**
 * The Playground Client interface.
 */
export interface PlaygroundClient
	extends RemoteAPI<PlaygroundWorkerEndpoint & WebClientMixin> {}

/*
 * Assert that PlaygroundClient is a superset of UniversalPHP.
 */
export const assertion: UniversalPHP = {} as PlaygroundClient;
