import { logger } from '@php-wasm/logger';
import { MountDescriptor, PlaygroundClient } from '@wp-playground/remote';
import { PHPConstants } from '@wp-playground/blueprints';
import { saveDirectoryHandle } from '../opfs/opfs-directory-handle-storage';
import {
	opfsSiteStorage,
	getDirectoryPathForSlug,
} from '../opfs/opfs-site-storage';
import store, { PlaygroundReduxState } from './store';
import { selectClientBySiteSlug, updateClientInfo } from './slice-clients';
import {
	selectSiteBySlug,
	updateSite,
	updateSiteMetadata,
} from './slice-sites';
import { PlaygroundRoute, redirectTo } from '../url/router';
import { SiteStorageType } from '../../site-metadata';

export function persistTemporarySite(
	siteSlug: string,
	storageType: Extract<SiteStorageType, 'opfs' | 'local-fs'>
) {
	// @TODO: Handle errors
	return async (
		dispatch: typeof store.dispatch,
		getState: () => PlaygroundReduxState
	) => {
		const state = getState();
		const playground = selectClientBySiteSlug(state, siteSlug);
		if (!playground) {
			throw new Error(
				`Site ${siteSlug} must have an active client to be saved, but none was found.`
			);
		}

		const siteInfo = selectSiteBySlug(state, siteSlug)!;
		if (!siteInfo) {
			throw new Error(`Cannot find site ${siteSlug} to save.`);
		}

		try {
			const existingSiteInfo = await opfsSiteStorage?.read(siteInfo.slug);
			if (existingSiteInfo?.metadata.storage === 'none') {
				// It is likely we are dealing with the remnants of a failed save
				// of a temporary site to OPFS. Let's clean up an try again.
				await opfsSiteStorage?.delete(siteInfo.slug);
			}
		} catch (error: any) {
			if (error?.name === 'NotFoundError') {
				// Do nothing
			} else {
				throw error;
			}
		}
		await opfsSiteStorage?.create(siteInfo.slug, {
			...siteInfo.metadata,
			// Start with storage type of 'none' to represent a temporary site
			// that the site is being saved. This will help us distinguish
			// between successful and failed saves.
			storage: 'none',
		});

		let mountDescriptor: Omit<MountDescriptor, 'initialSyncDirection'>;
		if (storageType === 'opfs') {
			mountDescriptor = {
				device: {
					type: 'opfs',
					path: getDirectoryPathForSlug(siteSlug),
				},
				mountpoint: '/wordpress',
			} as const;
		} else if (storageType === 'local-fs') {
			let dirHandle: FileSystemDirectoryHandle;
			try {
				// Request permission to access the directory.
				// https://developer.mozilla.org/en-US/docs/Web/API/Window/showDirectoryPicker
				dirHandle = await (window as any).showDirectoryPicker({
					// By specifying an ID, the browser can remember different directories
					// for different IDs.If the same ID is used for another picker, the
					// picker opens in the same directory.
					id: 'playground-directory',
					mode: 'readwrite',
				});
			} catch (e) {
				// No directory selected but log the error just in case.
				logger.error(e);
				return;
			}
			await saveDirectoryHandle(siteSlug, dirHandle);

			mountDescriptor = {
				device: {
					type: 'local-fs',
					handle: dirHandle,
				},
				mountpoint: '/wordpress',
			} as const;
		} else {
			throw new Error(`Unsupported device type: ${storageType}`);
		}

		dispatch(
			updateClientInfo({
				siteSlug,
				changes: {
					opfsMountDescriptor: mountDescriptor,
					opfsSync: { status: 'syncing' },
				},
			})
		);
		try {
			await playground!.mountOpfs(
				{
					...mountDescriptor,
					initialSyncDirection: 'memfs-to-opfs',
				},
				(progress) => {
					dispatch(
						updateClientInfo({
							siteSlug,
							changes: {
								opfsSync: {
									status: 'syncing',
									progress,
								},
							},
						})
					);
				}
			);

			// @TODO: Create a notification to tell the user the operation is complete
			dispatch(
				updateClientInfo({
					siteSlug,
					changes: {
						opfsSync: undefined,
					},
				})
			);
		} catch (error) {
			dispatch(
				updateClientInfo({
					siteSlug,
					changes: {
						opfsSync: {
							status: 'error',
						},
					},
				})
			);
			throw error;
		}

		await dispatch(
			updateSite({
				slug: siteSlug,
				changes: {
					originalUrlParams: undefined,
				},
			})
		);

		await dispatch(
			updateSiteMetadata({
				slug: siteSlug,
				changes: {
					storage: storageType,
					// Reset the created date. Mental model: From the perspective of
					// the storage backend, the site was just created.
					whenCreated: Date.now(),
					// Make sure to store the constants we'll want to re-apply
					// on the next page load.
					runtimeConfiguration: {
						...siteInfo.metadata.runtimeConfiguration,
						constants: await getPlaygroundDefinedPHPConstants(
							playground
						),
					},
				},
			})
		);
		/**
		 * @TODO: Fix OPFS site storage write timeout that happens alongside 2000
		 *        "Cannot read properties of undefined (reading 'apply')" errors here:
		 * I suspect the postMessage call we do to the safari worker causes it to
		 * respond with another message and these unexpected exchange throws off
		 * Comlink. We should make Comlink ignore those.
		 */
		// @TODO: ^ Is this fixed now?
		const updatedState = getState();
		const updatedSite = selectSiteBySlug(updatedState, siteSlug);
		const persistentSiteUrl = PlaygroundRoute.site(updatedSite!);
		redirectTo(persistentSiteUrl);
	};
}

async function getPlaygroundDefinedPHPConstants(playground: PlaygroundClient) {
	let constants: PHPConstants = {};
	try {
		constants = JSON.parse(
			await playground.readFileAsText('/internal/shared/consts.json')
		);
	} catch (error) {
		// Do nothing
	}
	return constants;
}
