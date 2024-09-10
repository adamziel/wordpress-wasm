import { configureStore } from '@reduxjs/toolkit';
import uiReducer, {
	__internal_uiSlice,
	listenToOnlineOfflineEventsMiddleware,
} from './slice-ui';
import sitesReducer, { selectSiteBySlug, SiteInfo } from './slice-sites';
import { PlaygroundRoute, redirectTo } from '../url/router';
import clientsReducer, { ClientInfo } from './slice-clients';
import { GetDefaultMiddleware } from '@reduxjs/toolkit/dist/getDefaultMiddleware';
import { useDispatch, useSelector } from 'react-redux';

const ignoreSerializableCheck = (getDefaultMiddleware: GetDefaultMiddleware) =>
	getDefaultMiddleware({
		serializableCheck: {
			// Ignore these action types
			ignoredActions: [
				'clients/addClientInfo',
				'clients/updateClientInfo',
			],
			// Ignore these field paths in all actions
			ignoredActionPaths: [
				/payload\.(changes\.)?client/,
				/payload\.(changes\.)?opfsMountDescriptor\.device\.handle/,
			],
			// Ignore these paths in the state
			ignoredPaths: [
				/clients\.entities\.[^.]+\.client/,
				/clients\.entities\.[^.]+\.opfsMountDescriptor\.device\.handle/,
			],
		},
	});

const store = configureStore({
	reducer: {
		ui: uiReducer,
		sites: sitesReducer,
		clients: clientsReducer,
	},
	middleware: (getDefaultMiddleware) =>
		ignoreSerializableCheck(getDefaultMiddleware).concat(
			listenToOnlineOfflineEventsMiddleware
		),
});

export type RootState = ReturnType<typeof store.getState>;

export function useAppSelector<T>(
	selector: (state: PlaygroundReduxState) => T
): T {
	return useSelector(selector);
}

export function useAppDispatch() {
	return useDispatch<PlaygroundDispatch>();
}

export const selectActiveSite = (
	state: PlaygroundReduxState
): SiteInfo | undefined =>
	state.ui.activeSiteSlug
		? state.sites.entities[state.ui.activeSiteSlug]
		: undefined;

export const useActiveSite = () => useAppSelector(selectActiveSite);

export const setActiveSite = (slug: string | undefined) => {
	return (
		dispatch: PlaygroundDispatch,
		getState: () => PlaygroundReduxState
	) => {
		dispatch(__internal_uiSlice.actions.setActiveSite(slug));
		if (slug) {
			const site = selectSiteBySlug(getState(), slug);
			redirectTo(PlaygroundRoute.site(site));
		}
	};
};

export const getActiveClientInfo = (
	state: PlaygroundReduxState
): ClientInfo | undefined =>
	state.ui.activeSiteSlug
		? state.clients.entities[state.ui.activeSiteSlug]
		: undefined;

// Define RootState type
export type PlaygroundReduxState = ReturnType<typeof store.getState>;

// Define AppDispatch type
export type PlaygroundDispatch = typeof store.dispatch;

export default store;
