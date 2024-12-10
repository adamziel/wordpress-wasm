import { createSlice, PayloadAction, Middleware } from '@reduxjs/toolkit';

export type SiteError =
	| 'directory-handle-not-found-in-indexeddb'
	| 'directory-handle-permission-denied'
	| 'directory-handle-directory-does-not-exist'
	| 'directory-handle-unknown-error'
	// @TODO: Improve name?
	| 'site-boot-failed';

export type SiteManagerSection = 'sidebar' | 'site-details' | 'blueprints';
export interface UIState {
	activeSite?: {
		slug: string;
		error?: SiteError;
	};
	activeModal: string | null;
	offline: boolean;
	siteManagerIsOpen: boolean;
	siteManagerSection: SiteManagerSection;
}

const query = new URL(document.location.href).searchParams;
const isEmbeddedInAnIframe = window.self !== window.top;
// @TODO: Centralize these breakpoint sizes.
const isMobile = window.innerWidth < 875;

const shouldOpenSiteManagerByDefault = false;

const initialState: UIState = {
	/**
	 * Don't show the error report modal after a page refresh.
	 * There's an action call below to remove the error-report modal attribute
	 * from the URL.
	 */
	activeModal:
		query.get('modal') === 'error-report'
			? null
			: query.get('modal') || null,
	offline: !navigator.onLine,
	// NOTE: Please do not eliminate the cases in this siteManagerIsOpen expression,
	// even if they seem redundant. We may experiment which toggling the manager
	// to be open by default or closed by default, and we do not want to lose
	// specific reasons for the manager to be closed.
	siteManagerIsOpen:
		shouldOpenSiteManagerByDefault &&
		// The site manager should not be shown at all in seamless mode.
		query.get('mode') !== 'seamless' &&
		// We do not expect to render the Playground app UI in an iframe.
		!isEmbeddedInAnIframe &&
		// Don't default to the site manager on mobile, as that would mean
		// seeing something that's not Playground filling your entire screen –
		// quite a confusing experience.
		!isMobile,
	siteManagerSection: 'site-details',
};

const uiSlice = createSlice({
	name: 'ui',
	initialState,
	reducers: {
		setActiveSite: (state, action: PayloadAction<string | undefined>) => {
			state.activeSite = action.payload
				? {
						slug: action.payload,
				  }
				: undefined;
		},
		setActiveSiteError: (state, action: PayloadAction<SiteError>) => {
			if (state.activeSite) {
				state.activeSite.error = action.payload;
			}
		},
		setActiveModal: (state, action: PayloadAction<string | null>) => {
			const url = new URL(window.location.href);
			if (action.payload === null) {
				url.searchParams.delete('modal');
			} else {
				url.searchParams.set('modal', action.payload);
			}
			window.history.replaceState({}, '', url.href);

			state.activeModal = action.payload;
		},
		setOffline: (state, action: PayloadAction<boolean>) => {
			state.offline = action.payload;
		},
		setSiteManagerOpen: (state, action: PayloadAction<boolean>) => {
			state.siteManagerIsOpen = action.payload;
		},
		setSiteManagerSection: (
			state,
			action: PayloadAction<SiteManagerSection>
		) => {
			state.siteManagerSection = action.payload;
		},
	},
});

export const __internal_uiSlice = uiSlice;

let ranOnce = false;
export const listenToOnlineOfflineEventsMiddleware: Middleware =
	(store) => (next) => (action) => {
		if (!ranOnce) {
			ranOnce = true;
			if (typeof window !== 'undefined') {
				window.addEventListener('online', () => {
					store.dispatch(uiSlice.actions.setOffline(false));
				});
				window.addEventListener('offline', () => {
					store.dispatch(uiSlice.actions.setOffline(true));
				});
			}
			/**
			 * Hide the error report modal on page load.
			 * It's too common to refresh the page after an error occurs,
			 * let's not bother the user with an empty error reporting modal.
			 */
			if (query.get('modal') === 'error-report') {
				setTimeout(() => {
					store.dispatch(uiSlice.actions.setActiveModal(null));
				}, 0);
			}
		}
		return next(action);
	};

export const {
	setActiveModal,
	setActiveSiteError,
	setOffline,
	setSiteManagerOpen,
	setSiteManagerSection,
} = uiSlice.actions;

export default uiSlice.reducer;
