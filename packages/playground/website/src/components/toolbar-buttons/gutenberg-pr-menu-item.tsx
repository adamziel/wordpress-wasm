import { MenuItem } from '@wordpress/components';
import { setActiveModal } from '../../lib/state/redux/slice-ui';
import { PlaygroundDispatch } from '../../lib/state/redux/store';
import { useDispatch } from 'react-redux';
import { modalSlugs } from '../layout';

interface Props {
	onClose: () => void;
	disabled?: boolean;
}
export function GutenbergPRMenuItem({ onClose, disabled }: Props) {
	const dispatch: PlaygroundDispatch = useDispatch();
	return (
		<MenuItem
			aria-label="Preview Gutenberg PR."
			disabled={disabled}
			onClick={() => {
				dispatch(setActiveModal(modalSlugs.PREVIEW_PR_GUTENBERG));
				onClose();
			}}
		>
			Preview a Gutenberg PR
		</MenuItem>
	);
}
