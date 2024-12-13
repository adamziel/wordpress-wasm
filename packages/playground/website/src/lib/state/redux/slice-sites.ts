import {
	createSlice,
	createEntityAdapter,
	PayloadAction,
	createSelector,
} from '@reduxjs/toolkit';
import { createSiteMetadata, SiteMetadata } from '../../site-metadata';
import {
	selectActiveSite,
	PlaygroundDispatch,
	PlaygroundReduxState,
	setActiveSite,
} from './store';
import { opfsSiteStorage } from '../opfs/opfs-site-storage';
import { randomSiteName } from './random-site-name';

/**
 * The Site model used to represent a site within Playground.
 */
export interface SiteInfo {
	slug: string;
	originalUrlParams?: {
		searchParams?: Record<string, string>;
		hash?: string;
	};
	metadata: SiteMetadata;
}

// Define the loading state type
export type LoadingState = 'loading' | 'loaded' | 'error';

// Create an entity adapter for SiteInfo
const sitesAdapter = createEntityAdapter<SiteInfo, string>({
	selectId: (site) => site.slug,
	sortComparer: (a, b) => a.slug.localeCompare(b.slug),
});

// Define the initial state using the adapter and include the loading state
const initialState = sitesAdapter.getInitialState({
	opfsSitesLoadingState: 'loading' as LoadingState,
	firstTemporarySiteCreated: false,
});

// Create the slice
const sitesSlice = createSlice({
	name: 'sites',
	initialState,
	reducers: {
		// Add one or many sites
		addSites: sitesAdapter.addMany,
		addSite: sitesAdapter.addOne,
		updateSite: sitesAdapter.updateOne,
		removeSite: sitesAdapter.removeOne,

		// Custom reducer for updating nested properties
		updateSiteMetadata: (
			state,
			action: PayloadAction<{
				slug: string;
				metadata: Partial<SiteMetadata>;
			}>
		) => {
			const { slug, metadata } = action.payload;
			const site = state.entities[slug];
			if (site) {
				site.metadata = { ...site.metadata, ...metadata };
			}
		},

		setSites: sitesAdapter.setAll,
		setOPFSSitesLoadingState: (
			state,
			action: PayloadAction<LoadingState>
		) => {
			state.opfsSitesLoadingState = action.payload;
		},
		setFirstTemporarySiteCreated: (state) => {
			state.firstTemporarySiteCreated = true;
		},
	},
});

export const OPFSSitesLoaded = (sites: SiteInfo[]) => {
	return (
		dispatch: PlaygroundDispatch,
		getState: () => PlaygroundReduxState
	) => {
		const currentSites = getState().sites.entities;
		const allSites = { ...currentSites };
		sites.forEach((site) => {
			allSites[site.slug] = site;
		});
		dispatch(sitesSlice.actions.setSites(allSites));
		dispatch(setOPFSSitesLoadingState('loaded'));
	};
};

// New selector for loading state
export const getSitesLoadingState = (state: {
	sites: ReturnType<typeof sitesSlice.reducer>;
}) => state.sites.opfsSitesLoadingState;

export function deriveSlugFromSiteName(name: string) {
	return name.toLowerCase().replaceAll(' ', '-');
}
export function deriveSiteNameFromSlug(slug: string) {
	return slug
		.replaceAll('-', ' ')
		.replaceAll(/\b\w/g, (c) => c.toUpperCase())
		.replaceAll(/WordPress/gi, 'WordPress');
}

/**
 * Updates the site metadata in the OPFS and in the redux state.
 */
export function updateSiteMetadata({
	slug,
	changes,
}: {
	slug: string;
	changes: Partial<SiteMetadata>;
}) {
	return async (
		dispatch: PlaygroundDispatch,
		getState: () => PlaygroundReduxState
	) => {
		const storedSite = selectSiteBySlug(getState(), slug);
		await dispatch(
			updateSite({
				slug,
				changes: {
					metadata: {
						...storedSite.metadata,
						...changes,
					},
				},
			})
		);
	};
}

/**
 * Updates a site in the OPFS and in the redux state.
 *
 * @param siteInfo The site info to update.
 * @returns
 */
export function updateSite({
	slug,
	changes,
}: {
	slug: string;
	changes: Partial<SiteInfo>;
}) {
	return async (
		dispatch: PlaygroundDispatch,
		getState: () => PlaygroundReduxState
	) => {
		if ('storage' in changes) {
			throw new Error('Cannot update storage for a site.');
		}
		dispatch(
			sitesSlice.actions.updateSite({
				id: slug,
				changes,
			})
		);
		const updatedSite = selectSiteBySlug(getState(), slug);
		if (updatedSite.metadata.storage !== 'none') {
			await opfsSiteStorage?.update(
				updatedSite.slug,
				updatedSite.metadata
			);
		}
	};
}

/**
 * Creates a new site in the OPFS and in the redux state.
 *
 * @param siteInfo The site info to add.
 * @returns
 */
export function addSite(siteInfo: SiteInfo) {
	return async (
		dispatch: PlaygroundDispatch,
		getState: () => PlaygroundReduxState
	) => {
		if (siteInfo.metadata.storage === 'none') {
			throw new Error(
				'Cannot add a temporary site. Use setTemporarySiteSpec instead.'
			);
		}
		await opfsSiteStorage?.create(siteInfo.slug, siteInfo.metadata);
		dispatch(sitesSlice.actions.addSite(siteInfo));
	};
}

/**
 * Removes a site from the OPFS and from the redux state.
 *
 * @param siteInfo The site info to remove.
 * @returns
 */
export function removeSite(slug: string) {
	return async (
		dispatch: PlaygroundDispatch,
		getState: () => PlaygroundReduxState
	) => {
		const activeSite = selectActiveSite(getState());
		const siteInfo = selectSiteBySlug(getState(), slug);
		if (siteInfo.metadata.storage === 'none') {
			throw new Error('Cannot remove a temporary site.');
		}
		await opfsSiteStorage?.delete(siteInfo.slug);
		dispatch(sitesSlice.actions.removeSite(siteInfo.slug));

		// Select the most recently created site
		if (activeSite?.slug === siteInfo.slug) {
			const newActiveSite = selectSortedSites(getState())[0];
			if (newActiveSite) {
				dispatch(setActiveSite(newActiveSite.slug));
			}
		}
	};
}

/**
 * Creates a new site in the OPFS and in the redux state.
 *
 * @param siteInfo The site info to add.
 * @returns
 */
export function setTemporarySiteSpec(
	siteInfo: Omit<Partial<SiteInfo>, 'metadata'> & {
		metadata: Partial<SiteMetadata>;
	}
) {
	return async (
		dispatch: PlaygroundDispatch,
		getState: () => PlaygroundReduxState
	) => {
		const currentTemporarySite = selectTemporarySite(getState());
		if (currentTemporarySite) {
			// If the current temporary site is the same as the site we're setting,
			// then we don't need to create a new site.
			if (
				JSON.stringify(currentTemporarySite.originalUrlParams) ===
				JSON.stringify(siteInfo.originalUrlParams)
			) {
				return currentTemporarySite;
			}
		}

		const sites = getState().sites.entities;

		// First, delete any existing temporary sites
		for (const site of Object.values(sites)) {
			if (site.metadata.storage === 'none') {
				dispatch(sitesSlice.actions.removeSite(site.slug));
			}
		}

		// Then create a new temporary site
		const siteName = siteInfo.metadata.name || randomSiteName();
		const newSiteInfo = {
			...siteInfo,
			slug: deriveSlugFromSiteName(siteName),
			metadata: await createSiteMetadata({
				...siteInfo.metadata,
				name: siteName,
				storage: 'none' as const,
			}),
		};
		dispatch(sitesSlice.actions.addSite(newSiteInfo));
		dispatch(sitesSlice.actions.setFirstTemporarySiteCreated());
		return newSiteInfo;
	};
}
export const { setOPFSSitesLoadingState } = sitesSlice.actions;

export const {
	selectAll: selectAllSites,
	selectById: selectSiteBySlug,
	selectIds: selectSiteSlugs,
} = sitesAdapter.getSelectors(
	(state: { sites: ReturnType<typeof sitesSlice.reducer> }) => state.sites
);

export const selectSortedSites = createSelector(
	[selectAllSites],
	(sites: SiteInfo[]) =>
		sites.sort(
			(a, b) =>
				(b.metadata.whenCreated || 0) - (a.metadata.whenCreated || 0)
		)
);

export const selectTemporarySite = createSelector(
	[selectAllSites],
	(sites: SiteInfo[]) => {
		return sites.find((site) => site.metadata.storage === 'none');
	}
);

export const selectTemporarySites = createSelector(
	[selectAllSites],
	(sites: SiteInfo[]) => {
		return sites.filter((site) => site.metadata.storage === 'none');
	}
);

export const selectSitesLoaded = createSelector(
	[
		(state: { sites: ReturnType<typeof sitesSlice.reducer> }) =>
			state.sites.opfsSitesLoadingState,
		(state: { sites: ReturnType<typeof sitesSlice.reducer> }) =>
			state.sites.firstTemporarySiteCreated,
	],
	(opfsSitesLoadingState, firstTemporarySiteCreated) =>
		['loaded', 'error'].includes(opfsSitesLoadingState) &&
		firstTemporarySiteCreated
);

export default sitesSlice.reducer;
