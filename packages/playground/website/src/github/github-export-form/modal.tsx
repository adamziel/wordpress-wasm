import Modal, { defaultStyles } from '../../components/modal';
import GitHubExportForm, { GitHubExportFormProps } from './form';
import { usePlaygroundClient } from '../../lib/use-playground-client';
import { PlaygroundDispatch } from '../../lib/state/redux/store';
import { useDispatch } from 'react-redux';
import { setActiveModal } from '../../lib/state/redux/slice-ui';
import { useEffect } from 'react';

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

	useEffect(() => {
		const url = new URL(window.location.href);
		url.searchParams.set('modal', 'github-export');
		window.history.replaceState({}, '', url.href);
	}, []);

	const closeModal = () => {
		dispatch(setActiveModal(null));
	}

	return (
		<Modal
			style={{
				...defaultStyles,
				content: { ...defaultStyles.content, width: 600 },
			}}
			isOpen
			onRequestClose={closeModal}
		>
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
