import { MenuItem } from '@wordpress/components';
import { details } from '@wordpress/icons';

import { useDispatch } from 'react-redux';
import { PlaygroundDispatch } from '../../lib/state/redux/store';
import { setActiveModal } from '../../lib/state/redux/slice-ui';
import { modalSlugs } from '../layout';

type Props = { onClose: () => void };
export function ViewLogs({ onClose }: Props) {
	const dispatch: PlaygroundDispatch = useDispatch();
	return (
		<MenuItem
			icon={details}
			iconPosition="left"
			data-cy="view-logs"
			aria-label="View logs"
			onClick={() => {
				dispatch(setActiveModal(modalSlugs.LOG));
				onClose();
			}}
		>
			View logs
		</MenuItem>
	);
}
