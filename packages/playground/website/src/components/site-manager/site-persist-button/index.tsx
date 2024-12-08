import { useAppSelector, useAppDispatch } from '../../../lib/state/redux/store';
import {
	DropdownMenu,
	DropdownMenuItem,
	DropdownMenuItemLabel,
	DropdownMenuItemHelpText,
	// @ts-ignore
} from '@wordpress/components/build/dropdown-menu-v2/index.js';
import css from './style.module.css';
import { persistTemporarySite } from '../../../lib/state/redux/persist-temporary-site';
import { selectClientInfoBySiteSlug } from '../../../lib/state/redux/slice-clients';
import { useLocalFsAvailability } from '../../../lib/hooks/use-local-fs-availability';
import { isOpfsAvailable } from '../../../lib/state/opfs/opfs-site-storage';

export function SitePersistButton({
	siteSlug,
	children,
}: {
	siteSlug: string;
	children: React.ReactNode;
}) {
	const clientInfo = useAppSelector((state) =>
		selectClientInfoBySiteSlug(state, siteSlug)
	);
	const localFsAvailability = useLocalFsAvailability(clientInfo?.client);
	const dispatch = useAppDispatch();

	if (!clientInfo?.opfsSync || clientInfo.opfsSync?.status === 'error') {
		return (
			<>
				<DropdownMenu trigger={children}>
					<DropdownMenuItem
						disabled={!isOpfsAvailable}
						onClick={() =>
							dispatch(persistTemporarySite(siteSlug, 'opfs'))
						}
					>
						<DropdownMenuItemLabel>
							Save in this browser
						</DropdownMenuItemLabel>
						{!isOpfsAvailable && (
							<DropdownMenuItemHelpText>
								{localFsAvailability === 'not-available'
									? 'Not available in this browser'
									: 'Not available on this site'}
							</DropdownMenuItemHelpText>
						)}
					</DropdownMenuItem>
					<DropdownMenuItem
						disabled={localFsAvailability !== 'available'}
						onClick={() =>
							dispatch(persistTemporarySite(siteSlug, 'local-fs'))
						}
					>
						<DropdownMenuItemLabel>
							Save in a local directory
						</DropdownMenuItemLabel>
						{localFsAvailability !== 'available' && (
							<DropdownMenuItemHelpText>
								{localFsAvailability === 'not-available'
									? 'Not available in this browser'
									: 'Not available on this site'}
							</DropdownMenuItemHelpText>
						)}
					</DropdownMenuItem>
				</DropdownMenu>
				{clientInfo?.opfsSync?.status === 'error' && (
					<div className={css.error}>
						There has been an error. Please try again.
					</div>
				)}
			</>
		);
	}

	if (
		clientInfo?.opfsSync?.status === 'syncing' &&
		!clientInfo?.opfsSync?.progress
	) {
		return (
			<div className={css.progressInfo}>
				<div>
					<progress id="file" max="100" value="0"></progress>
				</div>
				<div>Preparing to save...</div>
			</div>
		);
	}

	return (
		<div className={css.progressInfo}>
			<div>
				<progress
					id="file"
					max={clientInfo.opfsSync.progress?.total}
					value={clientInfo.opfsSync.progress?.files}
				></progress>
			</div>
			<div>
				{clientInfo.opfsSync.progress?.files}
				{' / '}
				{clientInfo.opfsSync.progress?.total} files saved
			</div>
		</div>
	);
}
