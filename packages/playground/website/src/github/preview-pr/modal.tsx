import { Modal } from '@wordpress/components';
import PreviewPRForm from './form';
import { setActiveModal } from '../../lib/state/redux/slice-ui';
import { PlaygroundDispatch } from '../../lib/state/redux/store';
import { useDispatch } from 'react-redux';
import css from '../../components/modal/style.module.css';

interface PreviewPRModalProps {
	target: 'wordpress' | 'gutenberg';
}

const targetName = {
	'wordpress': 'WordPress',
	'gutenberg': 'Gutenberg'
}

export function PreviewPRModal({ target }: PreviewPRModalProps) {
	const dispatch: PlaygroundDispatch = useDispatch();
	const closeModal = () => {
		dispatch(setActiveModal(null));
	};
	function handleImported() {
		closeModal();
	}
	return (
		<Modal
			className={css.modalSmall}
			title={`Preview a ${targetName[target]} PR`}
			onRequestClose={closeModal}
		>
			<PreviewPRForm
				onClose={closeModal}
				onImported={handleImported}
				target={target}
			/>
		</Modal>
	);
}