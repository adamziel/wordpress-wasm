import React from 'react';
import { useRef, useState } from 'react';
import { PlaygroundClient, importWordPressFiles } from '@wp-playground/client';
import forms from '../../forms.module.css';
import { logger } from '@php-wasm/logger';
import ModalButtons from '../modal/modal-buttons';

interface ImportFormProps {
	playground: PlaygroundClient;
	onImported: () => void;
	onClose: () => void;
}

export default function ImportForm({
	playground,
	onImported,
	onClose,
}: ImportFormProps) {
	const fileInputRef = useRef<any>();
	const [file, setFile] = useState<File | null>(null);
	const [error, setError] = useState<string>('');
	function handleSelectFile(e: React.ChangeEvent<HTMLInputElement>) {
		setFile(e.target.files![0]);
	}

	async function handleSubmit(e: React.FormEvent<HTMLFormElement>) {
		e.preventDefault();
		if (!file) {
			return;
		}

		try {
			await importWordPressFiles(playground, { wordPressFilesZip: file });
		} catch (error) {
			logger.error(error);
			setError(
				'Unable to import file. Is it a valid WordPress Playground export?'
			);
			return;
		}

		onImported();
	}

	return (
		<>
			<p>
				You may replace the current WordPress Playground site with a
				previously exported one.
			</p>
			<div className={`${forms.formGroup} ${forms.formGroupLast}`}>
				{error ? <div className={forms.error}>{error}</div> : null}
				<input
					type="file"
					id="import-select-file"
					onChange={handleSelectFile}
					ref={fileInputRef}
					accept="application/zip"
				/>
			</div>

			<ModalButtons
				areDisabled={!file}
				onCancel={onClose}
				onSubmit={handleSubmit}
				submitText="Import"
			/>
		</>
	);
}
