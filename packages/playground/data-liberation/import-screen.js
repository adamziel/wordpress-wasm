import { store, getContext } from '@wordpress/interactivity';
const { state } = store('dataLiberation', {
	state: {
		selectedImportType: 'wxr_file',
		get isImportTypeSelected() {
			return getContext().importType === state.selectedImportType;
		},
	},
	actions: {
		setImportType: () => {
			state.selectedImportType = getContext().importType;
		},
	},
});
