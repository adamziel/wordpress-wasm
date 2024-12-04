import { Button, Flex, FlexItem } from '@wordpress/components';
import { Modal } from '../modal';
import { SitePersistButton } from '../site-manager/site-persist-button';
import {
	useAppDispatch,
	useAppSelector,
	selectActiveSite,
} from '../../lib/state/redux/store';
import { setActiveModal } from '../../lib/state/redux/slice-ui';
import { usePlaygroundClient } from '../../lib/use-playground-client';

export function MissingSiteModal() {
	const dispatch = useAppDispatch();
	const closeModal = () => dispatch(setActiveModal(null));

	const activeSite = useAppSelector((state) => selectActiveSite(state));
	const playground = usePlaygroundClient(activeSite?.slug);

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
				page. The modal begins with a heading 2 called ... TODO"
			onRequestClose={closeModal}
		>
			<p>What do you want to do?</p>
			<Flex direction="column" gap={5} expanded={true}>
				<FlexItem>
					<Button
						variant="secondary"
						onClick={(e: React.MouseEvent) => {
							e.preventDefault();
							e.stopPropagation();
							closeModal();
						}}
					>
						Continue with Temporary Site
					</Button>
				</FlexItem>
				<FlexItem>
					<SitePersistButton siteSlug={activeSite.slug}>
						<Button
							variant="primary"
							disabled={!playground}
							aria-label="Save site locally"
						>
							Save
						</Button>
					</SitePersistButton>
				</FlexItem>
			</Flex>
		</Modal>
	);
}
