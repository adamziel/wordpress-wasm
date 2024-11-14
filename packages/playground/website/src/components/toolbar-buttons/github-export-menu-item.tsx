import { MenuItem } from '@wordpress/components';
import { setActiveModal } from '../../lib/state/redux/slice-ui';
import { PlaygroundDispatch } from '../../lib/state/redux/store';
import { useDispatch } from 'react-redux';
import { modalSlugs } from '../layout';

interface Props {
	onClose: () => void;
	disabled?: boolean;
}
export function GithubExportMenuItem({ onClose, disabled }: Props) {
	const dispatch: PlaygroundDispatch = useDispatch();
	return (
		<MenuItem
			aria-label="Export WordPress theme, plugin, or wp-content directory to a GitHub repository as a Pull Request."
			disabled={disabled}
			onClick={() => {
				dispatch(setActiveModal(modalSlugs.GITHUB_EXPORT));
				onClose();
			}}
		>
			Export Pull Request to GitHub
		</MenuItem>
	);
}
