import { store, getContext } from '@wordpress/interactivity';
const { state } = store('dataLiberation', {
	state: {
		selectedImportType: 'wxr_file',
		get isImportTypeSelected() {
			return getContext().importType === state.selectedImportType;
		},
		get frontloadingFailed() {
			return getContext().frontloadingPlaceholders.some(
				(placeholder) => placeholder.post_status === 'error'
			);
		},
	},
	actions: {
		setImportType: () => {
			state.selectedImportType = getContext().importType;
		},
	},
});
