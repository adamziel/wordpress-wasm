import GitHubExportForm, { GitHubExportFormProps } from './form';
import { usePlaygroundClient } from '../../lib/use-playground-client';
import { PlaygroundDispatch } from '../../lib/state/redux/store';
import { useDispatch } from 'react-redux';
import { setActiveModal } from '../../lib/state/redux/slice-ui';
import { Modal } from '../../components/modal';

interface GithubExportModalProps {
	allowZipExport: GitHubExportFormProps['allowZipExport'];
	onExported?: GitHubExportFormProps['onExported'];
	initialFilesBeforeChanges?: GitHubExportFormProps['initialFilesBeforeChanges'];
	initialValues?: GitHubExportFormProps['initialValues'];
}
export function GithubExportModal({
	onExported,
	allowZipExport,
	initialValues,
	initialFilesBeforeChanges,
}: GithubExportModalProps) {
	const dispatch: PlaygroundDispatch = useDispatch();
	const playground = usePlaygroundClient();

	const closeModal = () => {
		dispatch(setActiveModal(null));
	};

	return (
		<Modal title="Export to GitHub" onRequestClose={closeModal}>
			<GitHubExportForm
				onClose={closeModal}
				onExported={onExported}
				playground={playground!}
				initialValues={initialValues}
				initialFilesBeforeChanges={initialFilesBeforeChanges}
				allowZipExport={allowZipExport}
			/>
		</Modal>
	);
}
