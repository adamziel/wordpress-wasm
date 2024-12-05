import { Button, Flex, FlexItem } from '@wordpress/components';
import { Modal } from '../modal';
import { SitePersistButton } from '../site-manager/site-persist-button';
import {
	useAppDispatch,
	useAppSelector,
	selectActiveSite,
} from '../../lib/state/redux/store';
import { setActiveModal } from '../../lib/state/redux/slice-ui';
import { selectClientInfoBySiteSlug } from '../../lib/state/redux/slice-clients';

export function MissingSiteModal() {
	const dispatch = useAppDispatch();
	const closeModal = () => dispatch(setActiveModal(null));

	const activeSite = useAppSelector((state) => selectActiveSite(state));
	const clientInfo = useAppSelector(
		(state) =>
			activeSite?.slug &&
			selectClientInfoBySiteSlug(state, activeSite?.slug)
	);

	if (!activeSite) {
		return null;
	}
	if (activeSite.metadata.storage !== 'none') {
		return null;
	}

	// TODO: Improve language for this modal
	return (
		<Modal
			title="Save to browser storage?"
			contentLabel="This is a dialog window which overlays the main content of the
				page. It offers the user a choice between using a temporary Playground
				and a persistent Playground that is saved to browser storage."
			isDismissible={false}
			shouldCloseOnClickOutside={false}
			onRequestClose={closeModal}
		>
			<p>
				WordPress Playground tried to load a Playground that does not
				exist, so we loaded a temporary Playground instead. Any changes
				to temporary Playgrounds are lost on page refresh.
			</p>
			<p>Would you like to save this Playground to browser storage?</p>
			<Flex direction="column" gap={2} expanded={true}>
				<FlexItem>
					<Button
						variant="secondary"
						disabled={
							!!clientInfo &&
							clientInfo?.opfsSync?.status === 'syncing'
						}
						onClick={(e: React.MouseEvent) => {
							e.preventDefault();
							e.stopPropagation();
							closeModal();
						}}
					>
						Keep using a temporary Playground
					</Button>
				</FlexItem>
				<FlexItem>
					<SitePersistButton siteSlug={activeSite.slug}>
						<Button
							variant="primary"
							aria-label="Save site locally"
						>
							Save Playground to browser storage
						</Button>
					</SitePersistButton>
				</FlexItem>
			</Flex>
		</Modal>
	);
}
