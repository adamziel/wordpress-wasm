import { useDispatch } from 'react-redux';
import RenameForm from '../rename-form/index';
import { Modal } from '../modal';
import { setActiveModal } from '../../lib/state/redux/slice-ui';
import { PlaygroundDispatch } from '../../lib/state/redux/store';

export const RenameSiteModal = () => {
	const dispatch: PlaygroundDispatch = useDispatch();

	const closeModal = () => {
		dispatch(setActiveModal(null));
	};

	return (
		<Modal
			title="Rename Playground"
			contentLabel='This is a dialog window which overlays the main content of the
				page. The modal begins with a heading 2 called "Rename
				Playground". Pressing the Cancel button will close
				the modal and bring you back to where you were on the page.'
			onRequestClose={closeModal}
		>
			<RenameForm onClose={closeModal} />
		</Modal>
	);
};
