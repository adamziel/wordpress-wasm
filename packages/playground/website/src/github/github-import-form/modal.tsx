import Modal, { defaultStyles } from '../../components/modal';
import GitHubImportForm, { GitHubImportFormProps } from './form';
import { usePlaygroundClient } from '../../lib/use-playground-client';
import { setActiveModal } from '../../lib/state/redux/slice-ui';
import { PlaygroundDispatch } from '../../lib/state/redux/store';
import { useDispatch } from 'react-redux';
import { useEffect } from 'react';

interface GithubImportModalProps {
	defaultOpen?: boolean;
	onImported?: GitHubImportFormProps['onImported'];
}
export function GithubImportModal({ defaultOpen, onImported }: GithubImportModalProps) {
	const dispatch: PlaygroundDispatch = useDispatch();
	const playground = usePlaygroundClient();

	useEffect(() => {
		const url = new URL(window.location.href);
		url.searchParams.set('modal', 'github-import');
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
			<GitHubImportForm
				playground={playground!}
				onClose={closeModal}
				onImported={(details) => {
					playground!.goTo('/');
					// eslint-disable-next-line no-alert
					alert(
						'Import finished! Your Playground site has been updated.'
					);
					onImported?.(details);
					closeModal();
				}}
			/>
		</Modal>
	);
}
