import { MenuItem } from '@wordpress/components';

import { useDispatch } from 'react-redux';
import { PlaygroundDispatch } from '../../lib/state/redux/store';
import { setActiveModal } from '../../lib/state/redux/slice-ui';
import { modalSlugs } from '../layout';

type Props = { onClose: () => void; disabled?: boolean };
export function ReportError({ onClose, disabled }: Props) {
	const dispatch: PlaygroundDispatch = useDispatch();
	return (
		<MenuItem
			data-cy="report-error"
			aria-label="Report an error in Playground"
			disabled={disabled}
			onClick={() => {
				dispatch(setActiveModal(modalSlugs.ERROR_REPORT));
				onClose();
			}}
		>
			Report error
		</MenuItem>
	);
}
