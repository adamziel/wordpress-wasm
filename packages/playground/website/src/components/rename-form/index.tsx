import React from 'react';
import { useState } from 'react';
import forms from '../../forms.module.css';
import ModalButtons from '../modal/modal-buttons';
import { TextControl } from '@wordpress/components';
import { useActiveSite, useAppDispatch } from '../../lib/state/redux/store';
import { randomSiteName } from '../../lib/state/redux/random-site-name';
import { updateSiteMetadata } from '../../lib/state/redux/slice-sites';

interface RenameFormProps {
	onClose: () => void;
}

export default function RenameForm({ onClose }: RenameFormProps) {
	const activeSite = useActiveSite();
	const dispatch = useAppDispatch();

	const [newName, setNewName] = useState<string>(
		activeSite?.metadata.name ?? randomSiteName()
	);
	const [areBusy, setAreBusy] = useState(false);

	async function handleSubmit() {
		if (!activeSite || !activeSite.slug) {
			return null;
		}
		setAreBusy(true);
		await dispatch(
			updateSiteMetadata({
				slug: activeSite.slug,
				changes: {
					name: newName,
				},
			})
		);
		setAreBusy(false);
		onClose();
	}

	function submitOnEnter(e: React.KeyboardEvent<HTMLInputElement>) {
		if (e.key === 'Enter') {
			handleSubmit();
		}
	}

	return (
		<>
			<TextControl
				label="Playground name"
				placeholder="My Playground"
				value={newName}
				onChange={setNewName}
				onKeyDown={submitOnEnter}
			/>

			<ModalButtons
				areDisabled={!newName}
				onCancel={onClose}
				onSubmit={handleSubmit}
				submitText="Rename"
				areBusy={areBusy}
			/>
		</>
	);
}
