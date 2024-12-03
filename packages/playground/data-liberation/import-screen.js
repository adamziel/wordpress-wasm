/**
 * @TODO
 * - Replace window.prompt() calls with a pretty UI-based dialog or
 *   an inline form or something.
 */
import { store, getContext, getServerContext } from '@wordpress/interactivity';

const apiFetch = window.wp.apiFetch;

const { state, actions } = store('dataLiberation', {
	state: {
		get isImportTypeSelected() {
			return getContext()?.importType === state.selectedImportType;
		},
		get frontloadingFailed() {
			return getContext()?.item.post_status === 'error';
		},
		get isCurrentImportAtStage() {
			return getContext()?.stage.id === state.currentImport.stage;
		},
	},
	/**
	 * We're bombarding the server with HTTP requests both to run the import and to
	 * refresh the reported progress. Do we need such an aggressive refresh rate?
	 */
	callbacks: {
		/**
		 * Fetches a fresh interactivity state from the server every second.
		 */
		async startRefreshingProgress() {
			while (true) {
				if (
					!state.currentImport.active ||
					state.currentImport.stage === '#finished'
				) {
					await new Promise((resolve) => setTimeout(resolve, 1000));
					continue;
				}

				const response = await apiFetch({
					path: '/data-liberation/v1/interactivity-state',
				});
				Object.assign(state, response);

				await new Promise((resolve) => setTimeout(resolve, 2000));
			}
		},
		/**
		 * Continuously asks the server to continue the import.
		 *
		 * @TODO: Ensure two parallel requests are never processing the same import.
		 *        That would lead to race conditions, undefined states, bad stuff in general.
		 */
		async startImportLoop() {
			let failuresInARow = 0;
			const maxFailures = 3;
			while (true) {
				if (
					!state.currentImport.active ||
					state.currentImport.stage === '#finished'
				) {
					await new Promise((resolve) => setTimeout(resolve, 1000));
					continue;
				}

				try {
					const response = await fetch(
						`${window.location.pathname}?page=data-liberation&continue=true`,
						{
							credentials: 'same-origin', // Preserves cookies
						}
					);
					const text = await response.text();
					const parser = new DOMParser();
					const doc = parser.parseFromString(text, 'text/html');
					const importOutput = doc.querySelector('#import-output');
					if (importOutput) {
						document.querySelector('#import-output').innerHTML =
							importOutput.innerHTML;
					}
					failuresInARow = 0;
				} catch (error) {
					failuresInARow++;
					// TODO: notify the user about the problem.
					if (failuresInARow >= maxFailures) {
						throw error;
					}
				}
				await new Promise((resolve) => setTimeout(resolve, 1000));
			}
		},
	},
	actions: {
		setImportType: () => {
			if (getContext()) {
				state.selectedImportType = getContext().importType;
			}
		},

		async archiveImport() {
			window.location.href = `${window.location.pathname}?page=data-liberation&archive=true`;
		},

		// Existing download management actions
		async retryDownload(event) {
			const postId = event.target.dataset.postId;
			const response = await apiFetch({
				path: '/data-liberation/v1/retry-download',
				method: 'POST',
				data: { post_id: postId },
			});

			if (response.success) {
				window.location.reload();
			}
		},

		async ignoreDownload(event) {
			const postId = event.target.dataset.postId;
			const response = await apiFetch({
				path: '/data-liberation/v1/ignore-download',
				method: 'POST',
				data: { post_id: postId },
			});

			if (response.success) {
				window.location.reload();
			}
		},

		async changeDownloadUrl(event) {
			const postId = event.target.dataset.postId;
			const newUrl = prompt('Enter the new URL for this asset:');

			if (!newUrl) return;

			const response = await apiFetch({
				path: '/data-liberation/v1/change-download-url',
				method: 'POST',
				data: {
					post_id: postId,
					new_url: newUrl,
				},
			});

			if (response.success) {
				window.location.reload();
			}
		},
	},
});
