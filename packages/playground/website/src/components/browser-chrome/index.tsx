import React from 'react';
import css from './style.module.css';
import AddressBar from '../address-bar';
import classNames from 'classnames';
import { useMediaQuery } from '@wordpress/compose';
import {
	useAppSelector,
	getActiveClientInfo,
	useActiveSite,
	useAppDispatch,
} from '../../lib/state/redux/store';
import { SyncLocalFilesButton } from '../sync-local-files-button';
import { Dropdown, Icon } from '@wordpress/components';
import { Modal } from '../../components/modal';
import { cog } from '@wordpress/icons';
import Button from '../button';
import { ActiveSiteSettingsForm } from '../site-manager/site-settings-form';
import { setSiteManagerOpen } from '../../lib/state/redux/slice-ui';
import { SiteManagerIcon } from '@wp-playground/components';

interface BrowserChromeProps {
	children?: React.ReactNode;
	hideToolbar?: boolean;
	className?: string;
}

export default function BrowserChrome({
	children,
	hideToolbar,
	className,
}: BrowserChromeProps) {
	const clientInfo = useAppSelector(getActiveClientInfo);
	const activeSite = useActiveSite();
	const showAddressBar = !!clientInfo;
	const url = clientInfo?.url;
	const dispatch = useAppDispatch();
	const addressBarClass = classNames(css.addressBarSlot, {
		[css.isHidden]: !showAddressBar,
	});
	const wrapperClass = classNames(
		css.wrapper,
		css.hasFullSizeWindow,
		className
	);
	const isMobileUi = useMediaQuery('(max-width: 875px)');
	const [isModalOpen, setIsModalOpen] = React.useState(false);
	const onToggle = () => setIsModalOpen(!isModalOpen);
	const closeModal = () => setIsModalOpen(false);

	return (
		<div className={wrapperClass} data-cy="simulated-browser">
			<div className={`${css.window} browser-chrome-window`}>
				<header
					className={`
						${css.toolbar}
						${hideToolbar ? css.toolbarHidden : ''}
					`}
					aria-label="Playground toolbar"
				>
					<div className={css.windowControls}>
						<Button
							variant="browser-chrome"
							aria-label="Open Site Manager"
							className={css.openSiteManagerButton}
							onClick={() => {
								dispatch(setSiteManagerOpen(true));
							}}
						>
							<SiteManagerIcon />
						</Button>
					</div>

					<div className={addressBarClass}>
						<AddressBar
							url={url}
							onUpdate={(newUrl) =>
								clientInfo?.client.goTo(newUrl)
							}
						/>
					</div>

					<div className={css.toolbarButtons}>
						{isMobileUi ? (
							<>
								<Button
									variant="browser-chrome"
									aria-label="Edit Playground settings"
									onClick={onToggle}
									aria-expanded={isModalOpen}
									style={{
										padding: '0 10px',
										fill: '#FFF',
										alignItems: 'center',
										display: 'flex',
									}}
								>
									<Icon icon={cog} />
								</Button>
								{isModalOpen && (
									<Modal
										isFullScreen={true}
										title="Playground settings"
										onRequestClose={closeModal}
									>
										<ActiveSiteSettingsForm
											onSubmit={closeModal}
										/>
									</Modal>
								)}
							</>
						) : (
							<Dropdown
								className="my-container-class-name"
								contentClassName="my-dropdown-content-classname"
								popoverProps={{ placement: 'bottom-start' }}
								renderToggle={({ isOpen, onToggle }) => (
									<Button
										variant="browser-chrome"
										aria-label="Edit Playground settings"
										onClick={onToggle}
										aria-expanded={isOpen}
										style={{
											padding: '0 10px',
											fill: '#FFF',
											alignItems: 'center',
											display: 'flex',
										}}
									>
										<Icon icon={cog} />
									</Button>
								)}
								renderContent={({ onClose }) => (
									<div
										style={{
											width: 400,
											maxWidth: '100vw',
											padding: 0,
										}}
									>
										<div className={css.headerSection}>
											<h2 style={{ margin: 0 }}>
												Playground settings
											</h2>
										</div>
										<ActiveSiteSettingsForm
											onSubmit={onClose}
										/>
									</div>
								)}
							/>
						)}
						{activeSite?.metadata?.storage === 'local-fs' ? (
							<SyncLocalFilesButton />
						) : null}
					</div>
				</header>
				<div className={css.content}>{children}</div>
			</div>
		</div>
	);
}
