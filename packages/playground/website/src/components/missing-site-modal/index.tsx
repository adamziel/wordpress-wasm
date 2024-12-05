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
				The <b>{activeSite.metadata.name}</b> Playground does not exist,
				so we loaded a temporary Playground instead.
			</p>
			<p>
				If you want to preserve your changes, you can save the
				Playground to browser storage.
			</p>
			{/* Note: We are using row-reverse direction so the secondary
				button can display first in row orientation and last when
				wrapping to vertical orientation.
				
				This matches Modal style recommendations here:
				https://github.com/WordPress/gutenberg/tree/1418350eb5a1f15e109fc96af385bdd029fc7304/packages/components/src/modal#side-by-side-buttons-recommended
			*/}
			<Flex
				direction="row-reverse"
				gap={5}
				expanded={true}
				wrap={true}
				justify="flex-start"
			>
				<FlexItem>
					<SitePersistButton
						siteSlug={activeSite.slug}
						storage="opfs"
					>
						<Button variant="primary">
							Save Playground to browser storage
						</Button>
					</SitePersistButton>
				</FlexItem>
				<FlexItem>
					<Button
						variant="link"
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
			</Flex>
		</Modal>
	);
}
