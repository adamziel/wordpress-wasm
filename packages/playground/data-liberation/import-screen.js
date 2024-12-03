import { store, getContext } from '@wordpress/interactivity';

const apiFetch = window.wp.apiFetch;

const { state, actions } = store('dataLiberation', {
	state: {
		selectedImportType: 'wxr_file',
		get isImportTypeSelected() {
			return getContext().importType === state.selectedImportType;
		},
		get frontloadingFailed() {
			return getContext().item.post_status === 'error';
		},
	},
	actions: {
		setImportType: () => {
			state.selectedImportType = getContext().importType;
		},
		async retryDownload(event) {
			const postId = event.target.dataset.postId;
			const response = await apiFetch({
				path: '/data-liberation/v1/retry-download',
				method: 'POST',
				data: {
					post_id: postId,
				},
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
				data: {
					post_id: postId,
				},
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
